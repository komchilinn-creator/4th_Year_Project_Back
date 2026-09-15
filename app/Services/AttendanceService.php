<?php namespace App\Services;

use App\Helpers\HttpException;
use App\Models\{Attendance, Student, Subject, Teacher};
use App\Services\QrCodeService;

final class AttendanceService
{
    public function __construct(private \PDO $db) {}

    public function create(int $userId, array $in): array
    {
        $teacher = (new Teacher($this->db))->byUser($userId);
        $subjectId = (int)($in['subject_id'] ?? 0);
        $minutes = (int)($in['minutes'] ?? 10);
        $title = trim((string)($in['title'] ?? ''));
        if (!$teacher || !$subjectId || !(new Subject($this->db))->exists($subjectId)) {
            throw new HttpException('A valid subject is required.', 422);
        }
        if ($title === '' || strlen($title) > 150) {
            throw new HttpException('A title between 1 and 150 characters is required.', 422);
        }
        if ($minutes < 1 || $minutes > 240) {
            throw new HttpException('QR expiry must be between 1 and 240 minutes.', 422);
        }
        $token = (new QrCodeService())->token();
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE qr_codes q JOIN attendance_sessions s ON s.id=q.session_id SET q.active=0 WHERE s.teacher_id=? AND q.active=1')->execute([$teacher['id']]);
            $this->db->prepare('UPDATE attendance_sessions SET active=0 WHERE teacher_id=? AND active=1')->execute([$teacher['id']]);
            $this->db->prepare('INSERT INTO attendance_sessions(teacher_id,subject_id,title,starts_at,expires_at) VALUES(?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? MINUTE))')->execute([$teacher['id'], $subjectId, $title, $minutes]);
            $sessionId = (int)$this->db->lastInsertId();
            $this->db->prepare('INSERT INTO qr_codes(session_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))')->execute([$sessionId, password_hash($token, PASSWORD_DEFAULT), $minutes]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $expiresAt = $this->db
            ->prepare('SELECT expires_at FROM attendance_sessions WHERE id = ?');
        $expiresAt->execute([$sessionId]);

        return [
            'session_id' => $sessionId,
            'token' => $token,
            'qr_payload' => 'ATTENDQR:' . $token,
            'expires_at' => $expiresAt->fetchColumn(),
            'message' => 'Attendance session created.',
        ];
    }

    public function scan(int $userId, string $token): array
    {
        $token = $this->normaliseQrPayload($token);
        if ($token === '') {
            throw new HttpException('A QR token is required.', 422);
        }
        $student = (new Student($this->db))->byUser($userId);
        if (!$student) {
            throw new HttpException('Student account not found.', 403);
        }
        $rows = $this->db->query('SELECT q.token_hash,q.session_id,s.subject_id,s.teacher_id FROM qr_codes q JOIN attendance_sessions s ON s.id=q.session_id WHERE q.active=1 AND s.active=1 AND q.expires_at>NOW() AND s.expires_at>NOW()')->fetchAll();
        $qr = null;
        foreach ($rows as $row) {
            if (password_verify($token, $row['token_hash'])) {
                $qr = $row;
                break;
            }
        }
        if (!$qr) {
            throw new HttpException('Invalid QR code or the QR code has expired.', 422);
        }

        // The current data model has no student-to-subject enrollment table.  Do not
        // incorrectly reject a valid scan merely because the teacher has no schedule.
        try {
            $recorded = (new Attendance($this->db))->record((int) $qr['session_id'], (int) $student['id']);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new HttpException('Attendance has already been recorded.', 409);
            }
            throw $e;
        }
        if (!$recorded) {
            throw new HttpException('Attendance could not be recorded. Please try again.', 500);
        }

        return [
            'attendance_recorded' => true,
            'message' => 'Yes - your attendance has been recorded in the teacher\'s roll call.',
        ];
    }

    private function normaliseQrPayload(string $payload): string
    {
        $payload = trim($payload);
        $prefix = 'ATTENDQR:';

        if (stripos($payload, $prefix) === 0) {
            $payload = substr($payload, strlen($prefix));
        }

        return strtolower(trim($payload));
    }

    public function live(int $userId): array
    {
        $teacher = (new Teacher($this->db))->byUser($userId);
        $query = $this->db->prepare('SELECT s.id,s.title,CONCAT(sub.code," - ",sub.name) subject,s.expires_at FROM attendance_sessions s JOIN subjects sub ON sub.id=s.subject_id WHERE s.teacher_id=? AND s.active=1 ORDER BY s.id DESC LIMIT 1');
        $query->execute([$teacher['id']]);
        $session = $query->fetch();
        if (!$session) {
            return ['session' => null, 'total_students' => 0, 'present_students' => 0, 'absent_students' => 0, 'attendance' => []];
        }
        $attendance = (new Attendance($this->db))->live((int)$session['id']);
        $total = (int)$this->db->query('SELECT COUNT(*) FROM students')->fetchColumn(); $present = count($attendance);
        return ['session' => $session, 'total_students' => $total, 'present_students' => $present, 'absent_students' => max(0, $total - $present), 'attendance' => $attendance];
    }

    public function manual(int $userId, array $in): array
    {
        $sessionId = (int) ($in['session_id'] ?? 0);
        $studentId = (int) ($in['student_id'] ?? 0);
        $status = (string) ($in['status'] ?? 'present');
        $owner = $this->db->prepare('SELECT s.id FROM attendance_sessions s JOIN teachers t ON t.id=s.teacher_id WHERE s.id=? AND t.user_id=?');
        $owner->execute([$sessionId, $userId]);
        if (!$owner->fetch() || !$studentId || !in_array($status, ['present', 'late', 'absent'], true)) {
            throw new HttpException('Invalid attendance record.', 422);
        }
        try {
            (new Attendance($this->db))->record($sessionId, $studentId, $status);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new HttpException('Attendance has already been recorded.', 409);
            }
            throw $e;
        }
        return ['message' => 'Attendance recorded successfully.'];
    }
}

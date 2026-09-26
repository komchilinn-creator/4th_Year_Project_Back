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
        if (!$teacher) {
            throw new HttpException('Teacher account not found.', 404);
        }
        $subject = (new Teacher($this->db))->subjectForTeacher((int)$teacher['id'], $subjectId);
        if (!$subject) {
            throw new HttpException('You can only create attendance for a subject assigned to your account.', 422);
        }
        $className = null;
        $yearLevel = null;
        $teacherClasses = array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => strtoupper(trim($value)),
            explode(',', (string)($teacher['class_name'] ?? ''))
        ))));
        foreach ($teacherClasses as $teacherClass) {
            if (preg_match('/^([1-9])IT$/', $teacherClass, $classMatches)
                && (int)$subject['year_level'] === (int)$classMatches[1]
                && str_starts_with(strtoupper($subject['code']), 'IT' . $classMatches[1])) {
                $className = $teacherClass;
                $yearLevel = (int)$classMatches[1];
                break;
            }
        }
        if ($className === null || $yearLevel === null) {
            throw new HttpException('The selected subject does not belong to one of your classes.', 422);
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
            $this->db->prepare('INSERT INTO attendance_sessions(teacher_id,subject_id,year_level,class_name,title,starts_at,expires_at) VALUES(?,?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? MINUTE))')->execute([$teacher['id'], $subjectId, $yearLevel, $className, $title, $minutes]);
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
            'year_level' => $yearLevel,
            'class' => $className,
            'subject' => ['id'=>(int)$subject['id'],'code'=>$subject['code'],'name'=>$subject['name']],
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
        $rows = $this->db->query('SELECT q.token_hash,q.session_id,x.subject_id,x.teacher_id,x.year_level,x.class_name,sub.code subject_code,sub.name subject_name,u.full_name teacher_name FROM qr_codes q JOIN attendance_sessions x ON x.id=q.session_id JOIN subjects sub ON sub.id=x.subject_id JOIN teachers t ON t.id=x.teacher_id JOIN users u ON u.id=t.user_id WHERE q.active=1 AND x.active=1 AND q.expires_at>NOW() AND x.expires_at>NOW()')->fetchAll();
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
        if ((int)$student['year_level'] !== (int)$qr['year_level']) {
            throw new HttpException('This attendance session is for a different year level.', 403);
        }
        if ($qr['class_name'] && !str_starts_with(strtoupper((string)$student['class_name']), strtoupper($qr['class_name']))) {
            throw new HttpException('This attendance session is for a different class.', 403);
        }
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
            'session_id' => (int)$qr['session_id'],
            'year_level' => (int)$qr['year_level'],
            'class' => $qr['class_name'] ?: (int)$qr['year_level'] . $this->ordinalSuffix((int)$qr['year_level']) . ' Year',
            'subject' => ['id'=>(int)$qr['subject_id'],'code'=>$qr['subject_code'],'name'=>$qr['subject_name']],
            'teacher' => ['id'=>(int)$qr['teacher_id'],'name'=>$qr['teacher_name']],
            'message' => "Attendance recorded for {$qr['subject_code']} - {$qr['subject_name']}.",
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
        if (!$teacher) throw new HttpException('Teacher account not found.', 404);
        $query = $this->db->prepare('SELECT s.id,s.title,s.year_level,s.class_name,CONCAT(sub.code," - ",sub.name) subject,sub.code subject_code,sub.name subject_name,s.expires_at FROM attendance_sessions s JOIN subjects sub ON sub.id=s.subject_id WHERE s.teacher_id=? AND s.active=1 ORDER BY s.id DESC LIMIT 1');
        $query->execute([$teacher['id']]);
        $session = $query->fetch();
        if (!$session) {
            return ['session' => null, 'total_students' => 0, 'present_students' => 0, 'absent_students' => 0, 'attendance' => []];
        }
        $attendance = (new Attendance($this->db))->live((int)$session['id']);
        $totalQuery=$this->db->prepare("SELECT COUNT(*) FROM students s JOIN users u ON u.id=s.user_id WHERE s.year_level=? AND (? IS NULL OR UPPER(s.class_name) LIKE CONCAT(UPPER(?), '%')) AND u.status='active'");
        $totalQuery->execute([$session['year_level'], $session['class_name'], $session['class_name']]);
        $total = (int)$totalQuery->fetchColumn(); $present = count($attendance);
        return ['session' => $session, 'total_students' => $total, 'present_students' => $present, 'absent_students' => max(0, $total - $present), 'attendance' => $attendance];
    }

    public function manual(int $userId, array $in): array
    {
        $sessionId = (int) ($in['session_id'] ?? 0);
        $studentId = (int) ($in['student_id'] ?? 0);
        $status = (string) ($in['status'] ?? 'present');
        $owner = $this->db->prepare('SELECT s.id,s.year_level FROM attendance_sessions s JOIN teachers t ON t.id=s.teacher_id WHERE s.id=? AND t.user_id=?');
        $owner->execute([$sessionId, $userId]);
        $session=$owner->fetch();
        $studentQuery=$this->db->prepare('SELECT id,year_level FROM students WHERE id=?');
        $studentQuery->execute([$studentId]);
        $student=$studentQuery->fetch();
        if (!$session || !$student || (int)$student['year_level'] !== (int)$session['year_level'] || !in_array($status, ['present', 'late', 'absent'], true)) {
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

    private function ordinalSuffix(int $year): string
    {
        if ($year % 100 >= 11 && $year % 100 <= 13) return 'th';
        return match ($year % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
    }
}

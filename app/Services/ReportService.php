<?php

namespace App\Services;

use App\Helpers\AttendanceCalculator;
use App\Helpers\HttpException;
use App\Models\Student;
use App\Models\Teacher;

final class ReportService
{
    private const REQUIRED_PERCENTAGE = 75;

    public function __construct(private \PDO $db) {}

    public function monthly(array $user, array $filters = []): array
    {
        $month = (string)($filters['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new HttpException('Month must use YYYY-MM format.', 422);
        }

        $start = $month . '-01 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime($start . ' +1 month'));

        return $user['role'] === 'student'
            ? $this->studentMonthly($user, $month, $start, $end)
            : $this->teacherMonthly($user, $month, $start, $end, (int)($filters['subject_id'] ?? 0), (int)($filters['year_level'] ?? 0));
    }

    private function studentMonthly(array $user, string $month, string $start, string $end): array
    {
        $student = (new Student($this->db))->byUser((int) $user['id']);
        if (!$student) throw new HttpException('Student account not found.', 404);

        $statement = $this->db->prepare(
            "SELECT sub.id,sub.code,sub.name,
                    COUNT(DISTINCT x.id) total_sessions,
                    COUNT(DISTINCT CASE WHEN a.status IN ('present','late') THEN a.session_id END) attended
             FROM subjects sub
             LEFT JOIN attendance_sessions x ON x.subject_id=sub.id AND x.starts_at>=? AND x.starts_at<?
             LEFT JOIN attendance a ON a.session_id=x.id AND a.student_id=?
             WHERE sub.year_level=? AND sub.teacher_registration_enabled=1
             GROUP BY sub.id,sub.code,sub.name ORDER BY sub.code"
        );
        $statement->execute([$start, $end, $student['id'], $student['year_level']]);

        return $this->response($statement->fetchAll(), $month, (int) $student['year_level']);
    }

    private function teacherMonthly(array $user, string $month, string $start, string $end, int $subjectId, int $yearLevel): array
    {
        $teacherModel = new Teacher($this->db);
        $teacher = $teacherModel->byUser((int) $user['id']);
        if (!$teacher) throw new HttpException('Teacher account not found.', 404);
        $subjects = $teacherModel->subjectsByUser((int)$user['id']);
        if (!$subjects) throw new HttpException('No subjects are assigned to this teacher.', 422);
        if (!$subjectId) $subjectId = (int)$subjects[0]['id'];
        $subject = $teacherModel->subjectForTeacher((int)$teacher['id'], $subjectId);
        if (!$subject) throw new HttpException('That subject is not assigned to this teacher.', 403);
        if (!$yearLevel) $yearLevel = (int)$subject['year_level'];
        if ($yearLevel !== (int)$subject['year_level']) throw new HttpException('The selected subject does not belong to the selected year level.', 422);

        $statement = $this->db->prepare(
            "SELECT s.id student_id,u.full_name,s.student_no,sub.code,sub.name,
                    COUNT(DISTINCT x.id) total_sessions,
                    COUNT(DISTINCT CASE WHEN a.status IN ('present','late') THEN a.session_id END) attended
             FROM students s JOIN users u ON u.id=s.user_id AND u.status='active'
             JOIN subjects sub ON sub.id=?
             LEFT JOIN attendance_sessions x ON x.subject_id=sub.id AND x.teacher_id=? AND x.year_level=? AND x.starts_at>=? AND x.starts_at<?
             LEFT JOIN attendance a ON a.session_id=x.id AND a.student_id=s.id
             WHERE s.year_level=? GROUP BY s.id,u.full_name,s.student_no,sub.code,sub.name ORDER BY u.full_name"
        );
        $statement->execute([$subjectId, $teacher['id'], $yearLevel, $start, $end, $yearLevel]);
        $response=$this->response($statement->fetchAll(), $month, $yearLevel);
        $response['subject']=['id'=>(int)$subject['id'],'code'=>$subject['code'],'name'=>$subject['name']];
        $response['subjects']=array_map(static function(array $item):array{$item['id']=(int)$item['id'];$item['year_level']=(int)$item['year_level'];return $item;},$subjects);
        return $response;
    }

    private function response(array $rows, string $month, int $yearLevel): array
    {
        $report = array_map(function (array $row): array {
            $attended = (int) $row['attended'];
            $total = (int) $row['total_sessions'];
            $percentage = AttendanceCalculator::percentage($attended, $total);
            return array_merge($row, [
                'attended' => $attended,
                'total_sessions' => $total,
                'percentage' => $percentage,
                'meets_requirement' => $percentage >= self::REQUIRED_PERCENTAGE,
                'status' => $percentage >= self::REQUIRED_PERCENTAGE ? 'Good standing' : 'Below requirement',
            ]);
        }, $rows);

        return ['month'=>$month,'year_level'=>$yearLevel,'required_percentage'=>self::REQUIRED_PERCENTAGE,'report'=>$report];
    }
}

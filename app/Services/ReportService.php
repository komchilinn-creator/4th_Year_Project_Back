<?php

namespace App\Services;

use App\Helpers\AttendanceCalculator;

final class ReportService
{
    public function __construct(private \PDO $db)
    {
    }

    public function monthly(array $user): array
    {
        $scope = $this->sessionScope($user);
        $students = $this->studentsForReport($user);
        $totalSessions = $this->countSessions($scope['sql'], $scope['params']);
        $attendance = $this->attendanceCounts($scope['sql'], $scope['params'], $user);

        $report = array_map(function (array $student) use ($attendance, $totalSessions): array {
            $attended = (int) ($attendance[$student['id']] ?? 0);

            return [
                'full_name' => $student['full_name'],
                'student_no' => $student['student_no'],
                'attended' => $attended,
                'total_sessions' => $totalSessions,
                'percentage' => AttendanceCalculator::percentage($attended, $totalSessions),
            ];
        }, $students);

        return ['report' => $report];
    }

    private function sessionScope(array $user): array
    {
        if ($user['role'] !== 'teacher') {
            return ['sql' => '1 = 1', 'params' => []];
        }

        $statement = $this->db->prepare('SELECT id FROM teachers WHERE user_id = ?');
        $statement->execute([$user['id']]);
        $teacherId = $statement->fetchColumn();

        return ['sql' => 'x.teacher_id = ?', 'params' => [(int) $teacherId]];
    }

    private function studentsForReport(array $user): array
    {
        $sql = 'SELECT s.id, s.student_no, u.full_name
                FROM students s
                JOIN users u ON u.id = s.user_id
                WHERE u.status = \'active\'';
        $params = [];

        if ($user['role'] === 'student') {
            $sql .= ' AND s.user_id = ?';
            $params[] = $user['id'];
        }

        $sql .= ' ORDER BY u.full_name';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function countSessions(string $scopeSql, array $scopeParams): int
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM attendance_sessions x WHERE {$scopeSql}");
        $statement->execute($scopeParams);

        return (int) $statement->fetchColumn();
    }

    private function attendanceCounts(string $scopeSql, array $scopeParams, array $user): array
    {
        $sql = "SELECT a.student_id, COUNT(*) AS attended
                FROM attendance a
                JOIN attendance_sessions x ON x.id = a.session_id
                JOIN students s ON s.id = a.student_id
                WHERE a.status IN ('present', 'late') AND {$scopeSql}";
        $params = $scopeParams;

        if ($user['role'] === 'student') {
            $sql .= ' AND s.user_id = ?';
            $params[] = $user['id'];
        }

        $sql .= ' GROUP BY a.student_id';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return array_column($statement->fetchAll(), 'attended', 'student_id');
    }
}

<?php

namespace App\Services;

use App\Helpers\HttpException;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;

final class AuthenticationService
{
    private const TOKEN_LIFETIME_HOURS = 12;

    public function __construct(private \PDO $db)
    {
    }

    public function register(array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $role = (string) ($input['role'] ?? '');
        $identifier = trim((string) ($input['identifier'] ?? ''));
        $submittedCodes = $input['subject_codes'] ?? ($input['subject_code'] ?? []);
        if (!is_array($submittedCodes)) {
            $submittedCodes = preg_split('/[\s,]+/', (string) $submittedCodes, -1, PREG_SPLIT_NO_EMPTY);
        }
        $subjectCodes = array_values(array_unique(array_filter(array_map(
            static fn ($code): string => strtoupper(trim((string) $code)),
            $submittedCodes
        ))));
        $className = strtoupper(trim((string) ($input['class_name'] ?? '')));

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)
            || $fullName === ''
            || strlen($fullName) > 120
            || strlen($password) < 6
            || !in_array($role, ['student', 'teacher'], true)) {
            throw new HttpException('Enter a valid name, username, 6+ character password, and role.', 422);
        }

        try {
            $this->db->beginTransaction();

            $subjects = [];
            $yearLevel = null;
            if ($role === 'teacher') {
                $classNames = array_values(array_unique(array_filter(array_map(
                    static fn (string $value): string => trim($value),
                    explode(',', $className)
                ))));
                if (!$classNames || strlen(implode(',', $classNames)) > 30) {
                    throw new HttpException('Enter one or more valid teacher classes, for example 3IT,4IT.', 422);
                }
                $subjectModel = new Subject($this->db);
                $availableSubjects = [];
                foreach ($classNames as $teacherClass) {
                    if (!preg_match('/^([1-9])IT$/', $teacherClass)) {
                        throw new HttpException('Use class values such as 3IT,4IT separated by commas.', 422);
                    }
                    $classSubjects = $subjectModel->registrationSubjectsForClass($teacherClass);
                    if (!$classSubjects) {
                        throw new HttpException("No subjects are configured for class {$teacherClass}.", 422);
                    }
                    foreach ($classSubjects as $classSubject) {
                        $availableSubjects[$classSubject['code']] = $classSubject;
                    }
                }
                $className = implode(',', $classNames);
                if (!$subjectCodes) {
                    throw new HttpException('Select at least one subject.', 422);
                }
                foreach ($subjectCodes as $subjectCode) {
                    $subject = $availableSubjects[$subjectCode] ?? null;
                    if (!$subject) {
                        throw new HttpException("Subject {$subjectCode} is not available for classes {$className}.", 422);
                    }
                    $subjects[] = $subject;
                }
            } else {
                $identifier = strtoupper($identifier);
                if (!preg_match('/^([1-9])IT[0-9]+$/', $identifier, $matches)) {
                    throw new HttpException('Enter a valid student roll number, for example 4IT15.', 422);
                }
                $yearLevel = (int) $matches[1];
                $className = $matches[1] . 'IT';
            }

            $userId = $this->users()->create(
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $fullName,
                $role,
                'pending'
            );

            if ($role === 'student') {
                (new Student($this->db))->create($userId, $identifier, $className, $yearLevel);
            } else {
                (new Teacher($this->db))->create($userId, $className, $subjects);
            }

            $this->db->commit();
        } catch (\PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new HttpException('Username or identifier is already in use.', 422);
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return ['message' => 'Registration submitted for administrator approval.'];
    }

    public function login(array $input): array
    {
        $account = $this->users()->byUsername(trim((string) ($input['username'] ?? '')));

        if (!$account || !password_verify((string) ($input['password'] ?? ''), $account['password_hash'])) {
            throw new HttpException('Invalid username or password.', 401);
        }

        if ($account['status'] !== 'active') {
            $message = $account['status'] === 'pending'
                ? 'Your account is pending administrator approval.'
                : 'Your account is disabled.';
            throw new HttpException($message, 403);
        }

        if ($account['role'] === 'student') {
            $this->verifyStudentDevice((int) $account['id'], trim((string) ($input['device_uuid'] ?? '')));
        }

        $user = $this->safeUser($account);
        $token = bin2hex(random_bytes(32));
        $this->issueApiToken((int) $account['id'], $account['role'], $token);
        $_SESSION['user'] = $user;

        return ['message' => 'Logged in', 'user' => $user, 'token' => $token];
    }

    public function current(): array
    {
        $token = $this->bearerToken();
        if ($token !== null) {
            $statement = $this->db->prepare(
                "SELECT u.id, u.username, u.full_name, u.role
                 FROM api_tokens t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.token = ? AND t.expires_at > NOW() AND u.status = 'active'"
            );
            $statement->execute([$token]);
            $user = $statement->fetch();
            if ($user) {
                return $this->safeUser($user);
            }

            throw new HttpException('Authentication required.', 401);
        }

        if (!empty($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        throw new HttpException('Authentication required.', 401);
    }

    public function role(string $role): array
    {
        $user = $this->current();
        if ($user['role'] !== $role) {
            throw new HttpException('Forbidden.', 403);
        }

        return $user;
    }

    public function logout(): array
    {
        // A browser always sends its own Bearer token.  Revoke only that token so
        // signing out on one teacher device cannot affect the teacher's other devices.
        $token = $this->bearerToken();
        if ($token !== null) {
            $this->db->prepare('DELETE FROM api_tokens WHERE token = ?')->execute([$token]);
        }

        $_SESSION = [];
        if (session_id() !== '') {
            session_destroy();
        }

        return ['message' => 'Logged out'];
    }

    private function users(): User
    {
        return new User($this->db);
    }

    private function verifyStudentDevice(int $userId, string $deviceUuid): void
    {
        if ($deviceUuid === '') {
            throw new HttpException('Device identification is required.', 422);
        }

        $student = (new Student($this->db))->byUser($userId);
        if (!$student) {
            throw new HttpException('Student account not found.', 403);
        }
        if ($student['device_uuid'] && $student['device_uuid'] !== $deviceUuid) {
            throw new HttpException('This account is registered on another device.', 403);
        }
        if (!$student['device_uuid']) {
            (new Student($this->db))->setDevice((int) $student['id'], $deviceUuid);
        }
    }

    private function issueApiToken(int $userId, string $role, string $token): void
    {
        // Students have one registered device and receive one active token.  Teachers
        // intentionally keep a token per successful device login.
        if ($role === 'student') {
            $this->db->prepare('DELETE FROM api_tokens WHERE user_id = ?')->execute([$userId]);
        }

        $this->db->prepare(
            'INSERT INTO api_tokens(user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . self::TOKEN_LIFETIME_HOURS . ' HOUR))'
        )->execute([$userId, $token]);
    }

    private function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        return preg_match('/^Bearer\s+(.+)$/i', $header, $matches) ? $matches[1] : null;
    }

    private function safeUser(array $user): array
    {
        $safe = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];

        if ($user['role'] === 'teacher') {
            $teacherModel = new Teacher($this->db);
            $teacher = $teacherModel->byUser((int) $user['id']);
            if ($teacher) {
                $subjects = array_map(static function (array $subject): array {
                    $subject['id'] = (int) $subject['id'];
                    $subject['year_level'] = (int) $subject['year_level'];
                    return $subject;
                }, $teacherModel->subjectsByUser((int) $user['id']));
                $safe['subjects'] = $subjects;
                $safe['class_name'] = $teacher['class_name'];
                $safe['year_levels'] = array_values(array_unique(array_column($subjects, 'year_level')));
                // Retain the old fields for older clients while the array is authoritative.
                $safe['subject'] = $subjects[0] ?? null;
                $safe['year_level'] = $subjects[0]['year_level'] ?? null;
                $safe['welcome_message'] = 'Welcome, ' . $user['full_name'] . '!';
            }
        } elseif ($user['role'] === 'student') {
            $student = (new Student($this->db))->byUser((int) $user['id']);
            if ($student) {
                $safe['class_name'] = $student['class_name'];
                $safe['year_level'] = (int) $student['year_level'];
            }
        }

        return $safe;
    }
}

<?php namespace App\Models;
final class User extends BaseModel
{
    public function byUsername(string $username): ?array
    {
        return $this->one('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public function byId(int $id): ?array
    {
        return $this->one('SELECT id, username, full_name, role, status FROM users WHERE id = ?', [$id]);
    }

    public function create(string $username, string $hash, string $name, string $role, string $status): int
    {
        $statement = $this->db->prepare('INSERT INTO users(username, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$username, $hash, $name, $role, $status]);

        return (int) $this->db->lastInsertId();
    }

    public function list(): array
    {
        return $this->all('SELECT id, full_name, username, role, status, created_at FROM users ORDER BY created_at DESC');
    }

    public function setStatus(int $id, string $status): bool
    {
        $statement = $this->db->prepare("UPDATE users SET status = ? WHERE id = ? AND role IN ('student', 'teacher')");
        $statement->execute([$status, $id]);

        return $statement->rowCount() > 0;
    }

    public function activate(int $id): bool
    {
        return $this->setStatus($id, 'active');
    }
}

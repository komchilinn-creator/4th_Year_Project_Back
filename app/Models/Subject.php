<?php namespace App\Models;
final class Subject {
    public function __construct(private \PDO $db) {}
    public function allSubjects(): array { return $this->db->query('SELECT id,code,name,year_level FROM subjects ORDER BY year_level,code')->fetchAll(); }
    public function byRegistrationCode(string $code): ?array { $s=$this->db->prepare('SELECT id,code,name,year_level FROM subjects WHERE UPPER(code)=? AND teacher_registration_enabled=1'); $s->execute([strtoupper($code)]); return $s->fetch() ?: null; }
    public function exists(int $id): bool { $s=$this->db->prepare('SELECT id FROM subjects WHERE id=?'); $s->execute([$id]); return (bool)$s->fetch(); }
    public function save(string $code,string $name): void { $this->db->prepare('INSERT INTO subjects(code,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute([$code,$name]); }
}

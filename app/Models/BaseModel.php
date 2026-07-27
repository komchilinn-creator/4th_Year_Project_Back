<?php namespace App\Models;
abstract class BaseModel { public function __construct(protected \PDO $db) {} protected function all(string $sql,array $params=[]): array {$s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll();} protected function one(string $sql,array $params=[]): ?array {$rows=$this->all($sql,$params);return $rows[0]??null;} }

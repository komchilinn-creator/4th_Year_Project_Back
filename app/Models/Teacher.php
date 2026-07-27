<?php namespace App\Models;
final class Teacher extends BaseModel { public function create(int $uid,?string $no):void{$this->db->prepare('INSERT INTO teachers(user_id,staff_no) VALUES(?,?)')->execute([$uid,$no?:null]);} public function byUser(int $uid):?array{return $this->one('SELECT * FROM teachers WHERE user_id=?',[$uid]);} }

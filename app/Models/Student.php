<?php namespace App\Models;
final class Student extends BaseModel {
    public function create(int $uid,?string $no,string $className,int $yearLevel):void{$this->db->prepare('INSERT INTO students(user_id,student_no,class_name,year_level) VALUES(?,?,?,?)')->execute([$uid,$no?:null,$className,$yearLevel]);}
    public function byUser(int $uid):?array{return $this->one('SELECT * FROM students WHERE user_id=?',[$uid]);}
    public function resetDevice(int $uid):void{$this->db->prepare('UPDATE students SET device_uuid=NULL WHERE user_id=?')->execute([$uid]);}
    public function setDevice(int $id,string $uuid):void{$this->db->prepare('UPDATE students SET device_uuid=? WHERE id=?')->execute([$uuid,$id]);}
    public function profile(int $uid):?array{return $this->one('SELECT u.full_name,u.username,s.student_no,s.class_name,s.year_level FROM users u JOIN students s ON s.user_id=u.id WHERE u.id=?',[$uid]);}
}

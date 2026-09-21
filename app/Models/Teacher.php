<?php namespace App\Models;
final class Teacher extends BaseModel {
    public function create(int $uid,?string $no,array $subject):void{$this->db->prepare('INSERT INTO teachers(user_id,staff_no,subject_id,subject_code,year_level) VALUES(?,?,?,?,?)')->execute([$uid,$no?:null,$subject['id'],$subject['code'],$subject['year_level']]);}
    public function byUser(int $uid):?array{return $this->one('SELECT t.*,s.name subject_name,s.code subject_code FROM teachers t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.user_id=?',[$uid]);}
}

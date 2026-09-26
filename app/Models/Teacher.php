<?php namespace App\Models;
final class Teacher extends BaseModel {
    public function create(int $uid,string $className,array $subjects):void {
        $primary=$subjects[0];
        $this->db->prepare('INSERT INTO teachers(user_id,class_name,subject_id,subject_code,year_level) VALUES(?,?,?,?,?)')->execute([$uid,$className,$primary['id'],$primary['code'],$primary['year_level']]);
        $teacherId=(int)$this->db->lastInsertId();
        $link=$this->db->prepare('INSERT INTO teacher_subjects(teacher_id,subject_id) VALUES(?,?)');
        foreach($subjects as $subject){$link->execute([$teacherId,$subject['id']]);}
    }
    public function byUser(int $uid):?array{return $this->one('SELECT t.* FROM teachers t WHERE t.user_id=?',[$uid]);}
    public function subjectsByUser(int $uid):array{return $this->all('SELECT s.id,s.code,s.name,s.year_level FROM teachers t JOIN teacher_subjects ts ON ts.teacher_id=t.id JOIN subjects s ON s.id=ts.subject_id WHERE t.user_id=? AND s.year_level IS NOT NULL ORDER BY s.year_level,s.code',[$uid]);}
    public function subjectForTeacher(int $teacherId,int $subjectId):?array{return $this->one('SELECT s.id,s.code,s.name,s.year_level FROM teacher_subjects ts JOIN subjects s ON s.id=ts.subject_id WHERE ts.teacher_id=? AND s.id=?',[$teacherId,$subjectId]);}
}

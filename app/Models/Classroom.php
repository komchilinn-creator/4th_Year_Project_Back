<?php namespace App\Models; final class Classroom extends BaseModel { public function allRooms():array{return $this->all('SELECT id,name FROM classrooms ORDER BY name');} }

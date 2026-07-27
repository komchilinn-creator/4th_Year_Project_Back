<?php namespace App\Controllers; final class TeacherController extends BaseController { public function dashboard():array{$u=$this->auth()->role('teacher');return ['user'=>$u];} }

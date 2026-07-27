<?php namespace App\Controllers;
use App\Services\AttendanceService;
final class AttendanceController extends BaseController { public function create():array{$u=$this->auth()->role('teacher');return (new AttendanceService($this->db))->create($u['id'],$this->input);} public function live():array{$u=$this->auth()->role('teacher');return (new AttendanceService($this->db))->live($u['id']);} public function manual():array{$u=$this->auth()->role('teacher');return (new AttendanceService($this->db))->manual($u['id'],$this->input);} }

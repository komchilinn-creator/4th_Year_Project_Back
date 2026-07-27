<?php namespace App\Controllers;
abstract class BaseController { public function __construct(protected \PDO $db, protected array $input=[]) {} protected function auth(): \App\Services\AuthenticationService{return new \App\Services\AuthenticationService($this->db);} }

<?php namespace App\Helpers;
final class HttpException extends \RuntimeException {}
final class ApiResponse { public static function send(array $data, int $status=200): never { http_response_code($status); echo json_encode(['ok'=>$status < 400] + $data); exit; } }

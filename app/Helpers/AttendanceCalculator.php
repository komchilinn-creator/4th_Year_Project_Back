<?php namespace App\Helpers;
final class AttendanceCalculator { public static function percentage(int $attended,int $conducted):float{return $conducted>0?round($attended*100/$conducted,2):0.0;} }

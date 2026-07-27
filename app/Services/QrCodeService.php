<?php namespace App\Services; final class QrCodeService { public function token():string{return bin2hex(random_bytes(24));} }

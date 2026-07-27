<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$cfg = require __DIR__ . '/../config/database.php';
try {
    $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $error) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Database unavailable. Import database/schema.sql first.']); exit; }
$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? trim(str_replace('/4th_Year_Pj_Backend/public/', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');
if (!$action || $action === 'index.php') $action = 'health';
function out(array $data, int $status=200): never { http_response_code($status); echo json_encode(['ok'=>$status<400] + $data); exit; }
function input(string $key, mixed $default=null): mixed { global $body; return $body[$key] ?? $default; }
function sessionUser(): array {
  global $pdo;
  if (!empty($_SESSION['user'])) return $_SESSION['user'];
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
  if (!$header && function_exists('getallheaders')) $header = getallheaders()['Authorization'] ?? '';
  if (preg_match('/^Bearer\\s+(.+)$/i', $header, $matches)) {
    $statement = $pdo->prepare('SELECT u.id,u.username,u.full_name,u.role FROM api_tokens t JOIN users u ON u.id=t.user_id WHERE t.token=? AND t.expires_at>NOW() AND u.status=\'active\'');
    $statement->execute([$matches[1]]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);
    if ($user) return ['id'=>(int)$user['id'], 'username'=>$user['username'], 'full_name'=>$user['full_name'], 'role'=>$user['role']];
  }
  out(['message'=>'Authentication required'],401);
}
function role(string $needed): array { $user=sessionUser(); if ($user['role'] !== $needed) out(['message'=>'Forbidden'],403); return $user; }
function query(PDO $pdo,string $sql,array $args=[]): array { $statement=$pdo->prepare($sql); $statement->execute($args); return $statement->fetchAll(PDO::FETCH_ASSOC); }

if ($action === 'health') out(['message'=>'QR Attendance API is running']);
if ($action === 'logout') { session_destroy(); out(['message'=>'Logged out']); }
if ($action === 'me') out(['user'=>sessionUser()]);

if ($action === 'register') {
  $username=trim((string)input('username')); $password=(string)input('password'); $name=trim((string)input('full_name')); $userRole=input('role');
  if (!$username || !$name || strlen($password)<6 || !in_array($userRole,['student','teacher'],true)) out(['message'=>'Enter name, username, a 6+ character password, and role'],422);
  try { $pdo->beginTransaction(); $pdo->prepare('INSERT INTO users(username,password_hash,full_name,role,status) VALUES(?,?,?,?,?)')->execute([$username,password_hash($password,PASSWORD_DEFAULT),$name,$userRole,$userRole==='teacher'?'active':'pending']); $id=(int)$pdo->lastInsertId(); $table=$userRole==='student'?'students':'teachers'; $column=$userRole==='student'?'student_no':'staff_no'; $pdo->prepare("INSERT INTO {$table}(user_id,{$column}) VALUES(?,?)")->execute([$id,input('identifier') ?: null]); $pdo->commit(); out(['message'=>$userRole==='student'?'Registration submitted for approval':'Teacher account created']); } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); out(['message'=>'Username or identifier is already in use'],422); }
}
if ($action === 'login') {
  $statement=$pdo->prepare('SELECT * FROM users WHERE username=?'); $statement->execute([input('username')]); $account=$statement->fetch(PDO::FETCH_ASSOC);
  if (!$account || !password_verify((string)input('password'),$account['password_hash'])) out(['message'=>'Invalid username or password'],401);
  if ($account['status'] !== 'active') out(['message'=>$account['status']==='pending'?'Your account is pending administrator approval.':'Your account is disabled.'],403);
  if ($account['role']==='student') { $student=query($pdo,'SELECT * FROM students WHERE user_id=?',[$account['id']])[0]; $uuid=(string)input('device_uuid'); if ($student['device_uuid'] && $student['device_uuid']!==$uuid) out(['message'=>'This account is registered on another device.'],403); if(!$student['device_uuid']) $pdo->prepare('UPDATE students SET device_uuid=? WHERE id=?')->execute([$uuid,$student['id']]); }
  $safe=['id'=>(int)$account['id'],'username'=>$account['username'],'full_name'=>$account['full_name'],'role'=>$account['role']];
  $token = bin2hex(random_bytes(32));
  $pdo->prepare('DELETE FROM api_tokens WHERE user_id=?')->execute([$account['id']]);
  $pdo->prepare('INSERT INTO api_tokens(user_id,token,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 12 HOUR))')->execute([$account['id'],$token]);
  $_SESSION['user']=$safe; out(['message'=>'Logged in','user'=>$safe,'token'=>$token]);
}
if ($action === 'student/profile') { $user=role('student'); out(['profile'=>query($pdo,'SELECT u.full_name,u.username,s.student_no FROM users u JOIN students s ON s.user_id=u.id WHERE u.id=?',[$user['id']])[0]]); }
if ($action === 'student/attendance') { $user=role('student'); out(['attendance'=>query($pdo,'SELECT a.status,a.recorded_at,x.title,sub.code,sub.name FROM attendance a JOIN students s ON s.id=a.student_id JOIN attendance_sessions x ON x.id=a.session_id JOIN subjects sub ON sub.id=x.subject_id WHERE s.user_id=? ORDER BY a.recorded_at DESC',[$user['id']])]); }
if ($action === 'student/schedule') { role('student'); out(['schedules'=>query($pdo,'SELECT sub.code,sub.name,c.name classroom,sc.day_of_week,sc.start_time,sc.end_time FROM schedules sc JOIN subjects sub ON sub.id=sc.subject_id LEFT JOIN classrooms c ON c.id=sc.classroom_id ORDER BY sc.day_of_week,sc.start_time')]); }
if ($action === 'student/scan') { $user=role('student'); $token=trim((string)input('token')); $qr=query($pdo,'SELECT q.session_id FROM qr_codes q JOIN attendance_sessions x ON x.id=q.session_id WHERE q.token=? AND q.active=1 AND x.active=1 AND q.expires_at>NOW()',[$token]); if(!$qr)out(['message'=>'Invalid or expired QR code'],422); $student=query($pdo,'SELECT id FROM students WHERE user_id=?',[$user['id']])[0]; try{$pdo->prepare("INSERT INTO attendance(session_id,student_id,status) VALUES(?,?,'present')")->execute([$qr[0]['session_id'],$student['id']]);out(['message'=>'Attendance recorded']);}catch(Throwable $e){out(['message'=>'Attendance has already been recorded'],422);} }
if ($action === 'attendance/create') { $user=role('teacher'); $teacher=query($pdo,'SELECT id FROM teachers WHERE user_id=?',[$user['id']])[0]; $minutes=max(1,(int)input('minutes',10)); $subject=(int)input('subject_id'); if(!$subject)out(['message'=>'A subject ID is required'],422); $pdo->prepare('UPDATE attendance_sessions SET active=0 WHERE teacher_id=? AND active=1')->execute([$teacher['id']]); $pdo->prepare('INSERT INTO attendance_sessions(teacher_id,subject_id,title,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))')->execute([$teacher['id'],$subject,trim((string)input('title','Attendance session')),$minutes]); $session=(int)$pdo->lastInsertId(); $token=bin2hex(random_bytes(18)); $pdo->prepare('INSERT INTO qr_codes(session_id,token,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))')->execute([$session,$token,$minutes]); out(['session_id'=>$session,'token'=>$token,'expires_at'=>date(DATE_ATOM,time()+$minutes*60)]); }
if ($action === 'attendance/live') { $user=role('teacher'); $teacher=query($pdo,'SELECT id FROM teachers WHERE user_id=?',[$user['id']])[0]; $session=query($pdo,'SELECT x.id,x.title,x.expires_at,sub.name subject FROM attendance_sessions x JOIN subjects sub ON sub.id=x.subject_id WHERE x.teacher_id=? AND x.active=1 ORDER BY x.id DESC LIMIT 1',[$teacher['id']]); if(!$session)out(['session'=>null,'attendance'=>[]]); out(['session'=>$session[0],'attendance'=>query($pdo,'SELECT u.full_name,s.student_no,a.status,a.recorded_at FROM attendance a JOIN students s ON s.id=a.student_id JOIN users u ON u.id=s.user_id WHERE a.session_id=? ORDER BY a.recorded_at DESC',[$session[0]['id']])]); }
if ($action === 'reports/monthly') { $user=sessionUser(); $where=$user['role']==='student'?'WHERE s.user_id=?':''; out(['report'=>query($pdo,"SELECT u.full_name,s.student_no,COUNT(a.id) attended FROM students s JOIN users u ON u.id=s.user_id LEFT JOIN attendance a ON a.student_id=s.id {$where} GROUP BY s.id ORDER BY u.full_name",$where?[$user['id']]:[])]); }
if ($action === 'admin/users') { role('admin'); out(['users'=>query($pdo,'SELECT id,full_name,username,role,status,created_at FROM users ORDER BY created_at DESC')]); }
if ($action === 'admin/verify') { role('admin'); $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND role IN ('student','teacher')")->execute([(int)input('user_id')]); out(['message'=>'Account verified']); }
if ($action === 'admin/device/reset') { role('admin'); $pdo->prepare('UPDATE students SET device_uuid=NULL WHERE user_id=?')->execute([(int)input('user_id')]); out(['message'=>'Device registration reset']); }
if ($action === 'admin/subject') { role('admin'); $pdo->prepare('INSERT INTO subjects(code,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute([input('code'),input('name')]); out(['message'=>'Subject saved']); }
if ($action === 'subjects') { sessionUser(); out(['subjects'=>query($pdo,'SELECT id,code,name FROM subjects ORDER BY code')]); }
out(['message'=>'Endpoint not found'],404);

<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
function db(): PDO {
 static $pdo;
 if ($pdo instanceof PDO) return $pdo;
 if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH), 0755, true);
 $pdo = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;');
 $pdo->exec("CREATE TABLE IF NOT EXISTS licenses(id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT UNIQUE NOT NULL,duration_days INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'unused',device_uuid TEXT,device_name TEXT,ios_version TEXT,app_version TEXT,bundle_id TEXT,activated_at TEXT,expires_at TEXT,last_seen_at TEXT,created_at TEXT NOT NULL,notes TEXT DEFAULT '')");
 $pdo->exec("CREATE TABLE IF NOT EXISTS api_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,endpoint TEXT NOT NULL,code TEXT,device_uuid TEXT,ip TEXT,http_status INTEGER,response_status TEXT,created_at TEXT NOT NULL)");
 return $pdo;
}
function now(): string { return date('Y-m-d H:i:s'); }
function json_input(): array { $raw=file_get_contents('php://input'); $v=json_decode($raw?:'{}',true); return is_array($v)?$v:[]; }
function out(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function api_auth(): void { $key=$_SERVER['HTTP_X_GPS_API_KEY']??''; if (!hash_equals(API_KEY,$key)) out(['status'=>'error','message'=>'unauthorized'],401); }
function clean_code(string $code): string { return strtoupper(trim($code)); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR']??''; }
function log_api(string $endpoint,?string $code,?string $uuid,int $http,string $status): void { $s=db()->prepare('INSERT INTO api_logs(endpoint,code,device_uuid,ip,http_status,response_status,created_at) VALUES(?,?,?,?,?,?,?)'); $s->execute([$endpoint,$code,$uuid,client_ip(),$http,$status,now()]); }
function license_payload(array $l): array { return ['code'=>$l['code'],'status'=>$l['status'],'device_uuid'=>$l['device_uuid'],'activated_at'=>$l['activated_at'],'expires_at'=>$l['expires_at'],'last_seen_at'=>$l['last_seen_at']]; }
function require_fields(array $d,array $fields): void { foreach($fields as $f) if(!isset($d[$f])||trim((string)$d[$f])==='') out(['status'=>'error','message'=>"missing_$f"],422); }

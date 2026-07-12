<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Storage directory is unavailable');
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
    $pdo->exec("CREATE TABLE IF NOT EXISTS licenses(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        duration_days INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'unused',
        device_uuid TEXT, device_name TEXT, ios_version TEXT, app_version TEXT, bundle_id TEXT,
        activated_at TEXT, expires_at TEXT, last_seen_at TEXT,
        created_at TEXT NOT NULL, notes TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_logs(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        endpoint TEXT NOT NULL, code TEXT, device_uuid TEXT, ip TEXT,
        http_status INTEGER, response_status TEXT, created_at TEXT NOT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_licenses_code ON licenses(code)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logs_created ON api_logs(created_at)');
    return $pdo;
}

function now(): string { return date('Y-m-d H:i:s'); }
function json_input(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(['status'=>'error','message'=>'method_not_allowed'],405);
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > 32768) out(['status'=>'error','message'=>'payload_too_large'],413);
    $value = json_decode($raw, true);
    if (!is_array($value)) out(['status'=>'error','message'=>'invalid_json'],400);
    return $value;
}
function out(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function api_auth(): void {
    $expected = api_key();
    $received = (string)($_SERVER['HTTP_X_GPS_API_KEY'] ?? '');
    if ($expected === '' || str_starts_with($expected, 'CHANGE_ME_') || !hash_equals($expected, $received)) {
        out(['status'=>'error','message'=>'unauthorized'],401);
    }
}
function clean_code(string $code): string { return strtoupper(trim($code)); }
function client_ip(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? ''); }
function log_api(string $endpoint, ?string $code, ?string $uuid, int $http, string $status): void {
    $s=db()->prepare('INSERT INTO api_logs(endpoint,code,device_uuid,ip,http_status,response_status,created_at) VALUES(?,?,?,?,?,?,?)');
    $s->execute([$endpoint,$code,$uuid,client_ip(),$http,$status,now()]);
}
function license_payload(array $l): array {
    return [
        'code'=>$l['code'], 'status'=>$l['status'], 'duration_days'=>(int)$l['duration_days'],
        'device_uuid'=>$l['device_uuid'], 'activated_at'=>$l['activated_at'],
        'expires_at'=>$l['expires_at'], 'last_seen_at'=>$l['last_seen_at'],
        'server_time'=>now(),
    ];
}
function require_fields(array $d, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($d[$field]) || trim((string)$d[$field]) === '') out(['status'=>'error','message'=>"missing_$field"],422);
    }
}
function admin_session_start(): void {
    session_name(SESSION_NAME);
    session_set_cookie_params(['httponly'=>true,'secure'=>isset($_SERVER['HTTPS']),'samesite'=>'Strict','path'=>'/']);
    session_start();
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('CSRF token mismatch');
    }
}

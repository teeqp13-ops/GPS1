<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const DB_FILE = __DIR__ . '/../storage/gps.sqlite';

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        respond(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
}

function requireApiKey(): void {
    $expected = getenv('GPS_API_KEY') ?: '';
    $received = $_SERVER['HTTP_X_GPS_API_KEY'] ?? '';
    if ($expected === '' || !hash_equals($expected, $received)) {
        respond(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }
}

function input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(['status' => 'error', 'message' => 'Invalid JSON'], 400);
    }
    return $data;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $dir = dirname(DB_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        respond(['status' => 'error', 'message' => 'Storage unavailable'], 500);
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS licenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT "unused",
        duration_days INTEGER NOT NULL DEFAULT 30,
        device_uuid TEXT,
        activated_at TEXT,
        expires_at TEXT,
        last_seen_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS request_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT NOT NULL,
        code TEXT,
        device_uuid TEXT,
        ip TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    return $pdo;
}

function requiredString(array $data, string $key): string {
    $value = trim((string)($data[$key] ?? ''));
    if ($value === '') respond(['status' => 'error', 'message' => "$key is required"], 422);
    return $value;
}

function logRequest(string $action, string $code, string $deviceUuid): void {
    $stmt = db()->prepare('INSERT INTO request_logs(action, code, device_uuid, ip) VALUES(?,?,?,?)');
    $stmt->execute([$action, $code, $deviceUuid, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function licenseByCode(string $code): ?array {
    $stmt = db()->prepare('SELECT * FROM licenses WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function normalizeLicense(array $license): array {
    $expired = !empty($license['expires_at']) && strtotime($license['expires_at']) <= time();
    return [
        'code' => $license['code'],
        'status' => $expired ? 'expired' : $license['status'],
        'device_uuid' => $license['device_uuid'],
        'activated_at' => $license['activated_at'],
        'expires_at' => $license['expires_at'],
        'last_seen_at' => $license['last_seen_at'],
        'valid' => !$expired && $license['status'] === 'active',
    ];
}

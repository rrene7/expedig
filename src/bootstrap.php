<?php
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('Falta config.php. Copia config.example.php como config.php y ajusta la conexión.');
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'America/Panama');

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['db'];
    $dsn = "mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function json_response($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function audit(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void {
    $stmt = db()->prepare('INSERT INTO audit_logs(action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?)');
    $stmt->execute([$action,$entityType,$entityId,$details,$_SERVER['REMOTE_ADDR'] ?? null]);
}

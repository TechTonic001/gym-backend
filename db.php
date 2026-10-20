<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body(): array
{
    try {
        $data = json_decode(
            file_get_contents('php://input'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException) {
        json_response(['success' => false, 'message' => 'Invalid JSON'], 400);
    }

    if (!is_array($data)) {
        json_response(['success' => false, 'message' => 'JSON object required'], 400);
    }

    return $data;
}

function require_post_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST, OPTIONS');
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'gym_app';
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');

if ($dbUser === false || $dbUser === '' || $dbPassword === false) {
    error_log('Database connection configuration is missing.');
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

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

function read_request_body(): array
{
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));

    if (
        $contentType === 'application/x-www-form-urlencoded'
        || $contentType === 'multipart/form-data'
    ) {
        return $_POST;
    }

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

function require_auth_request(string $operation, array $requiredFields): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';

    if ($method === 'GET') {
        json_response([
            'success' => true,
            'message' => $operation . ' API is ready',
            'method' => 'POST',
            'required_fields' => $requiredFields,
        ]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST, OPTIONS');
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

$connectionStrings = [
    'mysql:host=127.0.0.1;dbname=gym_app;charset=utf8',
    'mysql:host=localhost;dbname=gym_app;charset=utf8',
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=gym_app;charset=utf8',
];
$lastConnectionException = null;

foreach ($connectionStrings as $dsn) {
    try {
        $pdo = new PDO(
            $dsn,
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $lastConnectionException = null;
        break;
    } catch (PDOException $exception) {
        $lastConnectionException = $exception;
        error_log('MySQL connection failed for ' . $dsn . ': ' . $exception->getMessage());
    }
}

if ($lastConnectionException !== null) {
    json_response([
        'success' => false,
        'message' => $lastConnectionException->getMessage(),
    ], 500);
}

function create_auth_token(int $userId, string $role): string
{
    $secret = getenv('JWT_SECRET');
    if ($secret === false && is_file(__DIR__ . '/config.local.php')) {
        $localConfig = require __DIR__ . '/config.local.php';
        $secret = is_array($localConfig) ? ($localConfig['jwt_secret'] ?? false) : false;
    }

    if (!is_string($secret) || strlen($secret) < 32) {
        error_log('JWT_SECRET must be configured with at least 32 characters.');
        json_response(['success' => false, 'message' => 'Service unavailable'], 500);
    }

    $now = time();
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'sub' => (string) $userId,
        'role' => $role,
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    $base64UrlEncode = static fn (string $value): string => rtrim(
        strtr(base64_encode($value), '+/', '-_'),
        '='
    );

    $unsignedToken = $base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
        . '.'
        . $base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
    $signature = hash_hmac('sha256', $unsignedToken, $secret, true);

    return $unsignedToken . '.' . $base64UrlEncode($signature);
}

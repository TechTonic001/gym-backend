<?php
declare(strict_types=1);

function load_local_env(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        error_log('Unable to read local environment file.');
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $key)) {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[-1] === '"')
                || ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $existingValue = getenv($key);
        if ($existingValue !== false) {
            $_ENV[$key] = $existingValue;
            continue;
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function environment_value(string $key): string|false
{
    return getenv($key) ?: ($_ENV[$key] ?? false);
}

load_local_env(__DIR__ . '/.env');

$allowedOrigins = [
    'https://form-fit-one.vercel.app',
    'http://localhost:4200',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
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

$databaseHost = environment_value('DB_HOST');
$databaseName = environment_value('DB_NAME');
$databaseUser = environment_value('DB_USER');
$databasePassword = environment_value('DB_PASS') ?: environment_value('DB_PASSWORD');
$databasePort = environment_value('DB_PORT') ?: '3306';
$runningOnVercel = environment_value('VERCEL') === '1';
$hasDatabaseEnvironment = $databaseHost !== false
    || $databaseName !== false
    || $databaseUser !== false
    || $databasePassword !== false;

if ($runningOnVercel && !$hasDatabaseEnvironment) {
    error_log('Database environment variables are required on Vercel.');
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

if ($hasDatabaseEnvironment) {
    if (
        !is_string($databaseHost) || $databaseHost === ''
        || !is_string($databaseName) || $databaseName === ''
        || !is_string($databaseUser) || $databaseUser === ''
        || !is_string($databasePassword) || $databasePassword === ''
        || !is_string($databasePort)
        || filter_var($databasePort, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]) === false
    ) {
        error_log('DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS must be configured correctly.');
        json_response(['success' => false, 'message' => 'Service unavailable'], 500);
    }

    $connectionStrings = [
        'mysql:host=' . $databaseHost
            . ';port=' . $databasePort
            . ';dbname=' . $databaseName
            . ';charset=utf8mb4',
    ];
    $connectionUser = $databaseUser;
    $connectionPassword = $databasePassword;
} else {
    $connectionStrings = [
        'mysql:host=127.0.0.1;dbname=gym_app;charset=utf8',
        'mysql:host=localhost;dbname=gym_app;charset=utf8',
        'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=gym_app;charset=utf8',
    ];
    $connectionUser = 'root';
    $connectionPassword = '';
}

$lastConnectionException = null;

foreach ($connectionStrings as $dsn) {
    try {
        $pdo = new PDO(
            $dsn,
            $connectionUser,
            $connectionPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );
        $lastConnectionException = null;
        break;
    } catch (PDOException $exception) {
        $lastConnectionException = $exception;
        error_log('MySQL connection failed: ' . $exception->getMessage());
    }
}

if ($lastConnectionException !== null) {
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

function create_auth_token(int $userId, string $role): string
{
    $secret = environment_value('JWT_SECRET');
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

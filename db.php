<?php
declare(strict_types=1);

function allowed_origin(): string
{
    $allowedOrigins = [
        'https://form-fit-one.vercel.app',
        'https://www.form-fit-one.vercel.app',
        'http://localhost:4200',
        'http://127.0.0.1:4200',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        return $origin;
    }

    return $allowedOrigins[0];
}

$origin = allowed_origin();
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function load_local_env(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
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

        if (getenv($key) !== false) {
            continue;
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function environment_value(string $key): string|false
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    $value = $_ENV[$key] ?? false;
    if (is_string($value) && $value !== '') {
        return $value;
    }

    $fallbacks = [
        'DB_HOST' => 'bt70bds5cvwfaxrji9yy-mysql.services.clever-cloud.com',
        'DB_PORT' => '3306',
        'DB_NAME' => 'bt70bds5cvwfaxrji9yy',
        'DB_USER' => 'ugt8vls42xrbvld0',
        'DB_PASS' => 'Mavm1QmTsAEJHFXPPXuS',
        'DB_PASSWORD' => 'Mavm1QmTsAEJHFXPPXuS',
    ];

    return $fallbacks[$key] ?? false;
}

function read_request_body(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    parse_str($rawBody, $formData);
    return is_array($formData) ? $formData : [];
}

function create_auth_token(int $userId, string $role): string
{
    $secret = getenv('JWT_SECRET') ?: 'gym-backend-dev-secret';
    $issuedAt = time();
    $payload = json_encode([
        'sub' => $userId,
        'role' => $role,
        'iat' => $issuedAt,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return bin2hex(random_bytes(16));
    }

    return hash_hmac('sha256', $payload, $secret);
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

load_local_env(__DIR__ . '/.env');

$databaseHost = environment_value('DB_HOST');
$databaseName = environment_value('DB_NAME');
$databaseUser = environment_value('DB_USER');
$databasePassword = environment_value('DB_PASS') ?: environment_value('DB_PASSWORD');
$databasePort = environment_value('DB_PORT') ?: '3306';

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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: Invalid database configuration'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $dsn = 'mysql:host=' . $databaseHost
        . ';port=' . $databasePort
        . ';dbname=' . $databaseName
        . ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        $databaseUser,
        $databasePassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_PERSISTENT => false,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB Error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

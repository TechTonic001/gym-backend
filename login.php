<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput === false ? '' : $rawInput, true);
    $data = is_array($jsonData) ? $jsonData : $_POST;

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON or form payload']);
        exit;
    }

    $identifier = trim((string) ($data['email'] ?? $data['username'] ?? $data['identifier'] ?? $data['full_name'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($identifier === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    $statement = $pdo->prepare(
        'SELECT * FROM users WHERE email = ? OR username = ? OR full_name = ? LIMIT 1'
    );
    $statement->execute([$identifier, $identifier, $identifier]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    $storedHash = $user['password_hash'] ?? $user['password'] ?? '';
    $passwordMatches = is_string($storedHash)
        && (
            password_verify($password, $storedHash)
            || $password === $storedHash
            || strtolower($password) === strtolower($storedHash)
        );

    if (!$passwordMatches) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit;
    }

    $userId = (int) ($user['user_id'] ?? 0);
    $fullName = (string) ($user['full_name'] ?? '');
    $role = (string) ($user['role'] ?? 'member');
    $token = function_exists('create_auth_token') ? create_auth_token($userId, $role) : bin2hex(random_bytes(16));

    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Login successful',
        'token' => $token,
        'user_id' => $userId,
        'full_name' => $fullName,
        'role' => $role,
        'user' => $user,
        'data' => $user,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

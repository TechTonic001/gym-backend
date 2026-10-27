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

    $email = trim((string) ($data['email'] ?? $data['username_or_email'] ?? $data['identifier'] ?? $data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    $statement = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    $storedHash = $user['password_hash'] ?? $user['password'] ?? '';
    $passwordMatches = is_string($storedHash)
        && (
            password_verify($password, $storedHash)
            || $password === $storedHash
        );

    if (!$passwordMatches) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    $userId = (int) ($user['user_id'] ?? 0);
    $fullName = (string) ($user['full_name'] ?? '');
    $role = (string) ($user['role'] ?? 'member');
    $token = function_exists('create_auth_token') ? create_auth_token($userId, $role) : null;

    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'full_name' => $fullName,
        'role' => $role,
        'token' => $token,
        'user' => $user,
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

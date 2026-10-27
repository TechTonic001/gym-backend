<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    require_auth_request('Login', ['username_or_email', 'password']);
    $input = read_request_body();

    $rawIdentifier = $input['username_or_email'] ?? $input['identifier'] ?? $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!is_string($rawIdentifier) || !is_string($password)) {
        json_response(['success' => false, 'message' => 'Invalid email or password'], 400);
    }

    $identifier = trim($rawIdentifier);

    if (
        $identifier === ''
        || strlen($identifier) > 100
        || $password === ''
        || strlen($password) > 4096
    ) {
        json_response(['success' => false, 'message' => 'Invalid email or password'], 400);
    }

    $statement = $pdo->prepare(
        'SELECT user_id, full_name, role, password_hash
        FROM users
        WHERE email = :email OR username = :username
        LIMIT 1'
    );
    $statement->execute([
        'email' => $identifier,
        'username' => $identifier,
    ]);
    $user = $statement->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        json_response(['success' => false, 'message' => 'Invalid email or password'], 401);
    }

    $userId = (int) $user['user_id'];
    $role = (string) ($user['role'] ?? 'member');

    json_response([
        'success' => true,
        'user_id' => $userId,
        'full_name' => $user['full_name'],
        'role' => $role,
        'token' => create_auth_token($userId, $role),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

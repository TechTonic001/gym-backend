<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
        exit();
    }

    require_auth_request('Registration', ['full_name', 'username', 'email', 'password']);

    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput === false ? '' : $rawInput, true);
    $data = is_array($jsonData) ? $jsonData : $_POST;

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON or form payload']);
        exit();
    }

    $rawFullName = $data['full_name'] ?? $data['fullName'] ?? $data['name'] ?? null;
    $rawUsername = $data['username'] ?? null;
    $rawEmail = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (
        !is_string($rawFullName)
        || !is_string($rawUsername)
        || !is_string($rawEmail)
        || !is_string($password)
    ) {
        json_response(['success' => false, 'message' => 'Invalid registration details'], 400);
    }

    $fullName = trim($rawFullName);
    $username = trim($rawUsername);
    $email = trim($rawEmail);

    if (
        $fullName === ''
        || strlen($fullName) > 100
        || !preg_match('/\A[A-Za-z0-9._\-]{3,30}\z/', $username)
        || $email === ''
        || strlen($email) > 100
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        || $password === ''
        || strlen($password) > 4096
    ) {
        json_response(['success' => false, 'message' => 'Invalid registration details'], 400);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        error_log('Unable to hash password.');
        json_response(['success' => false, 'message' => 'Service unavailable'], 500);
    }

    $check = $pdo->prepare(
        'SELECT user_id FROM users WHERE email = :email OR username = :username LIMIT 1'
    );
    $check->execute(['email' => $email, 'username' => $username]);

    if ($check->fetch() !== false) {
        json_response(['success' => false, 'message' => 'Email or username already registered'], 409);
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, username, email, password_hash)
        VALUES (:full_name, :username, :email, :password)'
    );
    $insert->execute([
        'full_name' => $fullName,
        'username' => $username,
        'email' => $email,
        'password' => $passwordHash,
    ]);

    json_response(['success' => true, 'message' => 'User registered'], 201);
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

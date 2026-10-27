<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

require_auth_request('Registration', ['full_name', 'username', 'email', 'password']);
$input = read_request_body();

$rawFullName = $input['full_name'] ?? $input['fullName'] ?? $input['name'] ?? null;
$rawUsername = $input['username'] ?? null;
$rawEmail = $input['email'] ?? null;
$password = $input['password'] ?? null;

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
    || !preg_match('/\A[A-Za-z0-9._-]{3,30}\z/', $username)
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

try {
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
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        json_response(['success' => false, 'message' => 'Email or username already registered'], 409);
    }

    error_log('User registration failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

json_response(['success' => true, 'message' => 'User registered'], 201);

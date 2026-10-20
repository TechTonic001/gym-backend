<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

require_post_request();
$input = read_json_body();

$rawFullName = $input['full_name'] ?? null;
$rawEmail = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (
    !is_string($rawFullName)
    || !is_string($rawEmail)
    || !is_string($password)
) {
    json_response(['success' => false, 'message' => 'Invalid registration details'], 400);
}

$fullName = trim($rawFullName);
$email = trim($rawEmail);

if (
    $fullName === ''
    || strlen($fullName) > 255
    || $email === ''
    || strlen($email) > 254
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
    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);

    if ($check->fetch() !== false) {
        json_response(['success' => false, 'message' => 'Email already registered'], 409);
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, email, password) VALUES (:full_name, :email, :password)'
    );
    $insert->execute([
        'full_name' => $fullName,
        'email' => $email,
        'password' => $passwordHash,
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        json_response(['success' => false, 'message' => 'Email already registered'], 409);
    }

    error_log('User registration failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

json_response([
    'success' => true,
    'message' => 'User registered successfully.',
], 201);

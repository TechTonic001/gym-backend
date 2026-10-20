<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

require_post_request();
$input = read_json_body();

$rawEmail = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (
    !is_string($rawEmail)
    || !is_string($password)
) {
    json_response(['success' => false, 'message' => 'Invalid email or password'], 400);
}

$email = trim($rawEmail);

if (
    || $email === ''
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || $password === ''
    || strlen($password) > 4096
) {
    json_response(['success' => false, 'message' => 'Invalid email or password'], 400);
}

try {
    $statement = $pdo->prepare(
        'SELECT id, password FROM users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
} catch (PDOException $exception) {
    error_log('User login lookup failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Service unavailable'], 500);
}

if ($user === false || !password_verify($password, $user['password'])) {
    json_response(['success' => false, 'message' => 'Invalid email or password'], 401);
}

json_response([
    'success' => true,
    'user_id' => (int) $user['id'],
    'token' => 'mock-jwt-token',
]);

<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS');
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId === null || $userId < 1) {
    json_response(['success' => false, 'message' => 'A valid user_id is required'], 400);
}

try {
    $statement = $pdo->prepare(
        'SELECT
            u.user_id,
            u.full_name,
            u.email,
            mp.profile_id,
            mp.age,
            mp.gender,
            mp.height_cm,
            mp.weight_kg,
            mp.fitness_goal,
            mp.target_weight_kg
        FROM users u
        LEFT JOIN member_profiles mp ON mp.user_id = u.user_id
        WHERE u.user_id = :user_id
        LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $record = $statement->fetch();
} catch (Throwable $e) {
    error_log('Member profile lookup failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

if ($record === false) {
    json_response(['success' => false, 'message' => 'User not found'], 404);
}

$profileExists = $record['profile_id'] !== null;
$heightCm = $profileExists && $record['height_cm'] !== null
    ? (float) $record['height_cm']
    : null;
$weightKg = $profileExists && $record['weight_kg'] !== null
    ? (float) $record['weight_kg']
    : null;
$bmi = $heightCm !== null && $heightCm > 0 && $weightKg !== null
    ? round($weightKg / (($heightCm / 100) ** 2), 1)
    : null;
$bmiCategory = match (true) {
    $bmi === null => null,
    $bmi < 18.5 => 'Underweight',
    $bmi < 25 => 'Normal weight',
    $bmi < 30 => 'Overweight',
    default => 'Obese',
};

json_response([
    'success' => true,
    'user_id' => (int) $record['user_id'],
    'full_name' => $record['full_name'],
    'email' => $record['email'],
    'profile_exists' => $profileExists,
    'age' => $profileExists && $record['age'] !== null ? (int) $record['age'] : null,
    'gender' => $profileExists ? $record['gender'] : null,
    'height_cm' => $heightCm,
    'weight_kg' => $weightKg,
    'fitness_goal' => $profileExists ? $record['fitness_goal'] : null,
    'target_weight_kg' => $profileExists && $record['target_weight_kg'] !== null
        ? (float) $record['target_weight_kg']
        : null,
    'bmi' => $bmi,
    'bmi_category' => $bmiCategory,
]);

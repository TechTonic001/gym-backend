<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_request_body();
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
$age = filter_var($input['age'] ?? null, FILTER_VALIDATE_INT);
$gender = $input['gender'] ?? null;
$fitnessGoal = $input['fitness_goal'] ?? null;

if (
    $userId === false
    || $userId === null
    || $userId < 1
    || $age === false
    || $age === null
    || $age < 1
    || $age > 120
) {
    json_response(['success' => false, 'message' => 'A valid user_id and age are required'], 400);
}

if (!is_string($gender) || !in_array($gender, ['male', 'female', 'other'], true)) {
    json_response(['success' => false, 'message' => 'A valid gender is required'], 400);
}

if (
    !is_string($fitnessGoal)
    || !in_array($fitnessGoal, ['weight_loss', 'muscle_gain', 'endurance', 'maintenance'], true)
) {
    json_response(['success' => false, 'message' => 'A valid fitness_goal is required'], 400);
}

$parseMeasurement = static function (mixed $value): ?float {
    if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
        return null;
    }

    $measurement = (float) $value;
    if (!is_finite($measurement) || $measurement <= 0 || $measurement > 999.99) {
        return null;
    }

    return $measurement;
};

$heightCm = $parseMeasurement($input['height_cm'] ?? null);
$weightKg = $parseMeasurement($input['weight_kg'] ?? null);
$targetWeightKg = $parseMeasurement($input['target_weight_kg'] ?? null);

if ($heightCm === null || $weightKg === null || $targetWeightKg === null) {
    json_response([
        'success' => false,
        'message' => 'Height, weight, and target weight must be positive values no greater than 999.99',
    ], 400);
}

try {
    $userStatement = $pdo->prepare('SELECT user_id FROM users WHERE user_id = :user_id LIMIT 1');
    $userStatement->execute(['user_id' => $userId]);
    if ($userStatement->fetch() === false) {
        json_response(['success' => false, 'message' => 'User not found'], 404);
    }

    $statement = $pdo->prepare(
        'INSERT INTO member_profiles (
            user_id,
            age,
            gender,
            height_cm,
            weight_kg,
            fitness_goal,
            target_weight_kg
        ) VALUES (
            :user_id,
            :age,
            :gender,
            :height_cm,
            :weight_kg,
            :fitness_goal,
            :target_weight_kg
        )
        ON DUPLICATE KEY UPDATE
            age = VALUES(age),
            gender = VALUES(gender),
            height_cm = VALUES(height_cm),
            weight_kg = VALUES(weight_kg),
            fitness_goal = VALUES(fitness_goal),
            target_weight_kg = VALUES(target_weight_kg)'
    );
    $statement->execute([
        'user_id' => $userId,
        'age' => $age,
        'gender' => $gender,
        'height_cm' => $heightCm,
        'weight_kg' => $weightKg,
        'fitness_goal' => $fitnessGoal,
        'target_weight_kg' => $targetWeightKg,
    ]);
} catch (PDOException $exception) {
    error_log('Member profile update failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Unable to update member profile'], 500);
}

json_response(['success' => true, 'message' => 'Profile updated successfully.']);

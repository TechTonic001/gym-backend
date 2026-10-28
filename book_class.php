<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = read_request_body();
$userId = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
$classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
$bookingDate = $input['booking_date'] ?? null;

if (
    $userId === false
    || $userId === null
    || $userId < 1
    || $classId === false
    || $classId === null
    || $classId < 1
    || !is_string($bookingDate)
) {
    json_response(['success' => false, 'message' => 'Invalid booking details'], 400);
}

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
if ($date === false || $date->format('Y-m-d') !== $bookingDate) {
    json_response(['success' => false, 'message' => 'Invalid booking date'], 400);
}

try {
    $pdo->beginTransaction();

    $classStatement = $pdo->prepare(
        'SELECT capacity FROM classes WHERE class_id = :class_id FOR UPDATE'
    );
    $classStatement->execute(['class_id' => $classId]);
    $class = $classStatement->fetch();

    if ($class === false) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Class not found'], 404);
    }

    $subscriptionStatement = $pdo->prepare(
        "SELECT sub_id
        FROM subscriptions
        WHERE user_id = :user_id
            AND status = 'active'
            AND start_date <= CURRENT_DATE
            AND end_date >= CURRENT_DATE
        LIMIT 1"
    );
    $subscriptionStatement->execute(['user_id' => $userId]);

    if ($subscriptionStatement->fetch() === false) {
        $pdo->rollBack();
        json_response([
            'success' => false,
            'message' => 'Active subscription required to book classes.',
        ], 403);
    }

    $existingStatement = $pdo->prepare(
        "SELECT booking_id
        FROM bookings
        WHERE user_id = :user_id
            AND class_id = :class_id
            AND booking_date = :booking_date
            AND status = 'confirmed'
        LIMIT 1"
    );
    $existingStatement->execute([
        'user_id' => $userId,
        'class_id' => $classId,
        'booking_date' => $bookingDate,
    ]);

    if ($existingStatement->fetch() !== false) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Class already booked for this date'], 409);
    }

    $capacity = (int) ($class['capacity'] ?? 20);
    $countStatement = $pdo->prepare(
        "SELECT COUNT(*)
        FROM bookings
        WHERE class_id = :class_id
            AND booking_date = :booking_date
            AND status = 'confirmed'"
    );
    $countStatement->execute([
        'class_id' => $classId,
        'booking_date' => $bookingDate,
    ]);

    if ((int) $countStatement->fetchColumn() >= $capacity) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Class is full'], 409);
    }

    $insertStatement = $pdo->prepare(
        "INSERT INTO bookings (user_id, class_id, booking_date, status)
        VALUES (:user_id, :class_id, :booking_date, 'confirmed')"
    );
    $insertStatement->execute([
        'user_id' => $userId,
        'class_id' => $classId,
        'booking_date' => $bookingDate,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Class booking failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

json_response(['success' => true, 'message' => 'Class booked successfully.'], 201);

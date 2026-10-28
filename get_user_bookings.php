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
        "SELECT
            b.booking_id,
            c.title,
            c.trainer_name AS trainer,
            b.booking_date,
            c.start_time,
            c.end_time
        FROM bookings b
        INNER JOIN classes c ON c.class_id = b.class_id
        WHERE b.user_id = :user_id
            AND b.status = 'confirmed'
            AND b.booking_date >= CURRENT_DATE
        ORDER BY b.booking_date, c.start_time, b.booking_id"
    );
    $statement->execute(['user_id' => $userId]);

    json_response($statement->fetchAll());
} catch (Throwable $e) {
    error_log('User class booking lookup failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

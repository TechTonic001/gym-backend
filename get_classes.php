<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS');
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $statement = $pdo->query(
        "SELECT
            c.class_id,
            c.title,
            c.trainer_name AS trainer,
            c.day_of_week,
            c.start_time,
            c.end_time,
            COALESCE(c.capacity, 20) AS capacity,
            GREATEST(
                COALESCE(c.capacity, 20) - COALESCE(bookings.confirmed_bookings, 0),
                0
            ) AS available_spots
        FROM classes c
        LEFT JOIN (
            SELECT class_id, COUNT(*) AS confirmed_bookings
            FROM bookings
            WHERE status = 'confirmed'
            GROUP BY class_id
        ) bookings ON bookings.class_id = c.class_id
        ORDER BY c.day_of_week, c.start_time, c.class_id"
    );

    json_response($statement->fetchAll());
} catch (PDOException $exception) {
    error_log('Class list lookup failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Unable to load classes'], 500);
}

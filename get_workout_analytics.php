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
    $today = new DateTimeImmutable((string) $pdo->query('SELECT CURRENT_DATE')->fetchColumn());
    $statement = $pdo->prepare(
        'SELECT
            DATE(ws.workout_date) AS workout_date,
            COUNT(DISTINCT ws.session_id) AS frequency,
            COALESCE(SUM(el.reps * el.weight_kg), 0) AS volume
        FROM workout_sessions ws
        LEFT JOIN exercise_logs el ON el.session_id = ws.session_id
        WHERE ws.user_id = :user_id
            AND ws.workout_date >= :start_date
            AND ws.workout_date < :end_date
        GROUP BY DATE(ws.workout_date)
        ORDER BY DATE(ws.workout_date)'
    );
    $statement->execute([
        'user_id' => $userId,
        'start_date' => $today->modify('-6 days')->format('Y-m-d'),
        'end_date' => $today->modify('+1 day')->format('Y-m-d'),
    ]);
    $dailyTotals = $statement->fetchAll();

    $labels = [];
    $frequency = [];
    $volume = [];
    $dateIndexes = [];

    for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
        $date = $today->modify("-{$daysAgo} days");
        $dateKey = $date->format('Y-m-d');
        $dateIndexes[$dateKey] = count($labels);
        $labels[] = $date->format('D');
        $frequency[] = 0;
        $volume[] = 0.0;
    }

    foreach ($dailyTotals as $dailyTotal) {
        $dateKey = (string) $dailyTotal['workout_date'];
        if (!array_key_exists($dateKey, $dateIndexes)) {
            continue;
        }

        $index = $dateIndexes[$dateKey];
        $frequency[$index] = (int) $dailyTotal['frequency'];
        $volume[$index] = (float) $dailyTotal['volume'];
    }

    json_response([
        'labels' => $labels,
        'frequency' => $frequency,
        'volume' => $volume,
    ]);
} catch (Throwable $e) {
    error_log('Workout analytics lookup failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

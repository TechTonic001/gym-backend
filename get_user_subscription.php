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
        'SELECT s.sub_id, s.user_id, s.plan_id, gp.name AS plan_name, s.status, s.start_date, s.end_date
        FROM subscriptions s
        LEFT JOIN gym_plans gp ON gp.plan_id = s.plan_id
        WHERE s.user_id = :user_id
        ORDER BY s.end_date DESC, s.sub_id DESC
        LIMIT 1'
    );
    $statement->execute(['user_id' => $userId]);
    $subscription = $statement->fetch();

    if ($subscription === false) {
        json_response([
            'success' => true,
            'subscription' => null,
            'data' => null,
        ]);
    }

    json_response([
        'success' => true,
        'user_id' => (int) $subscription['user_id'],
        'sub_id' => (int) $subscription['sub_id'],
        'plan_id' => (int) $subscription['plan_id'],
        'plan_name' => $subscription['plan_name'] ?? null,
        'name' => $subscription['plan_name'] ?? null,
        'status' => (string) $subscription['status'],
        'start_date' => $subscription['start_date'],
        'end_date' => $subscription['end_date'],
        'subscription' => $subscription,
        'data' => $subscription,
    ]);
} catch (Throwable $e) {
    error_log('User subscription lookup failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

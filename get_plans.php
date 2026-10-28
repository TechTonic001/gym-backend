<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, OPTIONS');
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $statement = $pdo->query(
        'SELECT plan_id, name, monthly_price, features
        FROM gym_plans
        ORDER BY monthly_price ASC, plan_id ASC'
    );
    $plans = $statement->fetchAll();

    foreach ($plans as &$plan) {
        $plan['monthly_price'] = (float) ($plan['monthly_price'] ?? 0);
        $plan['features'] = is_string($plan['features'] ?? null)
            ? preg_split('/\r\n|\r|\n|,/', $plan['features']) ?: []
            : [];
        $plan['features'] = array_values(array_filter(array_map('trim', $plan['features']), static fn (string $feature): bool => $feature !== ''));
    }
    unset($plan);

    json_response([
        'success' => true,
        'plans' => $plans,
        'data' => $plans,
    ]);
} catch (Throwable $e) {
    error_log('Membership plans lookup failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

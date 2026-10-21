<?php

session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function planResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    planResponse(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

/*
 * A random cookie identifies this browser's plan.
 * Only its hash is stored in the database.
 */
$visitorToken = $_COOKIE['planner_visitor'] ?? '';

$validToken = is_string($visitorToken)
    && preg_match('/^[a-f0-9]{64}$/D', $visitorToken);

if (!$validToken) {
    if ($method === 'POST') {
        planResponse(403, [
            'success' => false,
            'message' => 'Reload the page before saving your plan.'
        ]);
    }

    $visitorToken = bin2hex(random_bytes(32));

    $cookiePath = str_replace(
        '\\',
        '/',
        dirname(dirname($_SERVER['SCRIPT_NAME']))
    );

    $cookiePath = rtrim($cookiePath, '/') . '/';

    setcookie('planner_visitor', $visitorToken, [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => $cookiePath,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

$visitorHash = hash('sha256', $visitorToken);

if (empty($_SESSION['visit_plan_csrf'])) {
    $_SESSION['visit_plan_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['visit_plan_csrf'];

if ($method === 'POST') {
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (
        !is_string($submittedToken)
        || !hash_equals($csrfToken, $submittedToken)
    ) {
        planResponse(403, [
            'success' => false,
            'message' => 'Session verification failed. Reload the page.'
        ]);
    }
}

try {
    require_once __DIR__ . '/../config/database.php';

    /*
     * GET: load this visitor's saved places in visit order.
     */
    if ($method === 'GET') {
        $findPlan = $pdo->prepare(
            'SELECT plan_id
             FROM visit_plans
             WHERE visitor_token_hash = :token'
        );

        $findPlan->execute(['token' => $visitorHash]);
        $planId = $findPlan->fetchColumn();

        $placeIds = [];

        if ($planId !== false) {
            $items = $pdo->prepare(
                'SELECT place_id
                 FROM visit_plan_items
                 WHERE plan_id = :plan_id
                 ORDER BY visit_order'
            );

            $items->execute(['plan_id' => $planId]);

            $placeIds = array_map(
                'strval',
                $items->fetchAll(PDO::FETCH_COLUMN)
            );
        }

        planResponse(200, [
            'success' => true,
            'exists' => $planId !== false,
            'place_ids' => $placeIds,
            'csrf_token' => $csrfToken
        ]);
    }

    /*
     * POST: validate and save the complete ordered plan.
     */
    $rawBody = file_get_contents(
        'php://input',
        false,
        null,
        0,
        16385
    );

    if ($rawBody === false || strlen($rawBody) > 16384) {
        planResponse(413, [
            'success' => false,
            'message' => 'Request is too large.'
        ]);
    }

    try {
        $body = json_decode(
            $rawBody,
            true,
            32,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        planResponse(400, [
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
    }

    $submittedIds = is_array($body)
        ? ($body['place_ids'] ?? null)
        : null;

    if (
        !is_array($submittedIds)
        || !array_is_list($submittedIds)
        || count($submittedIds) > 100
    ) {
        planResponse(422, [
            'success' => false,
            'message' => 'Provide a list of at most 100 places.'
        ]);
    }

    $placeIds = [];

    foreach ($submittedIds as $value) {
        if (
            (!is_int($value) && !is_string($value))
            || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)
            || (float) $value > 4294967295
        ) {
            planResponse(422, [
                'success' => false,
                'message' => 'Invalid place ID.'
            ]);
        }

        $placeIds[] = (int) $value;
    }

    if (count(array_unique($placeIds)) !== count($placeIds)) {
        planResponse(422, [
            'success' => false,
            'message' => 'A place cannot appear twice in one plan.'
        ]);
    }

    $pdo->beginTransaction();

    /*
     * The unique visitor hash keeps one plan per visitor.
     * This also locks the plan during the update.
     */
    $createPlan = $pdo->prepare(
        'INSERT INTO visit_plans (visitor_token_hash)
         VALUES (:token)
         ON DUPLICATE KEY UPDATE plan_id = plan_id'
    );

    $createPlan->execute(['token' => $visitorHash]);

    $findPlan = $pdo->prepare(
        'SELECT plan_id
         FROM visit_plans
         WHERE visitor_token_hash = :token
         FOR UPDATE'
    );

    $findPlan->execute(['token' => $visitorHash]);
    $planId = $findPlan->fetchColumn();

    if ($placeIds) {
        $marks = implode(',', array_fill(0, count($placeIds), '?'));

        $checkPlaces = $pdo->prepare(
            "SELECT place_id
             FROM places
             WHERE place_id IN ($marks)
             LOCK IN SHARE MODE"
        );

        $checkPlaces->execute($placeIds);
        $existingIds = $checkPlaces->fetchAll(PDO::FETCH_COLUMN);

        if (count($existingIds) !== count($placeIds)) {
            $pdo->rollBack();

            planResponse(409, [
                'success' => false,
                'message' => 'A selected place is no longer available. Reload the page.'
            ]);
        }
    }

    $deleteItems = $pdo->prepare(
        'DELETE FROM visit_plan_items WHERE plan_id = :plan_id'
    );

    $deleteItems->execute(['plan_id' => $planId]);

    $insertItem = $pdo->prepare(
        'INSERT INTO visit_plan_items
            (plan_id, place_id, visit_order)
         VALUES (:plan_id, :place_id, :visit_order)'
    );

    foreach ($placeIds as $index => $placeId) {
        $insertItem->execute([
            'plan_id' => $planId,
            'place_id' => $placeId,
            'visit_order' => $index + 1
        ]);
    }

    $updateTime = $pdo->prepare(
        'UPDATE visit_plans
         SET updated_at = CURRENT_TIMESTAMP
         WHERE plan_id = :plan_id'
    );

    $updateTime->execute(['plan_id' => $planId]);

    $pdo->commit();

    planResponse(200, [
        'success' => true,
        'exists' => true,
        'place_ids' => array_map('strval', $placeIds)
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Visit plan API failed: ' . $e->getMessage());

    planResponse(500, [
        'success' => false,
        'message' => 'Unable to access your visit plan. Please try again.'
    ]);
}
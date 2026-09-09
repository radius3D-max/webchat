<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();

header('Content-Type: application/json; charset=utf-8');

updateLastActivity();

$stmt = $pdo->query(
    'SELECT
        u.id,
        u.username,
        c.name AS city_name,
        cat.name AS category_name
     FROM users u
     LEFT JOIN cities c
        ON c.id = u.city_id
     LEFT JOIN categories cat
        ON cat.id = u.category_id
     WHERE u.is_blocked = 0
       AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY u.username ASC'
);

$users = $stmt->fetchAll();

echo json_encode(
    $users,
    JSON_UNESCAPED_UNICODE
);

exit;
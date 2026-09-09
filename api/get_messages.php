<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');

$afterId = isset($_GET['after_id'])
    ? (int) $_GET['after_id']
    : 0;

if ($afterId > 0) {

    $stmt = $pdo->prepare(
        'SELECT
            m.id,
            m.message,
            m.created_at,
            m.user_id,
            m.receiver_id,
            u.username AS sender_username
         FROM messages m
         INNER JOIN users u
            ON u.id = m.user_id
         WHERE m.id > ?
           AND m.receiver_id IS NULL
         ORDER BY m.id DESC
         LIMIT 50'
    );

    $stmt->execute(array($afterId));

} else {

    $stmt = $pdo->query(
        'SELECT
            m.id,
            m.message,
            m.created_at,
            m.user_id,
            m.receiver_id,
            u.username AS sender_username
         FROM messages m
         INNER JOIN users u
            ON u.id = m.user_id
         WHERE m.receiver_id IS NULL
         ORDER BY m.id DESC
         LIMIT 50'
    );
}

$messages = $stmt->fetchAll();

echo json_encode(
    $messages,
    JSON_UNESCAPED_UNICODE
);

exit;
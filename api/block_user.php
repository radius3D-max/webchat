<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../private.php');
    exit;
}

$myId = currentUserId();

$targetId = isset($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;

if ($targetId <= 0 || $targetId == $myId) {
    header('Location: ../private.php');
    exit;
}


/*
 * Проверяем пользователя.
 */

$stmt = $pdo->prepare(
    'SELECT id
     FROM users
     WHERE id = ?
       AND is_blocked = 0
     LIMIT 1'
);

$stmt->execute(array($targetId));

if (!$stmt->fetch()) {
    header('Location: ../private.php');
    exit;
}


/*
 * Создаём блокировку.
 */

$stmt = $pdo->prepare(
    'INSERT IGNORE INTO user_blocks
        (user_id, blocked_user_id)
     VALUES
        (?, ?)'
);

$stmt->execute(array(
    $myId,
    $targetId
));


/*
 * Возвращаемся в диалог.
 */

header(
    'Location: ../private.php?user_id=' .
    $targetId .
    '&blocked_by_me=1'
);

exit;
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
 * Удаляем блокировку.
 */

$stmt = $pdo->prepare(
    'DELETE FROM user_blocks
     WHERE user_id = ?
       AND blocked_user_id = ?'
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
    '&unblocked=1'
);

exit;
<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');

$userId = (int) currentUserId();

$friendId = isset($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;

if ($friendId <= 0) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Не указан пользователь.'
    ));

    exit;
}


/*
 * Удаляем дружбу в обоих направлениях.
 */

$stmt = $pdo->prepare(
    'DELETE FROM friends
     WHERE
        (user_id = ? AND friend_id = ?)
        OR
        (user_id = ? AND friend_id = ?)'
);

$stmt->execute(array(
    $userId,
    $friendId,
    $friendId,
    $userId
));


echo json_encode(array(
    'success' => true,
    'message' => 'Пользователь удалён из друзей.'
));

exit;
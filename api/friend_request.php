<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');

$userId = (int) currentUserId();

$receiverId = isset($_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;

if ($receiverId <= 0) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Не указан пользователь.'
    ));
    exit;
}

if ($receiverId === $userId) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Нельзя добавить самого себя.'
    ));
    exit;
}


/*
 * Проверяем существование пользователя.
 */

$stmt = $pdo->prepare(
    'SELECT id
     FROM users
     WHERE id = ?
       AND is_blocked = 0
     LIMIT 1'
);

$stmt->execute(array($receiverId));

if (!$stmt->fetch()) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Пользователь не найден.'
    ));

    exit;
}


/*
 * Проверяем существующую дружбу.
 */

$stmt = $pdo->prepare(
    'SELECT id
     FROM friends
     WHERE
        (user_id = ? AND friend_id = ?)
        OR
        (user_id = ? AND friend_id = ?)
     LIMIT 1'
);

$stmt->execute(array(
    $userId,
    $receiverId,
    $receiverId,
    $userId
));

if ($stmt->fetch()) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Вы уже друзья.'
    ));

    exit;
}


/*
 * Проверяем существующую заявку
 * в любом направлении.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        sender_id,
        receiver_id,
        status
     FROM friend_requests
     WHERE
        (sender_id = ? AND receiver_id = ?)
        OR
        (sender_id = ? AND receiver_id = ?)
     ORDER BY id DESC
     LIMIT 1'
);

$stmt->execute(array(
    $userId,
    $receiverId,
    $receiverId,
    $userId
));

$request = $stmt->fetch(PDO::FETCH_ASSOC);


/*
 * Если уже есть pending-заявка.
 */

if (
    $request &&
    $request['status'] === 'pending'
) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Заявка уже существует.'
    ));

    exit;
}


/*
 * Если старую заявку отклонили,
 * разрешаем создать новую.
 */

$stmt = $pdo->prepare(
    'INSERT INTO friend_requests
    (
        sender_id,
        receiver_id,
        status,
        created_at,
        updated_at
    )
    VALUES
    (
        ?,
        ?,
        "pending",
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    )'
);

$stmt->execute(array(
    $userId,
    $receiverId
));

$requestId =
    (int) $pdo->lastInsertId();


/*
 * Создаём уведомление.
 */

try {

    $stmt = $pdo->prepare(
        'INSERT INTO notifications
        (
            user_id,
            actor_id,
            type,
            reference_id,
            message,
            is_read,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            "friend_request",
            ?,
            ?,
            0,
            CURRENT_TIMESTAMP
        )'
    );

    $stmt->execute(array(
        $receiverId,
        $userId,
        $requestId,
        'Новая заявка в друзья'
    ));

} catch (Exception $e) {
    /*
     * Уведомление не должно ломать
     * саму заявку.
     */
}


echo json_encode(array(
    'success' => true,
    'message' => 'Заявка отправлена.',
    'request_id' => $requestId
));

exit;
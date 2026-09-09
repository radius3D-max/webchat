<?php

/*
 * =========================================================
 * api/send_private.php
 * =========================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');


/*
 * =========================================================
 * ПРОВЕРКА МЕТОДА
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Недопустимый метод запроса.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


$senderId =
    currentUserId();


$receiverId =
    isset($_POST['receiver_id'])
        ? (int) $_POST['receiver_id']
        : 0;


$message =
    isset($_POST['message'])
        ? trim($_POST['message'])
        : '';


/*
 * =========================================================
 * ПРОВЕРКА ПОЛУЧАТЕЛЯ
 * =========================================================
 */

if (
    $receiverId <= 0 ||
    $receiverId == $senderId
) {

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Неверный получатель.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * =========================================================
 * ПРОВЕРКА СООБЩЕНИЯ
 * =========================================================
 */

if ($message === '') {

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Введите сообщение.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * =========================================================
 * МАКСИМАЛЬНАЯ ДЛИНА
 * =========================================================
 */

if (
    mb_strlen(
        $message,
        'UTF-8'
    ) > 2000
) {

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Сообщение слишком длинное.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * =========================================================
 * ПРОВЕРЯЕМ ПОЛУЧАТЕЛЯ
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        username
     FROM users
     WHERE id = ?
       AND is_blocked = 0
     LIMIT 1'
);

$stmt->execute(
    array(
        $receiverId
    )
);

$receiver =
    $stmt->fetch();


if (!$receiver) {

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Пользователь не найден.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * =========================================================
 * ПРОВЕРКА БЛОКИРОВКИ
 * =========================================================
 *
 * Проверяем, не заблокировал ли
 * получатель отправителя.
 */

$stmt = $pdo->prepare(
    'SELECT
        id
     FROM user_blocks
     WHERE user_id = ?
       AND blocked_user_id = ?
     LIMIT 1'
);

$stmt->execute(
    array(
        $receiverId,
        $senderId
    )
);


if ($stmt->fetch()) {

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Этот пользователь заблокировал вас.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * =========================================================
 * СОХРАНЯЕМ ПРИВАТНОЕ СООБЩЕНИЕ
 * =========================================================
 *
 * Используем только те поля,
 * которые реально существуют
 * в таблице private_messages.
 */

$stmt = $pdo->prepare(
    'INSERT INTO private_messages
     (
        sender_id,
        receiver_id,
        message,
        is_read
     )
     VALUES
     (
        ?,
        ?,
        ?,
        0
     )'
);

$stmt->execute(
    array(
        $senderId,
        $receiverId,
        $message
    )
);


$messageId =
    $pdo->lastInsertId();


/*
 * =========================================================
 * ПОЛУЧАЕМ СОЗДАННОЕ СООБЩЕНИЕ
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        pm.id,
        pm.sender_id,
        pm.receiver_id,
        pm.message,
        pm.created_at,

        sender.username AS sender_name,
        receiver.username AS receiver_name

     FROM private_messages pm

     INNER JOIN users sender
        ON sender.id = pm.sender_id

     INNER JOIN users receiver
        ON receiver.id = pm.receiver_id

     WHERE pm.id = ?

     LIMIT 1'
);

$stmt->execute(
    array(
        $messageId
    )
);


$savedMessage =
    $stmt->fetch();


/*
 * =========================================================
 * ОТВЕТ
 * =========================================================
 */

echo json_encode(
    array(
        'success' => true,
        'message' => $savedMessage
    ),
    JSON_UNESCAPED_UNICODE
);

exit;
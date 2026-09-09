<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=UTF-8');


/*
 * =========================================================
 * ОБЩИЙ ЧАТ
 * =========================================================
 *
 * Этот API работает ТОЛЬКО с таблицей messages.
 *
 * Приватные сообщения здесь не сохраняются.
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


/*
 * Получаем текущего пользователя.
 */

$user = currentUser();

if (!$user) {

    http_response_code(401);

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Пользователь не авторизован.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * Получаем сообщение.
 */

$message =
    isset($_POST['message'])
        ? trim($_POST['message'])
        : '';


/*
 * Проверяем пустое сообщение.
 */

if ($message === '') {

    http_response_code(400);

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Сообщение не может быть пустым.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * Ограничение длины.
 */

if (mb_strlen($message, 'UTF-8') > 2000) {

    http_response_code(400);

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
 * ID отправителя.
 */

$senderId =
    (int) $user['id'];


/*
 * =========================================================
 * СОХРАНЯЕМ ОБЩЕЕ СООБЩЕНИЕ
 * =========================================================
 *
 * receiver_id всегда NULL.
 */

$stmt = $pdo->prepare(
    'INSERT INTO messages
        (
            user_id,
            receiver_id,
            message,
            created_at
        )
     VALUES
        (
            ?,
            NULL,
            ?,
            NOW()
        )'
);

$stmt->execute(
    array(
        $senderId,
        $message
    )
);


$messageId =
    (int) $pdo->lastInsertId();


/*
 * =========================================================
 * ОТВЕТ
 * =========================================================
 */

echo json_encode(
    array(
        'success' => true,
        'type' => 'public',
        'id' => $messageId,
        'sender_id' => $senderId,
        'message' => $message
    ),
    JSON_UNESCAPED_UNICODE
);

exit;
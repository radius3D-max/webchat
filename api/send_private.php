<?php

/*
 * =========================================================
 * api/send_private.php
 * Отправка приватного сообщения
 * PHP 5.6.4
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


/*
 * =========================================================
 * ДАННЫЕ
 * =========================================================
 */

$senderId = currentUserId();

$receiverId = isset($_POST['receiver_id'])
    ? (int) $_POST['receiver_id']
    : 0;

$message = isset($_POST['message'])
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

$receiver = $stmt->fetch();


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
 * ТРАНЗАКЦИЯ
 * =========================================================
 *
 * Сначала создаём сообщение в private_messages.
 * Затем создаём запись в personal_messages.
 *
 * Если вторая операция не выполнится,
 * первая также будет отменена.
 */

try {

    $pdo->beginTransaction();


    /*
     * -----------------------------------------------------
     * 1. СОХРАНЯЕМ ПРИВАТНОЕ СООБЩЕНИЕ
     * -----------------------------------------------------
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


    $messageId = $pdo->lastInsertId();


    /*
     * -----------------------------------------------------
     * 2. СОЗДАЁМ ЛИЧНОЕ СООБЩЕНИЕ / АРХИВ
     * -----------------------------------------------------
     *
     * Здесь сам текст НЕ дублируется.
     *
     * personal_messages.private_message_id
     * указывает на сообщение в private_messages.
     */

    $stmt = $pdo->prepare(
        'INSERT INTO personal_messages
         (
            sender_id,
            receiver_id,
            private_message_id,
            is_read,
            created_at
         )
         VALUES
         (
            ?,
            ?,
            ?,
            0,
            NOW()
         )'
    );

    $stmt->execute(
        array(
            $senderId,
            $receiverId,
            $messageId
        )
    );


    /*
     * -----------------------------------------------------
     * ФИКСИРУЕМ ОБЕ ОПЕРАЦИИ
     * -----------------------------------------------------
     */

    $pdo->commit();

} catch (Exception $e) {

    /*
     * Если что-то пошло не так,
     * отменяем изменения.
     */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode(
        array(
            'success' => false,
            'error' => 'Не удалось сохранить сообщение.'
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
 * =========================================================
 * ПОЛУЧАЕМ СОХРАНЁННОЕ СООБЩЕНИЕ
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

$savedMessage = $stmt->fetch();


/*
 * =========================================================
 * ОТВЕТ
 * =========================================================
 */

echo json_encode(
    array(
        'success' => true,
        'message' => $savedMessage,
        'personal_message_id' => $pdo->lastInsertId()
    ),
    JSON_UNESCAPED_UNICODE
);

exit;
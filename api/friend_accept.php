<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');

$userId = (int) currentUserId();

$requestId = isset($_POST['request_id'])
    ? (int) $_POST['request_id']
    : 0;

if ($requestId <= 0) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Не указана заявка.'
    ));

    exit;
}


/*
 * Получаем входящую заявку.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        sender_id,
        receiver_id,
        status
     FROM friend_requests
     WHERE id = ?
       AND receiver_id = ?
       AND status = "pending"
     LIMIT 1'
);

$stmt->execute(array(
    $requestId,
    $userId
));

$request =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Заявка не найдена.'
    ));

    exit;
}


$senderId =
    (int) $request['sender_id'];


/*
 * Используем транзакцию.
 */

try {

    $pdo->beginTransaction();


    /*
     * Меняем статус заявки.
     */

    $stmt = $pdo->prepare(
        'UPDATE friend_requests
         SET
            status = "accepted",
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND receiver_id = ?'
    );

    $stmt->execute(array(
        $requestId,
        $userId
    ));


    /*
     * Создаём дружбу в двух направлениях.
     */

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO friends
        (
            user_id,
            friend_id,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            CURRENT_TIMESTAMP
        )'
    );

    $stmt->execute(array(
        $userId,
        $senderId
    ));

    $stmt->execute(array(
        $senderId,
        $userId
    ));


    /*
     * Уведомляем отправителя.
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
                "friend_accepted",
                ?,
                ?,
                0,
                CURRENT_TIMESTAMP
            )'
        );

        $stmt->execute(array(
            $senderId,
            $userId,
            $requestId,
            'Ваша заявка в друзья принята'
        ));

    } catch (Exception $e) {
        /*
         * Не ломаем принятие дружбы.
         */
    }


    $pdo->commit();


    echo json_encode(array(
        'success' => true,
        'message' => 'Заявка принята.'
    ));

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode(array(
        'success' => false,
        'message' => 'Не удалось принять заявку.'
    ));
}

exit;
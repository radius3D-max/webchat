<?php

/*
 * =========================================================
 * api/get_private_dialogs.php
 *
 * Список приватных диалогов текущего пользователя.
 *
 * PHP 5.6.4
 * =========================================================
 */

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');

$myId = currentUserId();


/*
 * =========================================================
 * ДИАЛОГИ
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        u.id,
        u.username,
        u.last_activity,

        pm.id AS last_message_id,
        pm.message AS last_message,
        pm.created_at AS last_message_time,

        COALESCE(unread.unread_count, 0) AS unread_count

     FROM users u

     INNER JOIN
     (
         SELECT
             CASE
                 WHEN sender_id = ? THEN receiver_id
                 ELSE sender_id
             END AS user_id,

             MAX(id) AS last_message_id

         FROM private_messages

         WHERE
             sender_id = ?
             OR receiver_id = ?

         GROUP BY
             user_id

     ) last_dialog
         ON last_dialog.user_id = u.id

     INNER JOIN private_messages pm
         ON pm.id = last_dialog.last_message_id

     LEFT JOIN
     (
         SELECT
             sender_id,
             COUNT(*) AS unread_count

         FROM private_messages

         WHERE
             receiver_id = ?
             AND is_read = 0

         GROUP BY
             sender_id

     ) unread
         ON unread.sender_id = u.id

     WHERE
         u.is_blocked = 0

     ORDER BY
         pm.id DESC'
);

$stmt->execute(
    array(
        $myId,
        $myId,
        $myId,
        $myId
    )
);

$dialogs = $stmt->fetchAll();


/*
 * =========================================================
 * ФОРМИРУЕМ JSON
 * =========================================================
 */

$result = array();

foreach ($dialogs as $dialog) {

    $result[] = array(
        'id' => (int) $dialog['id'],
        'username' => $dialog['username'],
        'last_activity' => $dialog['last_activity'],

        'last_message_id' =>
            (int) $dialog['last_message_id'],

        'last_message' =>
            $dialog['last_message'],

        'last_message_time' =>
            $dialog['last_message_time'],

        'unread_count' =>
            (int) $dialog['unread_count']
    );
}


/*
 * =========================================================
 * ОТВЕТ
 * =========================================================
 */

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
);

exit;
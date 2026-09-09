<?php

/*
 * =========================================================
 * api/get_private_dialogs.php
 *
 * Получение списка личных диалогов текущего пользователя.
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
 * ПОСЛЕДНИЙ ДИАЛОГ С КАЖДЫМ ПОЛЬЗОВАТЕЛЕМ
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        u.id,
        u.username,
        u.last_activity,

        pm.message AS last_message,
        pm.created_at AS last_message_time,

        COALESCE(unread.unread_count, 0) AS unread_count

     FROM users u

     INNER JOIN
     (
         SELECT
             CASE
                 WHEN p.sender_id = ? THEN p.receiver_id
                 ELSE p.sender_id
             END AS user_id,

             MAX(p.id) AS last_message_id

         FROM personal_messages p

         WHERE
             p.sender_id = ?
             OR p.receiver_id = ?

         GROUP BY user_id

     ) last_dialog
         ON last_dialog.user_id = u.id

     INNER JOIN personal_messages pmsg
         ON pmsg.id = last_dialog.last_message_id

     INNER JOIN private_messages pm
         ON pm.id = pmsg.private_message_id

     LEFT JOIN
     (
         SELECT
             sender_id,
             COUNT(*) AS unread_count

         FROM personal_messages

         WHERE
             receiver_id = ?
             AND is_read = 0

         GROUP BY sender_id

     ) unread
         ON unread.sender_id = u.id

     WHERE
         u.is_blocked = 0

     ORDER BY
         pmsg.id DESC'
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
 * ПОДГОТАВЛИВАЕМ ДАННЫЕ ДЛЯ JAVASCRIPT
 * =========================================================
 */

$result = array();

foreach ($dialogs as $dialog) {

    $result[] = array(
        'id' => (int) $dialog['id'],
        'username' => $dialog['username'],
        'last_activity' => $dialog['last_activity'],
        'last_message' => $dialog['last_message'],
        'last_message_time' => $dialog['last_message_time'],
        'unread_count' => (int) $dialog['unread_count']
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
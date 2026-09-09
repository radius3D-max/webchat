<?php


/*


api/get_messages.php


 */

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

header('Content-Type: application/json; charset=utf-8');


/*
 * ID последнего сообщения,
 * которое уже есть у клиента.
 */

$afterId = 0;

if (isset($_GET['after_id'])) {
    $afterId = (int) $_GET['after_id'];
}


/*
 * Если after_id не передан или равен 0,
 * отдаём последние 50 сообщений.
 */

if ($afterId <= 0) {

    $stmt = $pdo->query(
        'SELECT
    m.id,
    m.message,
    m.created_at,
    m.user_id,
    u.username,
    up.gender
FROM messages m
INNER JOIN users u
    ON u.id = m.user_id
LEFT JOIN user_profiles up
    ON up.user_id = u.id
WHERE m.id > ?
ORDER BY m.id ASC
LIMIT 50'
    );

    $messages = $stmt->fetchAll();

    /*
     * Запрос идёт от новых к старым,
     * поэтому разворачиваем массив.
     */

    $messages = array_reverse($messages);

} else {

    /*
     * После первоначальной загрузки
     * получаем только новые сообщения.
     */

    $stmt = $pdo->prepare(
        'SELECT
            m.id,
            m.message,
            m.created_at,
            m.user_id,
            u.username
         FROM messages m
         INNER JOIN users u ON u.id = m.user_id
         WHERE m.id > ?
         ORDER BY m.id ASC
         LIMIT 50'
    );

    $stmt->execute(array($afterId));

    $messages = $stmt->fetchAll();
}


echo json_encode(
    $messages,
    JSON_UNESCAPED_UNICODE
);

exit;
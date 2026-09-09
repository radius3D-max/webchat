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


$stmt = $pdo->prepare(
    'UPDATE friend_requests
     SET
        status = "rejected",
        updated_at = CURRENT_TIMESTAMP
     WHERE id = ?
       AND receiver_id = ?
       AND status = "pending"'
);

$stmt->execute(array(
    $requestId,
    $userId
));


if ($stmt->rowCount() === 0) {

    echo json_encode(array(
        'success' => false,
        'message' => 'Заявка не найдена.'
    ));

    exit;
}


echo json_encode(array(
    'success' => true,
    'message' => 'Заявка отклонена.'
));

exit;
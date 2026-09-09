<?php

require_once __DIR__ . '/config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$userId = currentUserId();


/*
 * Получаем адрес профиля
 * текущего пользователя.
 */

$stmt = $pdo->prepare(
    'SELECT profile_slug
     FROM user_profiles
     WHERE user_id = ?
     LIMIT 1'
);

$stmt->execute(
    array($userId)
);

$profile = $stmt->fetch();


/*
 * Если профиль ещё не создан.
 */

if (!$profile) {

    header(
        'Location: /users/edit.php'
    );

    exit;
}


/*
 * Переходим на персональный адрес.
 */

header(
    'Location: /users/' .
    rawurlencode($profile['profile_slug'])
);

exit;
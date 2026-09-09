<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();


/*
 * ID публикации.
 */

$postId = 0;

if (isset($_POST['post_id'])) {
    $postId = (int) $_POST['post_id'];
}

if ($postId <= 0) {
    http_response_code(404);
    exit('Публикация не найдена.');
}


/*
 * CSRF.
 */

$csrf = '';

if (isset($_POST['csrf_token'])) {
    $csrf = $_POST['csrf_token'];
}

if (!verifyCsrfToken($csrf)) {
    http_response_code(403);
    exit('Ошибка безопасности. Обновите страницу и попробуйте снова.');
}


/*
 * Получаем публикацию
 * вместе с профилем автора.
 */

$stmt = $pdo->prepare(
    'SELECT
        p.id,
        p.user_id,
        up.profile_slug

     FROM posts p

     INNER JOIN users u
        ON u.id = p.user_id

     INNER JOIN user_profiles up
        ON up.user_id = u.id

     WHERE p.id = ?

     LIMIT 1'
);

$stmt->execute(array(
    $postId
));

$post = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$post) {
    http_response_code(404);
    exit('Публикация не найдена.');
}


/*
 * Проверяем права.
 *
 * Автор может удалить свою публикацию.
 *
 * Пользователь с разрешением
 * posts.delete может удалить чужую.
 */

$currentUserId = currentUserId();

$isAuthor =
    (int) $currentUserId ===
    (int) $post['user_id'];

$canDelete =
    $isAuthor ||
    can('posts.delete');


if (!$canDelete) {

    http_response_code(403);

    exit(
        'У вас нет права удалять эту публикацию.'
    );
}


/*
 * Удаляем публикацию.
 */

$stmt = $pdo->prepare(
    'DELETE FROM posts
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute(array(
    $postId
));


/*
 * После удаления
 * возвращаемся на профиль автора.
 */

header(
    'Location: /users/' .
    rawurlencode(
        $post['profile_slug']
    )
);

exit;
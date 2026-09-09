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

    http_response_code(400);

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

    exit(
        'Ошибка безопасности. Обновите страницу и попробуйте снова.'
    );
}


/*
 * Текущий пользователь.
 */

$userId = currentUserId();

if (!$userId) {

    http_response_code(401);

    exit('Необходимо войти в аккаунт.');
}


/*
 * Проверяем существование публикации.
 *
 * Лайкнуть можно только существующую
 * публичную публикацию.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        user_id,
        visibility

     FROM posts

     WHERE id = ?

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

    exit(
        'Публикация не найдена.'
    );
}


/*
 * Проверяем видимость публикации.
 */

if ($post['visibility'] !== 'public') {

    http_response_code(403);

    exit(
        'Эта публикация недоступна.'
    );
}


/*
 * Проверяем, существует ли уже лайк
 * этого пользователя.
 */

$stmt = $pdo->prepare(
    'SELECT
        post_id

     FROM post_likes

     WHERE post_id = ?
       AND user_id = ?

     LIMIT 1'
);

$stmt->execute(array(
    $postId,
    $userId
));

$existingLike = $stmt->fetch(
    PDO::FETCH_ASSOC
);


/*
 * Переключаем состояние лайка.
 */

if ($existingLike) {

    /*
     * Лайк уже есть —
     * удаляем его.
     */

    $stmt = $pdo->prepare(
        'DELETE FROM post_likes
         WHERE post_id = ?
           AND user_id = ?
         LIMIT 1'
    );

    $stmt->execute(array(
        $postId,
        $userId
    ));

    $liked = false;

} else {

    /*
     * Лайка нет —
     * создаём его.
     */

    $stmt = $pdo->prepare(
        'INSERT INTO post_likes
        (
            post_id,
            user_id,
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
        $postId,
        $userId
    ));

    $liked = true;
}


/*
 * Получаем актуальное количество лайков.
 */

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM post_likes
     WHERE post_id = ?'
);

$stmt->execute(array(
    $postId
));

$likesCount = (int) $stmt->fetchColumn();


/*
 * Возвращаем JSON.
 */

header(
    'Content-Type: application/json; charset=UTF-8'
);

echo json_encode(
    array(
        'success' => true,
        'liked' => $liked,
        'likes_count' => $likesCount
    ),
    JSON_UNESCAPED_UNICODE
);

exit;

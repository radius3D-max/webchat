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
 * Текст комментария.
 */

$content = '';

if (isset($_POST['content'])) {
    $content = trim($_POST['content']);
}


/*
 * Проверяем комментарий.
 */

if ($content === '') {

    http_response_code(400);

    exit('Введите текст комментария.');
}


if (mb_strlen($content) > 2000) {

    http_response_code(400);

    exit(
        'Комментарий не может быть длиннее 2000 символов.'
    );
}


/*
 * Получаем публикацию.
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
 * Комментировать можно только
 * публичную публикацию.
 */

if ($post['visibility'] !== 'public') {

    http_response_code(403);

    exit(
        'Эта публикация недоступна.'
    );
}


/*
 * Родительский комментарий.
 *
 * Если parent_id не передан —
 * это обычный комментарий.
 */

$parentId = null;

if (
    isset($_POST['parent_id']) &&
    $_POST['parent_id'] !== ''
) {

    $parentId = (int) $_POST['parent_id'];

    if ($parentId <= 0) {
        $parentId = null;
    }
}


/*
 * Если указан parent_id,
 * проверяем существование
 * родительского комментария
 * именно у этой публикации.
 */

if ($parentId !== null) {

    $stmt = $pdo->prepare(
        'SELECT
            id

         FROM post_comments

         WHERE id = ?
           AND post_id = ?

         LIMIT 1'
    );

    $stmt->execute(array(
        $parentId,
        $postId
    ));

    $parentComment = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$parentComment) {

        http_response_code(400);

        exit(
            'Родительский комментарий не найден.'
        );
    }
}


/*
 * Создаём комментарий.
 */

$stmt = $pdo->prepare(
    'INSERT INTO post_comments
    (
        post_id,
        user_id,
        parent_id,
        content,
        created_at,
        updated_at
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    )'
);

$stmt->execute(array(
    $postId,
    $userId,
    $parentId,
    $content
));


/*
 * ID нового комментария.
 */

$commentId = (int) $pdo->lastInsertId();


/*
 * Получаем созданный комментарий
 * вместе с данными пользователя.
 */

$stmt = $pdo->prepare(
    'SELECT
        c.id,
        c.post_id,
        c.user_id,
        c.parent_id,
        c.content,
        c.created_at,
        c.updated_at,

        u.username,

        up.profile_slug,
        up.display_name,
        up.avatar

     FROM post_comments c

     INNER JOIN users u
        ON u.id = c.user_id

     INNER JOIN user_profiles up
        ON up.user_id = u.id

     WHERE c.id = ?

     LIMIT 1'
);

$stmt->execute(array(
    $commentId
));

$comment = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$comment) {

    http_response_code(500);

    exit(
        'Комментарий создан, но получить его не удалось.'
    );
}


/*
 * Получаем актуальное количество
 * комментариев публикации.
 */

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM post_comments
     WHERE post_id = ?'
);

$stmt->execute(array(
    $postId
));

$commentsCount = (int) $stmt->fetchColumn();


/*
 * Возвращаем JSON.
 */

header(
    'Content-Type: application/json; charset=UTF-8'
);

echo json_encode(
    array(
        'success' => true,

        'comment' => array(
            'id' => (int) $comment['id'],
            'post_id' => (int) $comment['post_id'],
            'user_id' => (int) $comment['user_id'],
            'parent_id' => $comment['parent_id'] !== null
                ? (int) $comment['parent_id']
                : null,
            'content' => $comment['content'],
            'created_at' => $comment['created_at'],
            'updated_at' => $comment['updated_at'],
            'username' => $comment['username'],
            'profile_slug' => $comment['profile_slug'],
            'display_name' => $comment['display_name'],
            'avatar' => $comment['avatar']
        ),

        'comments_count' => $commentsCount
    ),
    JSON_UNESCAPED_UNICODE
);

exit;

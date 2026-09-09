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
 * Текущий пользователь.
 */

$userId = currentUserId();

if (!$userId) {

    http_response_code(401);

    exit('Необходимо войти в аккаунт.');
}


/*
 * Проверяем существование публикации.
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
 * Просматривать можно только
 * публичную публикацию.
 */

if ($post['visibility'] !== 'public') {

    http_response_code(403);

    exit(
        'Эта публикация недоступна.'
    );
}


/*
 * Проверяем, был ли уже просмотр
 * этой публикации текущим пользователем.
 */

$stmt = $pdo->prepare(
    'SELECT
        id

     FROM post_views

     WHERE post_id = ?
       AND user_id = ?

     LIMIT 1'
);

$stmt->execute(array(
    $postId,
    $userId
));

$existingView = $stmt->fetch(
    PDO::FETCH_ASSOC
);


/*
 * Добавляем просмотр только один раз.
 */

if (!$existingView) {

    $stmt = $pdo->prepare(
        'INSERT INTO post_views
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

    $viewAdded = true;

} else {

    $viewAdded = false;
}


/*
 * Получаем актуальное количество
 * просмотров.
 */

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM post_views
     WHERE post_id = ?'
);

$stmt->execute(array(
    $postId
));

$viewsCount = (int) $stmt->fetchColumn();


/*
 * Возвращаем JSON.
 */

header(
    'Content-Type: application/json; charset=UTF-8'
);

echo json_encode(
    array(
        'success' => true,
        'view_added' => $viewAdded,
        'views_count' => $viewsCount
    ),
    JSON_UNESCAPED_UNICODE
);

exit;

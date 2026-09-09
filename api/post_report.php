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
 * Жаловаться можно только
 * на публичную публикацию.
 */

if ($post['visibility'] !== 'public') {

    http_response_code(403);

    exit(
        'Эта публикация недоступна.'
    );
}


/*
 * Автор не может пожаловаться
 * на собственную публикацию.
 */

if (
    (int) $post['user_id'] ===
    (int) $userId
) {

    http_response_code(403);

    exit(
        'Нельзя пожаловаться на собственную публикацию.'
    );
}


/*
 * Причина жалобы.
 *
 * Используем короткие значения,
 * чтобы потом удобно было
 * обрабатывать их в панели модерации.
 */

$reason = '';

if (isset($_POST['reason'])) {
    $reason = trim($_POST['reason']);
}


/*
 * Дополнительный комментарий.
 */

$comment = '';

if (isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
}


/*
 * Проверяем причину.
 */

$allowedReasons = array(
    'spam',
    'advertising',
    'insult',
    'violence',
    'adult',
    'illegal',
    'other'
);


if (
    $reason === '' ||
    !in_array($reason, $allowedReasons, true)
) {

    http_response_code(400);

    exit(
        'Выберите причину жалобы.'
    );
}


/*
 * Ограничение дополнительного комментария.
 */

if (mb_strlen($comment) > 2000) {

    http_response_code(400);

    exit(
        'Комментарий к жалобе не может быть длиннее 2000 символов.'
    );
}


/*
 * Проверяем существующую активную жалобу.
 *
 * Один пользователь не должен
 * несколько раз жаловаться
 * на одну и ту же публикацию.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        status

     FROM post_reports

     WHERE post_id = ?
       AND user_id = ?

     ORDER BY id DESC

     LIMIT 1'
);

$stmt->execute(array(
    $postId,
    $userId
));

$existingReport = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if ($existingReport) {

    /*
     * Если жалоба уже существует
     * и ещё находится в работе,
     * новую создавать нельзя.
     */

    if (
        $existingReport['status'] === 'pending' ||
        $existingReport['status'] === 'reviewing'
    ) {

        http_response_code(409);

        exit(
            'Вы уже отправляли жалобу на эту публикацию.'
        );
    }

    /*
     * Если предыдущая жалоба была
     * закрыта, разрешаем создать
     * новую жалобу.
     */
}


/*
 * Создаём жалобу.
 *
 * Новая жалоба получает статус pending.
 */

$stmt = $pdo->prepare(
    'INSERT INTO post_reports
    (
        post_id,
        user_id,
        reason,
        comment,
        status,
        created_at
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        "pending",
        CURRENT_TIMESTAMP
    )'
);

$stmt->execute(array(
    $postId,
    $userId,
    $reason,
    $comment
));


/*
 * ID жалобы.
 */

$reportId = (int) $pdo->lastInsertId();


/*
 * Получаем актуальное количество
 * жалоб на публикацию.
 *
 * Здесь считаем все жалобы,
 * которые существуют в системе.
 */

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM post_reports
     WHERE post_id = ?'
);

$stmt->execute(array(
    $postId
));

$reportsCount = (int) $stmt->fetchColumn();


/*
 * Возвращаем JSON.
 */

header(
    'Content-Type: application/json; charset=UTF-8'
);

echo json_encode(
    array(
        'success' => true,
        'report_id' => $reportId,
        'reports_count' => $reportsCount,
        'message' => 'Жалоба успешно отправлена.'
    ),
    JSON_UNESCAPED_UNICODE
);

exit;

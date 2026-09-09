<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();


/*
 * ID отдельной новости.
 *
 * Если id не указан —
 * показываем список новостей.
 */
$newsId = 0;

if (isset($_GET['id'])) {
    $newsId = (int) $_GET['id'];
}

 

/*
 * Выбранная категория.
 *
 * Используется только
 * при отображении списка.
 */
$categorySlug = '';

if (isset($_GET['category'])) {
    $categorySlug = trim($_GET['category']);
}


/*
 * Безопасный вывод.
 */
function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
 * =========================================================
 * РЕЖИМ 1.
 * Открыта конкретная новость.
 * /news/?id=16
 * =========================================================
 */

if ($newsId > 0) {

    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.user_id,
            p.title,
            p.content,
            p.visibility,
            p.in_news_feed,
            p.news_category_id,
            p.created_at,
            p.updated_at,

            u.username,

            up.profile_slug,
            up.display_name,
            up.avatar,

            nc.name AS news_category_name,
            nc.slug AS news_category_slug

         FROM posts p

         INNER JOIN users u
            ON u.id = p.user_id

         INNER JOIN user_profiles up
            ON up.user_id = u.id

         LEFT JOIN news_categories nc
            ON nc.id = p.news_category_id

         WHERE p.id = ?
           AND p.visibility = "public"
           AND u.is_blocked = 0

         LIMIT 1'
    );

    $stmt->execute(
        array($newsId)
    );

    $post = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    /*
     * Новость не найдена.
     */
    if (!$post) {

        http_response_code(404);

        exit(
            'Новость не найдена.'
        );
    }
/*
 * Права текущего пользователя
 * на эту публикацию.
 */

$currentUserId = currentUserId();

$isAuthor =
    (int) $currentUserId ===
    (int) $post['user_id'];

$canEditPost =
    $isAuthor ||
    can('posts.edit');

$canDeletePost =
    $isAuthor ||
    can('posts.delete');

    /*
     * Заголовок страницы.
     */
    $pageTitle = !empty($post['title'])
        ? $post['title']
        : 'Новость';

}


/*
 * =========================================================
 * РЕЖИМ 2.
 * Список новостей.
 * /news/
 * =========================================================
 */

else {

    /*
     * Получаем активные категории.
     */
    $stmt = $pdo->prepare(
        'SELECT
            id,
            name,
            slug

         FROM news_categories

         WHERE is_active = 1

         ORDER BY
            sort_order ASC,
            id ASC'
    );

    $stmt->execute();

    $newsCategories =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
     * Ищем выбранную категорию.
     */
    $selectedCategory = null;

    foreach (
        $newsCategories as $category
    ) {

        if (
            $category['slug'] ===
            $categorySlug
        ) {

            $selectedCategory =
                $category;

            break;
        }
    }


    /*
     * Получаем список новостей
     * без выбранной категории.
     */
    if ($selectedCategory) {

        $stmt = $pdo->prepare(
            'SELECT
                p.id,
                p.user_id,
                p.title,
                p.content,
                p.created_at,

                u.username,

                up.profile_slug,
                up.display_name,
                up.avatar,

                nc.name AS news_category_name,
                nc.slug AS news_category_slug

             FROM posts p

             INNER JOIN users u
                ON u.id = p.user_id

             INNER JOIN user_profiles up
                ON up.user_id = u.id

             INNER JOIN news_categories nc
                ON nc.id = p.news_category_id

             WHERE p.visibility = "public"
               AND p.in_news_feed = 1
               AND p.news_category_id = ?
               AND u.is_blocked = 0
               AND nc.is_active = 1

             ORDER BY p.id DESC

             LIMIT 50'
        );

        $stmt->execute(
            array(
                $selectedCategory['id']
            )
        );

    } else {

        $stmt = $pdo->prepare(
            'SELECT
                p.id,
                p.user_id,
                p.title,
                p.content,
                p.created_at,

                u.username,

                up.profile_slug,
                up.display_name,
                up.avatar,

                nc.name AS news_category_name,
                nc.slug AS news_category_slug

             FROM posts p

             INNER JOIN users u
                ON u.id = p.user_id

             INNER JOIN user_profiles up
                ON up.user_id = u.id

             LEFT JOIN news_categories nc
                ON nc.id = p.news_category_id

             WHERE p.visibility = "public"
               AND p.in_news_feed = 1
               AND u.is_blocked = 0

             ORDER BY p.id DESC

             LIMIT 50'
        );

        $stmt->execute();
    }


    $posts =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $pageTitle = 'Новости KUPITETUT';
}

?>

<!DOCTYPE html>

<html lang="ru">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>

<?php

if ($newsId > 0) {

    echo e($pageTitle);

} else {

    if ($selectedCategory) {

        echo e(
            $selectedCategory['name']
        );

        echo ' — ';

    }

    echo 'Новости KUPITETUT';
}

?>


</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background:
        #f2f4f7;

    color:
        #222;
}

.topbar {
    background:
        #222;

    color:
        #fff;

    padding:
        14px 20px;
}

.topbar-inner {
    max-width:
        1000px;

    margin:
        0 auto;
}

.topbar a {
    color:
        #fff;

    text-decoration:
        none;

    margin-right:
        20px;
}

.container {
    max-width:
        850px;

    margin:
        30px auto;

    padding:
        0 15px;
}

.page-header {
    margin-bottom:
        25px;
}

.page-header h1 {
    margin:
        0 0 8px;

    font-size:
        30px;
}

.page-header p {
    margin:
        0;

    color:
        #777;
}

.categories {
    display:
        flex;

    gap:
        8px;

    flex-wrap:
        wrap;

    margin:
        20px 0 25px;
}

.category {
    display:
        inline-block;

    padding:
        9px 13px;

    border-radius:
        8px;

    background:
        #fff;

    color:
        #444;

    text-decoration:
        none;

    border:
        1px solid #e5e7eb;
}

.category:hover {
    background:
        #f8fafc;
}

.category.active {
    background:
        #4f46e5;

    color:
        #fff;

    border-color:
        #4f46e5;
}

.feed {
    display:
        flex;

    flex-direction:
        column;

    gap:
        18px;
}

.news-card {
    background:
        #fff;

    border-radius:
        12px;

    padding:
        22px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,.06);
}

.news-header {
    display:
        flex;

    align-items:
        center;

    margin-bottom:
        15px;
}

.avatar {
    width:
        50px;

    height:
        50px;

    border-radius:
        50%;

    object-fit:
        cover;

    background:
        #ddd;

    margin-right:
        12px;
}

.avatar-placeholder {
    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        23px;
}

.author-name {
    font-weight:
        bold;

    color:
        #222;

    text-decoration:
        none;
}

.author-name:hover {
    text-decoration:
        underline;
}

.author-username {
    color:
        #777;

    font-size:
        13px;

    margin-top:
        3px;
}

.news-date {
    color:
        #888;

    font-size:
        12px;

    margin-top:
        3px;
}

.news-category {
    display:
        inline-block;

    margin-bottom:
        10px;

    padding:
        5px 9px;

    border-radius:
        6px;

    background:
        #eef2ff;

    color:
        #4338ca;

    font-size:
        12px;

    text-decoration:
        none;
}

.news-category:hover {
    background:
        #e0e7ff;
}

.news-title {
    margin:
        5px 0 12px;

    font-size:
        23px;

    line-height:
        1.35;
}

.news-title a {
    color:
        #222;

    text-decoration:
        none;
}

.news-title a:hover {
    color:
        #4f46e5;
}

.news-excerpt {
    line-height:
        1.65;

    white-space:
        pre-wrap;

    word-wrap:
        break-word;
}

.news-content {
    line-height:
        1.7;

    white-space:
        pre-wrap;

    word-wrap:
        break-word;

    font-size:
        16px;
}

.read-more {
    display:
        inline-block;

    margin-top:
        13px;

    color:
        #4f46e5;

    font-weight:
        bold;

    text-decoration:
        none;
}

.read-more:hover {
    text-decoration:
        underline;
}

.post-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #eee;
}

.post-button {
    display: inline-block;
    padding: 10px 16px;
    border: 0;
    border-radius: 8px;
    background: #4f46e5;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.post-button:hover {
    background: #4338ca;
}

.post-button.delete {
    background: #dc2626;
}

.post-button.delete:hover {
    background: #b91c1c;
}

.post-button.secondary {
    background: #eee;
    color: #222;
}

.post-button.secondary:hover {
    background: #e5e7eb;
}



.back-link {
    display:
        inline-block;

    margin-bottom:
        20px;

    color:
        #4f46e5;

    font-weight:
        bold;

    text-decoration:
        none;
}

.back-link:hover {
    text-decoration:
        underline;
}

.empty {
    background:
        #fff;

    border-radius:
        12px;

    padding:
        40px 20px;

    text-align:
        center;

    color:
        #777;
}

@media (
    max-width: 600px
) {

    .topbar {
        line-height:
            2;
    }

    .container {
        margin-top:
            20px;
    }

    .news-card {
        padding:
            17px;
    }

    .news-title {
        font-size:
            21px;
    }


</style>

</head>


<body>


<div class="topbar">

<div class="topbar-inner">

    <a href="/index.php">
        🏠 KUPITETUT
    </a>

    <a href="/news/">
        📰 Новости
    </a>

    <a href="/chat.php">
        💬 Чат
    </a>

    <a href="/users.php">
        🔎 Пользователи
    </a>


    <?php if (isLoggedIn()): ?>

        <a href="/private.php">
            💌 Сообщения
        </a>

        <a href="/friends.php">
            👥 Друзья
        </a>

    <?php else: ?>

        <a href="/login.php">
            🔐 Войти
        </a>

        <a href="/register.php">
            📝 Регистрация
        </a>

    <?php endif; ?>

</div>

</div>


<div class="container">


<?php if ($newsId > 0): ?>


    <!-- =================================================
         ОТДЕЛЬНАЯ НОВОСТЬ
         ================================================= -->


    <a
        class="back-link"
        href="/news/"
    >
        ← Все новости
    </a>


    <article class="news-card">


        <div class="news-header">


            <?php if (
                !empty(
                    $post['avatar']
                )
            ): ?>

                <img
                    class="avatar"
                    src="<?php echo e(
                        $post['avatar']
                    ); ?>"
                    alt=""
                >

            <?php else: ?>

                <div
                    class="
                        avatar
                        avatar-placeholder
                    "
                >
                    👤
                </div>

            <?php endif; ?>


            <div>

                <a
                    class="author-name"
                    href="/users/<?php echo
                        rawurlencode(
                            $post['profile_slug']
                        );
                    ?>"
                >

                    <?php echo e(
                        $post['display_name']
                            ? $post['display_name']
                            : $post['username']
                    ); ?>

                </a>


                <div
                    class="author-username"
                >

                    @<?php echo e(
                        $post['username']
                    ); ?>

                </div>


                <div
                    class="news-date"
                >

                    <?php echo e(
                        $post['created_at']
                    ); ?>

                </div>

            </div>

        </div>


        <?php if (
            !empty(
                $post['news_category_name']
            )
        ): ?>

            <a
                class="news-category"
                href="/news/?category=<?php echo
                    rawurlencode(
                        $post[
                            'news_category_slug'
                        ]
                    );
                ?>"
            >

                📰

                <?php echo e(
                    $post[
                        'news_category_name'
                    ]
                ); ?>

            </a>

        <?php endif; ?>


        <?php if (
            !empty(
                $post['title']
            )
        ): ?>

            <h1
                class="news-title"
            >

                <?php echo e(
                    $post['title']
                ); ?>

            </h1>

        <?php endif; ?>


        <div
            class="news-content"
        >

            <?php echo e(
                $post['content']
            ); ?>

        </div>

<?php if (
    $canEditPost ||
    $canDeletePost
): ?>

    <div class="post-actions">

        <?php if ($canEditPost): ?>

            <a
                class="post-button"
                href="/users/edit_post.php?id=<?php echo
                    (int) $post['id'];
                ?>"
            >
                ✏️ Редактировать
            </a>

        <?php endif; ?>


        <?php if ($canDeletePost): ?>

            <form
                method="POST"
                action="/users/delete_post.php"
                onsubmit="return confirm('Вы действительно хотите удалить эту публикацию?');"
                style="margin: 0;"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e(
                        csrfToken()
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="post_id"
                    value="<?php echo
                        (int) $post['id'];
                    ?>"
                >

                <button
                    type="submit"
                    class="post-button delete"
                >
                    🗑️ Удалить
                </button>

            </form>

        <?php endif; ?>

    </div>

<?php endif; ?>
    </article>


<?php else: ?>


    <!-- =================================================
         СПИСОК НОВОСТЕЙ
         ================================================= -->


    <div
        class="page-header"
    >

        <h1>

            📰

            <?php if (
                $selectedCategory
            ): ?>

                <?php echo e(
                    $selectedCategory['name']
                ); ?>

            <?php else: ?>

                Новости KUPITETUT

            <?php endif; ?>

        </h1>


        <p>
            Новости и публикации пользователей KUPITETUT
        </p>

    </div>


    <div class="categories">


        <a
            class="category <?php echo
                $selectedCategory === null
                    ? 'active'
                    : '';
            ?>"
            href="/news/"
        >
            Все
        </a>


        <?php foreach (
            $newsCategories
            as $category
        ): ?>


            <a
                class="category <?php echo
                    (
                        $selectedCategory &&
                        $selectedCategory['id'] ===
                        $category['id']
                    )
                    ? 'active'
                    : '';
                ?>"
                href="/news/?category=<?php echo
                    rawurlencode(
                        $category['slug']
                    );
                ?>"
            >

                <?php echo e(
                    $category['name']
                ); ?>

            </a>


        <?php endforeach; ?>


    </div>


    <div class="feed">


        <?php if (
            count($posts) === 0
        ): ?>


            <div class="empty">


                <?php if (
                    $selectedCategory
                ): ?>

                    В категории
                    «<?php echo e(
                        $selectedCategory['name']
                    ); ?>»
                    пока нет новостей.

                <?php else: ?>

                    Пока нет опубликованных новостей.

                <?php endif; ?>


            </div>


        <?php else: ?>


            <?php foreach (
                $posts as $post
            ): ?>


                <article
                    class="news-card"
                >


                    <div
                        class="news-header"
                    >


                        <?php if (
                            !empty(
                                $post['avatar']
                            )
                        ): ?>

                            <img
                                class="avatar"
                                src="<?php echo e(
                                    $post['avatar']
                                ); ?>"
                                alt=""
                            >

                        <?php else: ?>

                            <div
                                class="
                                    avatar
                                    avatar-placeholder
                                "
                            >
                                👤
                            </div>

                        <?php endif; ?>


                        <div>


                            <a
                                class="author-name"
                                href="/users/<?php echo
                                    rawurlencode(
                                        $post['profile_slug']
                                    );
                                ?>"
                            >

                                <?php echo e(
                                    $post['display_name']
                                        ? $post['display_name']
                                        : $post['username']
                                ); ?>

                            </a>


                            <div
                                class="author-username"
                            >

                                @<?php echo e(
                                    $post['username']
                                ); ?>

                            </div>


                            <div
                                class="news-date"
                            >

                                <?php echo e(
                                    $post['created_at']
                                ); ?>

                            </div>


                        </div>


                    </div>


                    <?php if (
                        !empty(
                            $post[
                                'news_category_name'
                            ]
                        )
                    ): ?>


                        <a
                            class="news-category"
                            href="/news/?category=<?php echo
                                rawurlencode(
                                    $post[
                                        'news_category_slug'
                                    ]
                                );
                            ?>"
                        >

                            📰

                            <?php echo e(
                                $post[
                                    'news_category_name'
                                ]
                            ); ?>

                        </a>
 

                    <?php endif; ?>


                    <?php if (
                        !empty(
                            $post['title']
                        )
                    ): ?>


                        <h2
                            class="news-title"
                        >

                            <a
                                href="/news/?id=<?php echo
                                    (int) $post['id'];
                                ?>"
                            >

                                <?php echo e(
                                    $post['title']
                                ); ?>

                            </a>

                        </h2>


                    <?php endif; ?>


                    <div
                        class="news-excerpt"
                    >


                        <?php
$excerpt = $post['content'];

$hasMoreContent = false;

if (mb_strlen($excerpt) > 600) {

    $excerpt = mb_substr(
        $excerpt,
        0,
        600
    );

    $hasMoreContent = true;
}

echo e($excerpt);

if ($hasMoreContent) {
    echo '…';
}

                        echo e(
                            $excerpt
                        );

                        ?>


                    </div>


  <a
    class="read-more"
    href="/news/?id=<?php echo
        (int) $post['id'];
    ?>"
>
    Читать полностью →
</a>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>


<?php endif; ?>


</div>


</body>

</html>
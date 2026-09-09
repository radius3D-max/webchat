<?php require_once __DIR__ . '/config/auth.php'; /* * Выбранная категория. */ $categorySlug = ''; if (isset($_GET['category'])) { $categorySlug = trim($_GET['category']); } /* * Получаем активные категории. */ $stmt = $pdo->prepare( 'SELECT id, name, slug FROM news_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC' ); $stmt->execute(); $newsCategories = $stmt->fetchAll( PDO::FETCH_ASSOC ); /* * Ищем выбранную категорию. */ $selectedCategory = null; foreach ( $newsCategories as $category ) { if ( $category['slug'] === $categorySlug ) { $selectedCategory = $category; break; } } /* * Получаем публикации. */ if ($selectedCategory) { $stmt = $pdo->prepare( 'SELECT p.id, p.user_id, p.title, p.content, p.created_at, u.username, up.profile_slug, up.display_name, up.avatar, nc.name AS news_category_name, nc.slug AS news_category_slug FROM posts p INNER JOIN users u ON u.id = p.user_id INNER JOIN user_profiles up ON up.user_id = u.id INNER JOIN news_categories nc ON nc.id = p.news_category_id WHERE p.visibility = "public" AND p.in_news_feed = 1 AND p.news_category_id = ? AND u.is_blocked = 0 AND nc.is_active = 1 ORDER BY p.id DESC LIMIT 50' ); $stmt->execute(array( $selectedCategory['id'] )); } else { $stmt = $pdo->prepare( 'SELECT p.id, p.user_id, p.title, p.content, p.created_at, u.username, up.profile_slug, up.display_name, up.avatar, nc.name AS news_category_name, nc.slug AS news_category_slug FROM posts p INNER JOIN users u ON u.id = p.user_id INNER JOIN user_profiles up ON up.user_id = u.id LEFT JOIN news_categories nc ON nc.id = p.news_category_id WHERE p.visibility = "public" AND p.in_news_feed = 1 AND u.is_blocked = 0 ORDER BY p.id DESC LIMIT 50' ); $stmt->execute(); } $posts = $stmt->fetchAll( PDO::FETCH_ASSOC ); /* * Безопасный вывод. */ function e($value) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); } ?> <!DOCTYPE html> <html lang="ru"> <head>
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>

    <?php if ($selectedCategory): ?>

        <?php echo e(
            $selectedCategory['name']
        ); ?>
        —

    <?php endif; ?>

    KUPITETUT

</title>

<style>

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f2f4f7;
        color: #222;
    }

    .topbar {
        background: #222;
        color: #fff;
        padding: 14px 20px;
    }

    .topbar-inner {
        max-width: 1000px;
        margin: 0 auto;
    }

    .topbar a {
        color: #fff;
        text-decoration: none;
        margin-right: 20px;
    }

    .container {
        max-width: 720px;
        margin: 30px auto;
        padding: 0 15px;
    }

    .page-title {
        margin-bottom: 20px;
    }

    .page-title h1 {
        margin: 0 0 6px;
        font-size: 28px;
    }

    .page-title p {
        margin: 0;
        color: #777;
    }

    .categories {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 20px 0;
    }

    .category {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 8px;
        background: #fff;
        color: #444;
        text-decoration: none;
        border: 1px solid #e5e7eb;
    }

    .category:hover {
        background: #f8fafc;
    }

    .category.active {
        background: #4f46e5;
        color: #fff;
        border-color: #4f46e5;
    }

    .feed {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .post {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }

    .post-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        background: #ddd;
        margin-right: 12px;
    }

    .avatar-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 23px;
    }

    .author-name {
        font-weight: bold;
        color: #222;
        text-decoration: none;
    }

    .author-name:hover {
        text-decoration: underline;
    }

    .author-username {
        color: #777;
        font-size: 13px;
        margin-top: 3px;
    }

    .post-date {
        color: #888;
        font-size: 12px;
        margin-top: 3px;
    }

    .post-category {
        display: inline-block;
        margin-bottom: 10px;
        padding: 5px 9px;
        border-radius: 6px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 12px;
        text-decoration: none;
    }

    .post-category:hover {
        background: #e0e7ff;
    }

    .post-content {
        line-height: 1.65;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .empty {
        background: #fff;
        border-radius: 12px;
        padding: 35px 20px;
        text-align: center;
        color: #777;
    }

    @media (max-width: 600px) {

        .topbar {
            line-height: 2;
        }

        .container {
            margin-top: 20px;
        }

        .post {
            padding: 16px;
        }

        .category {
            font-size: 14px;
        }

    }
.post-title {
    margin: 5px 0 12px;
    font-size: 22px;
    line-height: 1.35;
}

.post-title a {
    color: #222;
    text-decoration: none;
}

.post-title a:hover {
    color: #4f46e5;
}

.post-excerpt {
    line-height: 1.65;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.read-more {
    display: inline-block;
    margin-top: 12px;
    color: #4f46e5;
    font-weight: bold;
    text-decoration: none;
}

.read-more:hover {
    text-decoration: underline;
}
</style>

</head> <body> <div class="topbar">
<div class="topbar-inner">

    <a href="/index.php">
        🏠 KUPITETUT
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

</div> <div class="container">
<div class="page-title">

    <h1>
        📰

        <?php if ($selectedCategory): ?>

            <?php echo e(
                $selectedCategory['name']
            ); ?>

        <?php else: ?>

            Новости KUPITETUT

        <?php endif; ?>

    </h1>

    <p>
        Публичные публикации пользователей KUPITETUT
    </p>

</div>


<div class="categories">

    <a
        class="category <?php echo
            $selectedCategory === null
                ? 'active'
                : '';
        ?>"
        href="/index.php"
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
            href="/index.php?category=<?php echo
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

    <?php if (count($posts) === 0): ?>

        <div class="empty">

            <?php if ($selectedCategory): ?>

                В категории
                «<?php echo e(
                    $selectedCategory['name']
                ); ?>»
                пока нет публикаций.

            <?php else: ?>

                Пока в общей ленте нет публикаций.

            <?php endif; ?>

        </div>

    <?php else: ?>


        <?php foreach ($posts as $post): ?>

            <article class="post">

                <div class="post-header">

                    <?php if (!empty($post['avatar'])): ?>

                        <img
                            class="avatar"
                            src="<?php echo e(
                                $post['avatar']
                            ); ?>"
                            alt=""
                        >

                    <?php else: ?>

                        <div class="avatar avatar-placeholder">
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


                        <div class="author-username">

                            @<?php echo e(
                                $post['username']
                            ); ?>

                        </div>


                        <div class="post-date">

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
                        class="post-category"
                        href="/index.php?category=<?php echo
                            rawurlencode(
                                $post[
                                    'news_category_slug'
                                ]
                            );
                        ?>"
                    >

                        📰 <?php echo e(
                            $post[
                                'news_category_name'
                            ]
                        ); ?>

                    </a>

                <?php endif; ?>


               <?php if (!empty($post['title'])): ?>

    <div class="post-title">

        <a
            href="/news/?id=<?php echo (int) $post['id']; ?>"
        >
            <?php echo e(
                $post['title']
            ); ?>
        </a>

    </div>

<?php endif; ?>


<div class="post-excerpt">

    <?php
    $excerpt = $post['content'];

    if (mb_strlen($excerpt) > 300) {
        $excerpt = mb_substr(
            $excerpt,
            0,
            300
        ) . '…';
    }

    echo e($excerpt);
    ?>

</div>


<a
    class="read-more"
    href="/news/?id=<?php echo (int) $post['id']; ?>"
>
    Читать полностью →
</a>

            </article>

        <?php endforeach; ?>


    <?php endif; ?>

</div>

</div> </body> </html>
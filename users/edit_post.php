<?php require_once __DIR__ . '/../config/auth.php'; requireLogin(); checkBlocked(); updateLastActivity(); $postId = 0; if (isset($_GET['id'])) { $postId = (int) $_GET['id']; } if ($postId <= 0) { http_response_code(404); exit('Публикация не найдена.'); } /* * Получаем публикацию. */ $stmt = $pdo->prepare( 'SELECT p.id, p.user_id, p.title, p.content, p.visibility, p.in_news_feed, p.news_category_id, p.created_at, p.updated_at, u.username, u.role, u.is_blocked, up.profile_slug, up.display_name FROM posts p INNER JOIN users u ON u.id = p.user_id INNER JOIN user_profiles up ON up.user_id = u.id WHERE p.id = ? LIMIT 1' ); $stmt->execute(array( $postId )); $post = $stmt->fetch( PDO::FETCH_ASSOC ); if (!$post) { http_response_code(404); exit('Публикация не найдена.'); } /* * Проверяем, имеет ли пользователь * право редактировать публикацию. * * Автор может редактировать свою публикацию. * * Пользователь с разрешением posts.edit * может редактировать чужие публикации. */ $currentUserId = currentUserId(); $isAuthor = (int) $currentUserId === (int) $post['user_id']; $canEdit = $isAuthor || can('posts.edit'); if (!$canEdit) { http_response_code(403); exit('У вас нет права редактировать эту публикацию.'); } /* * Получаем активные категории новостей. */ $stmt = $pdo->prepare( 'SELECT id, name, slug FROM news_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC' ); $stmt->execute(); $newsCategories = $stmt->fetchAll( PDO::FETCH_ASSOC ); /* * Ошибка редактирования. */ $postError = ''; /* * Сохранение изменений. */ if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) { /* * CSRF. */ $csrf = ''; if (isset($_POST['csrf_token'])) { $csrf = $_POST['csrf_token']; } if (!verifyCsrfToken($csrf)) { $postError = 'Ошибка безопасности. Обновите страницу и попробуйте снова.'; } else { /* * Получаем данные формы. */ $content = ''; if (isset($_POST['wall_content'])) { $content = trim( $_POST['wall_content'] ); } $title = ''; if (isset($_POST['post_title'])) { $title = trim( $_POST['post_title'] ); } /* * Участие в общей ленте. */ $inNewsFeed = 0; if ( isset($_POST['in_news_feed']) && $_POST['in_news_feed'] === '1' ) { $inNewsFeed = 1; } /* * Категория. */ $newsCategoryId = null; if ( isset($_POST['news_category_id']) && $_POST['news_category_id'] !== '' ) { $newsCategoryId = (int) $_POST['news_category_id']; if ($newsCategoryId <= 0) { $newsCategoryId = null; } } /* * Проверяем текст. */ if ($content === '') { $postError = 'Введите текст публикации.'; } elseif ( mb_strlen($content) > 5000 ) { $postError = 'Публикация не может быть длиннее 5000 символов.'; /* * Для новости заголовок обязателен. */ } elseif ( $inNewsFeed === 1 && $title === '' ) { $postError = 'Для публикации в общей ленте необходимо указать заголовок.'; /* * Проверяем длину заголовка. */ } elseif ( mb_strlen($title) > 255 ) { $postError = 'Заголовок не может быть длиннее 255 символов.'; /* * Для новости категория обязательна. */ } elseif ( $inNewsFeed === 1 && $newsCategoryId === null ) { $postError = 'Выберите категорию для публикации в общей ленте.'; } else { /* * Проверяем категорию. */ if ($newsCategoryId !== null) { $stmt = $pdo->prepare( 'SELECT id FROM news_categories WHERE id = ? AND is_active = 1 LIMIT 1' ); $stmt->execute(array( $newsCategoryId )); if (!$stmt->fetch()) { $newsCategoryId = null; } } /* * Если категория стала недоступной. */ if ( $inNewsFeed === 1 && $newsCategoryId === null ) { $postError = 'Выбранная категория недоступна. Обновите страницу.'; } else { /* * Обычная публикация * не должна иметь заголовка * или категории новости. */ if ($inNewsFeed === 0) { $title = null; $newsCategoryId = null; } /* * Сохраняем изменения. */ $stmt = $pdo->prepare( 'UPDATE posts SET title = ?, content = ?, in_news_feed = ?, news_category_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1' ); $stmt->execute(array( $title, $content, $inNewsFeed, $newsCategoryId, $postId )); /* * После сохранения * открываем страницу новости. * * Сейчас используем обычный URL * без ЧПУ. */ header( 'Location: /news/?id=' . (int) $postId ); exit; } } } } /* * Безопасный вывод. */ function e($value) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); } ?> <!DOCTYPE html> <html lang="ru"> <head> <meta charset="UTF-8">
<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

<title> Редактирование публикации — KUPITETUT </title> <style> * { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; background: #f2f4f7; color: #222; } .topbar { background: #222; color: #fff; padding: 14px 20px; } .topbar-inner { max-width: 1000px; margin: 0 auto; } .topbar a { color: #fff; text-decoration: none; margin-right: 20px; } .container { max-width: 720px; margin: 30px auto; padding: 0 15px; } .card { background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,.06); } h1 { margin: 0 0 20px; font-size: 26px; } .post-info { color: #777; font-size: 13px; margin-bottom: 20px; } .field { margin-bottom: 15px; } .field label { display: block; margin-bottom: 7px; font-weight: bold; } .post-title-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: Arial, sans-serif; font-size: 16px; } textarea { width: 100%; min-height: 180px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; resize: vertical; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.5; } .news-options { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 15px; } .news-checkbox { margin-bottom: 12px; } .news-checkbox label { cursor: pointer; font-weight: bold; } .news-category label { display: block; color: #555; font-size: 14px; margin-bottom: 6px; } .news-category select { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: #fff; font-size: 15px; } .post-error { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 12px; margin-bottom: 15px; } .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; } .button { display: inline-block; padding: 11px 18px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; text-decoration: none; cursor: pointer; font-size: 15px; } .button.secondary { background: #eee; color: #222; } @media (max-width: 600px) { .container { margin-top: 20px; } .card { padding: 17px; } } </style> </head> <body> <div class="topbar">
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

    <a href="/private.php">
        💌 Сообщения
    </a>

    <a href="/friends.php">
        👥 Друзья
    </a>

</div>

</div> <div class="container">
<div class="card">

    <h1>
        ✏️ Редактирование публикации
    </h1>

    <div class="post-info">

        Автор:

        <?php echo e(
            $post['display_name']
                ? $post['display_name']
                : $post['username']
        ); ?>

    </div>


    <?php if ($postError !== ''): ?>

        <div class="post-error">

            <?php echo e(
                $postError
            ); ?>

        </div>

    <?php endif; ?>


    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e(
                csrfToken()
            ); ?>"
        >


        <div class="field">

            <label for="post_title">
                Заголовок
            </label>

            <input
                type="text"
                id="post_title"
                name="post_title"
                maxlength="255"
                class="post-title-input"
                value="<?php echo e(
                    $post['title']
                ); ?>"
                placeholder="Заголовок новости"
            >

        </div>


        <div class="field">

            <label for="wall_content">
                Текст публикации
            </label>

            <textarea
                id="wall_content"
                name="wall_content"
                maxlength="5000"
                required
            ><?php echo e(
                $post['content']
            ); ?></textarea>

        </div>


        <div class="news-options">

            <div class="news-checkbox">

                <label>

                    <input
                        type="checkbox"
                        name="in_news_feed"
                        value="1"
                        <?php echo
                            (int) $post['in_news_feed'] === 1
                                ? 'checked'
                                : '';
                        ?>
                    >

                    📰 Участвовать в общей ленте

                </label>

            </div>


            <div class="news-category">

                <label for="news_category_id">
                    Категория публикации
                </label>

                <select
                    name="news_category_id"
                    id="news_category_id"
                >

                    <option value="">
                        — Выберите категорию —
                    </option>

                    <?php foreach (
                        $newsCategories
                        as $newsCategory
                    ): ?>

                        <option
                            value="<?php echo
                                (int) $newsCategory['id'];
                            ?>"
                            <?php echo
                                (
                                    $post['news_category_id'] !== null &&
                                    (int) $post['news_category_id'] ===
                                    (int) $newsCategory['id']
                                )
                                    ? 'selected'
                                    : '';
                            ?>
                        >

                            <?php echo e(
                                $newsCategory['name']
                            ); ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <div class="actions">

            <button
                type="submit"
                class="button"
            >
                💾 Сохранить изменения
            </button>


            <a
                class="button secondary"
                href="/news/?id=<?php echo
                    (int) $post['id'];
                ?>"
            >
                Отмена
            </a>

        </div>

    </form>

</div>

</div> </body> </html>
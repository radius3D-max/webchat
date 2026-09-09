<?php require_once __DIR__ . '/../config/auth.php'; requireLogin(); checkBlocked(); updateLastActivity(); /* * ========================================================= * ПРОФИЛЬ * ========================================================= */ $slug = ''; if (isset($_GET['slug'])) { $slug = trim($_GET['slug']); } if ($slug === '') { http_response_code(404); exit('Профиль не найден'); } /* * Получаем профиль. */ $stmt = $pdo->prepare( 'SELECT u.id, u.username, u.role, u.last_activity, u.created_at, p.profile_slug, p.display_name, p.gender, p.birth_date, p.phone, p.bio, p.avatar, p.cover_image, c.name AS city_name, cat.name AS category_name FROM user_profiles p INNER JOIN users u ON u.id = p.user_id LEFT JOIN cities c ON c.id = u.city_id LEFT JOIN categories cat ON cat.id = u.category_id WHERE p.profile_slug = ? AND u.is_blocked = 0 LIMIT 1' ); $stmt->execute(array($slug)); $user = $stmt->fetch(PDO::FETCH_ASSOC); if (!$user) { http_response_code(404); exit('Пользователь не найден'); } /* * ========================================================= * ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ * ========================================================= */ function e($value) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); } /* * ========================================================= * ВОЗРАСТ * ========================================================= */ $age = null; if ( !empty($user['birth_date']) && $user['birth_date'] !== '0000-00-00' ) { try { $birthDate = new DateTime( $user['birth_date'] ); $today = new DateTime(); $age = $today->diff( $birthDate )->y; } catch (Exception $e) { $age = null; } } /* * ========================================================= * ОНЛАЙН * ========================================================= */ $isOnline = false; if (!empty($user['last_activity'])) { $lastActivity = strtotime( $user['last_activity'] ); if ( $lastActivity !== false && $lastActivity >= time() - 300 ) { $isOnline = true; } } /* * ========================================================= * МОЙ ПРОФИЛЬ * ========================================================= */ $myId = currentUserId(); $isMyProfile = ((int) $myId === (int) $user['id']); /* * ========================================================= * СТАТУС ДРУЖБЫ * ========================================================= */ $friendStatus = 'none'; $friendRequestId = 0; if (!$isMyProfile) { $stmt = $pdo->prepare( 'SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1' ); $stmt->execute(array( $myId, $user['id'], $user['id'], $myId )); if ($stmt->fetch()) { $friendStatus = 'friends'; } else { $stmt = $pdo->prepare( 'SELECT id, sender_id, receiver_id, status FROM friend_requests WHERE ( sender_id = ? AND receiver_id = ? ) OR ( sender_id = ? AND receiver_id = ? ) AND status = "pending" ORDER BY id DESC LIMIT 1' ); $stmt->execute(array( $myId, $user['id'], $user['id'], $myId )); $request = $stmt->fetch( PDO::FETCH_ASSOC ); if ($request) { $friendRequestId = (int) $request['id']; if ( (int) $request['sender_id'] === (int) $myId ) { $friendStatus = 'outgoing'; } else { $friendStatus = 'incoming'; } } } } /* * ========================================================= * КАТЕГОРИИ НОВОСТЕЙ * ========================================================= */ $stmt = $pdo->prepare( 'SELECT id, name, slug FROM news_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC' ); $stmt->execute(); $newsCategories = $stmt->fetchAll( PDO::FETCH_ASSOC ); /* * ========================================================= * ОШИБКА ПУБЛИКАЦИИ * ========================================================= */ $postError = ''; /* * ========================================================= * СОЗДАНИЕ НОВОЙ ПУБЛИКАЦИИ * ========================================================= */ if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wall_content']) && $isMyProfile ) { /* * CSRF. */ $csrf = ''; if (isset($_POST['csrf_token'])) { $csrf = $_POST['csrf_token']; } if (!verifyCsrfToken($csrf)) { $postError = 'Ошибка безопасности. Обновите страницу и попробуйте снова.'; } else { /* * Текст. */ $content = ''; if (isset($_POST['wall_content'])) { $content = trim( $_POST['wall_content'] ); } /* * Заголовок. */ $title = ''; if (isset($_POST['post_title'])) { $title = trim( $_POST['post_title'] ); } /* * Общая лента. */ $inNewsFeed = 0; if ( isset($_POST['in_news_feed']) && $_POST['in_news_feed'] === '1' ) { $inNewsFeed = 1; } /* * Категория. */ $newsCategoryId = null; if ( isset($_POST['news_category_id']) && $_POST['news_category_id'] !== '' ) { $newsCategoryId = (int) $_POST['news_category_id']; if ($newsCategoryId <= 0) { $newsCategoryId = null; } } /* * Проверка текста. */ if ($content === '') { $postError = 'Введите текст публикации.'; } elseif ( mb_strlen($content) > 5000 ) { $postError = 'Публикация не может быть длиннее 5000 символов.'; } elseif ( $inNewsFeed === 1 && $title === '' ) { $postError = 'Для публикации в общей ленте необходимо указать заголовок.'; } elseif ( mb_strlen($title) > 255 ) { $postError = 'Заголовок не может быть длиннее 255 символов.'; } elseif ( $inNewsFeed === 1 && $newsCategoryId === null ) { $postError = 'Выберите категорию для публикации в общей ленте.'; } else { /* * Проверяем категорию. */ if ($newsCategoryId !== null) { $stmt = $pdo->prepare( 'SELECT id FROM news_categories WHERE id = ? AND is_active = 1 LIMIT 1' ); $stmt->execute(array( $newsCategoryId )); if (!$stmt->fetch()) { $newsCategoryId = null; } } /* * Категория стала недоступной. */ if ( $inNewsFeed === 1 && $newsCategoryId === null ) { $postError = 'Выбранная категория недоступна. Обновите страницу.'; } else { /* * Для обычной публикации * заголовок и категория не нужны. */ if ($inNewsFeed === 0) { $title = null; $newsCategoryId = null; } /* * Создаём публикацию. */ $stmt = $pdo->prepare( 'INSERT INTO posts ( user_id, title, content, visibility, in_news_feed, news_category_id, created_at, updated_at ) VALUES ( ?, ?, ?, "public", ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )' ); $stmt->execute(array( $user['id'], $title, $content, $inNewsFeed, $newsCategoryId )); /* * Возвращаемся на профиль. */ header( 'Location: /users/' . rawurlencode( $user['profile_slug'] ) ); exit; } } } } /* * ========================================================= * ПОЛУЧАЕМ ПОСТЫ СТЕНЫ * * Сразу получаем количество: * просмотров * лайков * комментариев * жалоб * ========================================================= */ $stmt = $pdo->prepare( 'SELECT p.id, p.user_id, p.title, p.content, p.visibility, p.in_news_feed, p.news_category_id, p.created_at, p.updated_at, nc.name AS news_category_name, ( SELECT COUNT(*) FROM post_views pv WHERE pv.post_id = p.id ) AS views_count, ( SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id ) AS likes_count, ( SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id ) AS comments_count, ( SELECT COUNT(*) FROM post_reports pr WHERE pr.post_id = p.id ) AS reports_count FROM posts p LEFT JOIN news_categories nc ON nc.id = p.news_category_id WHERE p.user_id = ? AND p.visibility = "public" ORDER BY p.id DESC LIMIT 50' ); $stmt->execute(array( $user['id'] )); $posts = $stmt->fetchAll( PDO::FETCH_ASSOC ); ?> <!DOCTYPE html> <html lang="ru"> <head> <meta charset="UTF-8">
<meta
name="viewport"
content="width=device-width, initial-scale=1.0"

<title> <?php echo e( $user['display_name'] ? $user['display_name'] : $user['username'] ); ?> — KUPITETUT </title> <style> * { box-sizing: border-box; } body { margin: 0; font-family: Arial, sans-serif; background: #f2f4f7; color: #222; } .topbar { background: #222; color: #fff; padding: 14px 20px; } .topbar-inner { max-width: 1000px; margin: 0 auto; } .topbar a { color: #fff; text-decoration: none; margin-right: 20px; } .container { max-width: 900px; margin: 30px auto; padding: 0 15px; } .profile { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.08); } .cover { height: 220px; background: linear-gradient( 135deg, #4f46e5, #9333ea ); } .profile-main { padding: 0 30px 30px; } .avatar { width: 120px; height: 120px; border-radius: 50%; border: 5px solid #fff; background: #ddd; margin-top: -60px; object-fit: cover; } .avatar-placeholder { display: flex; align-items: center; justify-content: center; font-size: 45px; } .name { font-size: 28px; font-weight: bold; margin-top: 10px; } .username { color: #777; margin-top: 5px; } .online { color: #16a34a; font-weight: bold; margin-top: 5px; } .offline { color: #888; margin-top: 5px; } .info { margin-top: 25px; } .info-row { padding: 10px 0; border-bottom: 1px solid #eee; } .label { color: #777; display: inline-block; width: 130px; } .actions { margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap; } .button { display: inline-block; padding: 11px 18px; border-radius: 8px; text-decoration: none; background: #4f46e5; color: #fff; border: 0; cursor: pointer; } .button.secondary { background: #eee; color: #222; } .bio { margin-top: 25px; line-height: 1.6; white-space: pre-wrap; } .wall { margin-top: 30px; padding-top: 25px; border-top: 1px solid #eee; } .wall h2 { margin-top: 0; } .wall textarea { width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; resize: vertical; font-family: Arial, sans-serif; font-size: 15px; margin-bottom: 10px; } .news-options { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 12px; } .news-checkbox { margin-bottom: 12px; } .news-checkbox label { cursor: pointer; font-weight: bold; } .news-category { margin-top: 10px; } .news-category label { display: block; color: #555; font-size: 14px; margin-bottom: 6px; } .news-category select { width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: #fff; font-size: 15px; } .post-error { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 12px; margin-bottom: 12px; } .wall-posts { margin-top: 20px; } .wall-post { position: relative; padding: 18px 0; border-top: 1px solid #eee; } .wall-post-date { color: #888; font-size: 13px; margin-bottom: 8px; padding-right: 45px; } .wall-post-title { font-size: 20px; font-weight: bold; margin-bottom: 8px; line-height: 1.4; } .wall-post-content { line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; } .wall-post-content a { color: #4f46e5; } .news-badge { display: inline-block; margin-left: 8px; padding: 3px 7px; border-radius: 5px; background: #eef2ff; color: #4338ca; font-size: 12px; } .empty { color: #888; padding: 20px 0; } /* * ========================================================= * СТАТИСТИКА * ========================================================= */ .post-stats { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 13px; color: #777; font-size: 13px; } .post-stat { white-space: nowrap; } /* * ========================================================= * ЧИТАТЬ ПОЛНОСТЬЮ * ========================================================= */ .read-more { display: inline-block; margin-top: 10px; color: #4f46e5; font-weight: bold; text-decoration: none; } .read-more:hover { text-decoration: underline; } /* * ========================================================= * МЕНЮ ДЕЙСТВИЙ * ========================================================= */ .post-actions { position: absolute; top: 12px; right: 0; } .post-actions-button { width: 38px; height: 38px; border: 0; border-radius: 50%; background: #f3f4f6; color: #333; cursor: pointer; font-size: 23px; line-height: 38px; text-align: center; } .post-actions-button:hover { background: #e5e7eb; } .post-actions-menu { display: none; position: absolute; right: 0; top: 43px; min-width: 190px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 8px 25px rgba(0,0,0,.12); overflow: hidden; z-index: 100; } .post-actions.open .post-actions-menu { display: block; } .post-action-item { display: block; width: 100%; padding: 11px 14px; border: 0; background: #fff; color: #222; text-align: left; text-decoration: none; cursor: pointer; font-size: 14px; } .post-action-item:hover { background: #f8fafc; } .post-action-danger { color: #dc2626; } /* * ========================================================= * МОДАЛЬНОЕ ОКНО * ========================================================= */ .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: center; justify-content: center; padding: 20px; } .modal-overlay.show { display: flex; } .modal { width: 100%; max-width: 430px; background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 15px 50px rgba(0,0,0,.25); } .modal h3 { margin: 0 0 12px; } .modal p { line-height: 1.5; color: #555; } .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; } .modal-close { border: 0; border-radius: 8px; padding: 10px 16px; background: #eee; cursor: pointer; } .modal-confirm { border: 0; border-radius: 8px; padding: 10px 16px; background: #dc2626; color: #fff; cursor: pointer; } /* * ========================================================= * АДАПТИВ * ========================================================= */ @media (max-width: 600px) { .topbar { line-height: 2; } .container { margin-top: 20px; } .profile-main { padding-left: 17px; padding-right: 17px; } .cover { height: 180px; } .wall-post-content { padding-right: 5px; } .post-stats { gap: 10px; } } </style> </head> <body> <div class="topbar"> <div class="topbar-inner">
<a href="../index.php">
    🏠 KUPITETUT
</a>

<a href="../chat.php">
    💬 Чат
</a>

<a href="../users.php">
    🔎 Пользователи
</a>

<a href="../private.php">
    💌 Сообщения
</a>

<a href="../friends.php">
    👥 Друзья
</a>

</div> </div> <div class="container"> <div class="profile">
<div class="cover"></div>


<div class="profile-main">


    <?php if (!empty($user['avatar'])): ?>

        <img
            class="avatar"
            src="<?php echo e($user['avatar']); ?>"
            alt=""
        >

    <?php else: ?>

        <div class="avatar avatar-placeholder">
            👤
        </div>

    <?php endif; ?>


    <div class="name">

        <?php echo e(
            $user['display_name']
                ? $user['display_name']
                : $user['username']
        ); ?>

    </div>


    <div class="username">

        @<?php echo e(
            $user['username']
        ); ?>

    </div>


    <?php if ($isOnline): ?>

        <div class="online">
            🟢 онлайн
        </div>

    <?php else: ?>

        <div class="offline">
            ⚪ офлайн
        </div>

    <?php endif; ?>


    <div class="info">


        <?php if (!empty($user['gender'])): ?>

            <div class="info-row">

                <span class="label">
                    Гендер:
                </span>

                <?php echo e(
                    $user['gender']
                ); ?>

            </div>

        <?php endif; ?>


        <?php if ($age !== null): ?>

            <div class="info-row">

                <span class="label">
                    Возраст:
                </span>

                <?php echo (int) $age; ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($user['city_name'])): ?>

            <div class="info-row">

                <span class="label">
                    Город:
                </span>

                <?php echo e(
                    $user['city_name']
                ); ?>

            </div>

        <?php endif; ?>


        <?php if (!empty($user['category_name'])): ?>

            <div class="info-row">

                <span class="label">
                    Категория:
                </span>

                <?php echo e(
                    $user['category_name']
                ); ?>

            </div>

        <?php endif; ?>


    </div>


    <?php if (!empty($user['bio'])): ?>

        <div class="bio">

            <?php echo e(
                $user['bio']
            ); ?>

        </div>

    <?php endif; ?>


    <?php if ($isMyProfile): ?>


        <div class="wall">

            <h2>
                📝 Моя стена
            </h2>


            <?php if ($postError !== ''): ?>

                <div class="post-error">

                    <?php echo e(
                        $postError
                    ); ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                id="createPostForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e(
                        csrfToken()
                    ); ?>"
                >


                <input
                    type="text"
                    name="post_title"
                    id="post_title"
                    maxlength="255"
                    placeholder="Заголовок новости"
                    class="post-title-input"
                >


                <textarea
                    name="wall_content"
                    id="wall_content"
                    rows="4"
                    maxlength="5000"
                    placeholder="Что нового?"
                    required
                ></textarea>


                <div class="news-options">


                    <div class="news-checkbox">

                        <label>

                            <input
                                type="checkbox"
                                name="in_news_feed"
                                id="in_news_feed"
                                value="1"
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
                                >

                                    <?php echo e(
                                        $newsCategory['name']
                                    ); ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>

                </div>


                <button
                    type="submit"
                    class="button"
                >
                    Опубликовать
                </button>


            </form>

        </div>


    <?php endif; ?>


    <div class="wall-posts">


        <?php if (count($posts) === 0): ?>


            <div class="empty">
                Пока нет публикаций.
            </div>


        <?php else: ?>


            <?php foreach ($posts as $post): ?>


                <div class="wall-post">


                    <?php
                    /*
                     * -------------------------------------------------
                     * Меню действий.
                     * -------------------------------------------------
                     */

                    $postIsAuthor =
                        ((int) $myId === (int) $post['user_id']);

                    ?>


                    <div class="post-actions">


                        <button
                            type="button"
                            class="post-actions-button"
                            onclick="togglePostMenu(this)"
                            aria-label="Действия"
                        >
                            ⋮
                        </button>


                        <div class="post-actions-menu">


                            <?php if ($postIsAuthor): ?>


                                <a
                                    class="post-action-item"
                                    href="edit_post.php?id=<?php echo
                                        (int) $post['id'];
                                    ?>"
                                >
                                    ✏️ Редактировать
                                </a>


                                <button
                                    type="button"
                                    class="
                                        post-action-item
                                        post-action-danger
                                    "
                                    onclick="
                                        confirmDelete(
                                            <?php echo (int) $post['id']; ?>
                                        )
                                    "
                                >
                                    🗑️ Удалить
                                </button>


                            <?php endif; ?>


                            <?php if (!$postIsAuthor): ?>


                                <a
                                    class="post-action-item"
                                    href="../api/report_post.php?id=<?php echo
                                        (int) $post['id'];
                                    ?>"
                                >
                                    🚩 Пожаловаться
                                </a>


                            <?php else: ?>


                                <a
                                    class="post-action-item"
                                    href="../api/report_post.php?id=<?php echo
                                        (int) $post['id'];
                                    ?>"
                                >
                                    🚩 Пожаловаться
                                </a>


                            <?php endif; ?>


                        </div>

                    </div>


                    <div class="wall-post-date">

                        <?php echo e(
                            $post['created_at']
                        ); ?>


                        <?php if (
                            (int) $post['in_news_feed'] === 1
                        ): ?>

                            <span class="news-badge">

                                📰 В общей ленте

                                <?php if (
                                    !empty(
                                        $post['news_category_name']
                                    )
                                ): ?>

                                    · <?php echo e(
                                        $post['news_category_name']
                                    ); ?>

                                <?php endif; ?>

                            </span>

                        <?php endif; ?>

                    </div>


                    <?php if (!empty($post['title'])): ?>

                        <div class="wall-post-title">

                            <?php echo e(
                                $post['title']
                            ); ?>

                        </div>

                    <?php endif; ?>


                    <?php

                    /*
                     * -------------------------------------------------
                     * Краткий текст.
                     *
                     * Показываем максимум 600 символов.
                     * -------------------------------------------------
                     */

                    $fullContent =
                        (string) $post['content'];

                    $shortContent =
                        $fullContent;

                    $isTruncated = false;

                    if (
                        mb_strlen($fullContent) > 600
                    ) {

                        $shortContent =
                            mb_substr(
                                $fullContent,
                                0,
                                600
                            );

                        $shortContent =
                            rtrim($shortContent) .
                            '…';

                        $isTruncated = true;
                    }

                    ?>


                    <div class="wall-post-content">

                        <?php echo e(
                            $shortContent
                        ); ?>

                    </div>


                    <?php if ($isTruncated): ?>

                        <a
                            class="read-more"
                            href="../news/?id=<?php echo
                                (int) $post['id'];
                            ?>"
                        >
                            Читать полностью →
                        </a>

                    <?php endif; ?>


                    <div class="post-stats">


                        <span class="post-stat">
                            👁
                            <?php echo (int) $post['views_count']; ?>
                        </span>


                        <span class="post-stat">
                            ❤️
                            <?php echo (int) $post['likes_count']; ?>
                        </span>


                        <span class="post-stat">
                            💬
                            <?php echo (int) $post['comments_count']; ?>
                        </span>


                        <span class="post-stat">
                            🚩
                            <?php echo (int) $post['reports_count']; ?>
                        </span>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>


    <div class="actions">


        <?php if (!$isMyProfile): ?>


            <a
                class="button"
                href="../private.php?user_id=<?php echo
                    (int) $user['id'];
                ?>"
            >
                💌 Написать сообщение
            </a>


            <?php if ($friendStatus === 'none'): ?>


                <form
                    method="POST"
                    action="../api/friend_request.php"
                >

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?php echo
                            (int) $user['id'];
                        ?>"
                    >

                    <button
                        type="submit"
                        class="button secondary"
                    >
                        👥 Добавить в друзья
                    </button>

                </form>


            <?php elseif (
                $friendStatus === 'outgoing'
            ): ?>


                <span class="button secondary">
                    ⏳ Заявка отправлена
                </span>


            <?php elseif (
                $friendStatus === 'incoming'
            ): ?>


                <form
                    method="POST"
                    action="../api/friend_accept.php"
                >

                    <input
                        type="hidden"
                        name="request_id"
                        value="<?php echo
                            (int) $friendRequestId;
                        ?>"
                    >

                    <button
                        type="submit"
                        class="button"
                    >
                        ✅ Принять заявку
                    </button>

                </form>


            <?php elseif (
                $friendStatus === 'friends'
            ): ?>


                <span class="button secondary">
                    🤝 Вы уже друзья
                </span>


            <?php endif; ?>


        <?php else: ?>


            <a
                class="button"
                href="edit.php"
            >
                ✏️ Редактировать профиль
            </a>


        <?php endif; ?>


    </div>


</div>

</div> </div> <!-- ========================================================= МОДАЛЬНОЕ ОКНО ПОДТВЕРЖДЕНИЯ УДАЛЕНИЯ ========================================================= --> <div class="modal-overlay" id="deleteModal" >
<div class="modal">

    <h3>
        Удалить публикацию?
    </h3>

    <p>
        Публикация будет удалена без возможности восстановления.
    </p>

    <form
        method="POST"
        action="delete_post.php"
        id="deletePostForm"
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
            id="deletePostId"
            value=""
        >

        <div class="modal-actions">

            <button
                type="button"
                class="modal-close"
                onclick="closeDeleteModal()"
            >
                Отмена
            </button>

            <button
                type="submit"
                class="modal-confirm"
            >
                Удалить
            </button>

        </div>

    </form>

</div>

</div> <script> /* * ========================================================= * МЕНЮ ПУБЛИКАЦИИ * ========================================================= */ function togglePostMenu(button) { var current = button.parentElement; var allMenus = document.querySelectorAll( '.post-actions' ); for ( var i = 0; i < allMenus.length; i++ ) { if ( allMenus[i] !== current ) { allMenus[i] .classList .remove('open'); } } current .classList .toggle('open'); } /* * Закрываем меню * при клике вне него. */ document.addEventListener( 'click', function(event) { if ( !event.target.closest( '.post-actions' ) ) { var menus = document.querySelectorAll( '.post-actions' ); for ( var i = 0; i < menus.length; i++ ) { menus[i] .classList .remove('open'); } } } ); /* * ========================================================= * УДАЛЕНИЕ * ========================================================= */ function confirmDelete(postId) { document.getElementById( 'deletePostId' ).value = postId; document.getElementById( 'deleteModal' ).classList.add('show'); } function closeDeleteModal() { document.getElementById( 'deleteModal' ).classList.remove('show'); } document.getElementById( 'deleteModal' ).addEventListener( 'click', function(event) { if ( event.target === this ) { closeDeleteModal(); } } ); /* * ========================================================= * ПРОВЕРКА ФОРМЫ ПУБЛИКАЦИИ * * Ошибки показываем до отправки формы. * Введённые данные не теряются. * ========================================================= */ var createPostForm = document.getElementById( 'createPostForm' ); if (createPostForm) { createPostForm.addEventListener( 'submit', function(event) { var content = document.getElementById( 'wall_content' ); var title = document.getElementById( 'post_title' ); var inNewsFeed = document.getElementById( 'in_news_feed' ); var category = document.getElementById( 'news_category_id' ); /* * Проверяем текст. */ if ( !content.value.trim() ) { event.preventDefault(); alert( 'Введите текст публикации.' ); content.focus(); return; } /* * Проверяем длину. */ if ( content.value.length > 5000 ) { event.preventDefault(); alert( 'Публикация не может быть длиннее 5000 символов.' ); content.focus(); return; } /* * Если публикация идёт * в общую ленту — * заголовок обязателен. */ if ( inNewsFeed.checked && !title.value.trim() ) { event.preventDefault(); alert( 'Для публикации в общей ленте необходимо указать заголовок.' ); title.focus(); return; } /* * Проверяем заголовок. */ if ( title.value.length > 255 ) { event.preventDefault(); alert( 'Заголовок не может быть длиннее 255 символов.' ); title.focus(); return; } /* * Проверяем категорию. */ if ( inNewsFeed.checked && category.value === '' ) { event.preventDefault(); alert( 'Для публикации в общей ленте необходимо выбрать категорию.' ); category.focus(); return; } } ); } </script> </body> </html>
<?php /* * ========================================================= * ЕДИНЫЙ БЛОК ДЕЙСТВИЙ ПУБЛИКАЦИИ * ========================================================= * * Используется: * * /users/profile.php * /news/index.php * * Для каждой публикации перед подключением файла * необходимо определить: * * $post['id'] * $post['user_id'] * */ /* * Проверяем, что публикация существует * и у нас есть необходимые данные. */ if ( !isset($post['id']) || !isset($post['user_id']) ) { return; } /* * ID публикации. */ $postActionId = (int) $post['id']; /* * ID текущего пользователя. */ $postActionUserId = (int) currentUserId(); /* * Автор публикации? */ $postActionIsAuthor = $postActionUserId > 0 && $postActionUserId === (int) $post['user_id']; /* * Права пользователя. * * Автор может редактировать * и удалять собственную публикацию. * * Дополнительные права позволяют * работать с чужими публикациями. */ $postActionCanEdit = $postActionIsAuthor || can('posts.edit'); $postActionCanDelete = $postActionIsAuthor || can('posts.delete'); /* * Пожаловаться на собственную * публикацию нельзя. */ $postActionCanReport = $postActionUserId > 0 && !$postActionIsAuthor && can('report.create'); /* * ========================================================= * СТАТИСТИКА * ========================================================= */ /* * Просмотры. */ $stmtPostViews = $pdo->prepare( 'SELECT COUNT(*) FROM post_views WHERE post_id = ?' ); $stmtPostViews->execute( array($postActionId) ); $postActionViews = (int) $stmtPostViews->fetchColumn(); /* * Лайки. */ $stmtPostLikes = $pdo->prepare( 'SELECT COUNT(*) FROM post_likes WHERE post_id = ?' ); $stmtPostLikes->execute( array($postActionId) ); $postActionLikes = (int) $stmtPostLikes->fetchColumn(); /* * Проверяем, * поставил ли текущий пользователь лайк. */ $postActionLiked = false; if ($postActionUserId > 0) { $stmtPostLiked = $pdo->prepare( 'SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1' ); $stmtPostLiked->execute( array( $postActionId, $postActionUserId ) ); $postActionLiked = (bool) $stmtPostLiked->fetchColumn(); } /* * Комментарии. * * Считаем все комментарии публикации, * включая ответы. */ $stmtPostComments = $pdo->prepare( 'SELECT COUNT(*) FROM post_comments WHERE post_id = ?' ); $stmtPostComments->execute( array($postActionId) ); $postActionComments = (int) $stmtPostComments->fetchColumn(); /* * Жалобы. * * Пока показываем количество * всех созданных жалоб. * * Позже для модераторов можно * отдельно сделать счётчик * только открытых жалоб. */ $stmtPostReports = $pdo->prepare( 'SELECT COUNT(*) FROM post_reports WHERE post_id = ?' ); $stmtPostReports->execute( array($postActionId) ); $postActionReports = (int) $stmtPostReports->fetchColumn(); /* * Если пользователь уже жаловался * на эту публикацию, запоминаем это. */ $postActionAlreadyReported = false; if ($postActionUserId > 0) { $stmtPostReported = $pdo->prepare( 'SELECT 1 FROM post_reports WHERE post_id = ? AND user_id = ? LIMIT 1' ); $stmtPostReported->execute( array( $postActionId, $postActionUserId ) ); $postActionAlreadyReported = (bool) $stmtPostReported->fetchColumn(); } ?> <div class="post-actions-block">
<div class="post-statistics">


    <span class="post-stat">

        👁

        <?php echo
            (int) $postActionViews;
        ?>

    </span>


    <button
        type="button"
        class="
            post-stat
            post-like-button
            <?php echo
                $postActionLiked
                    ? 'liked'
                    : '';
            ?>"
        data-post-id="<?php echo
            (int) $postActionId;
        ?>"
    >

        <?php echo
            $postActionLiked
                ? '❤️'
                : '♡';
        ?>

        <span class="post-like-count">

            <?php echo
                (int) $postActionLikes;
            ?>

        </span>

    </button>


    <button
        type="button"
        class="post-stat post-comments-button"
        data-post-id="<?php echo
            (int) $postActionId;
        ?>"
    >

        💬

        <span class="post-comment-count">

            <?php echo
                (int) $postActionComments;
            ?>

        </span>

    </button>


    <span class="post-stat">

        ⚠️

        <?php echo
            (int) $postActionReports;
        ?>

    </span>


</div>


<?php if (
    $postActionCanEdit ||
    $postActionCanDelete ||
    $postActionCanReport
): ?>


    <div class="post-menu-wrapper">


        <button
            type="button"
            class="post-menu-button"
            aria-label="Действия с публикацией"
            onclick="
                this
                .parentElement
                .classList
                .toggle('open')
            "
        >

            ⋮

        </button>


        <div class="post-menu">


            <?php if (
                $postActionCanEdit
            ): ?>

                <a
                    href="/users/edit_post.php?id=<?php echo
                        (int) $postActionId;
                    ?>"
                    class="post-menu-item"
                >

                    ✏️ Редактировать

                </a>

            <?php endif; ?>


            <?php if (
                $postActionCanDelete
            ): ?>

                <form
                    method="POST"
                    action="/users/delete_post.php"
                    onsubmit="
                        return confirm(
                            'Удалить эту публикацию?'
                        );
                    "
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
                            (int) $postActionId;
                        ?>"
                    >

                    <button
                        type="submit"
                        class="post-menu-item danger"
                    >

                        🗑️ Удалить

                    </button>

                </form>

            <?php endif; ?>


            <?php if (
                $postActionCanReport
            ): ?>

                <?php if (
                    !$postActionAlreadyReported
                ): ?>

                    <button
                        type="button"
                        class="post-menu-item"
                        onclick="
                            alert(
                                'Форма жалобы будет подключена следующим этапом.'
                            );
                        "
                    >

                        ⚠️ Пожаловаться

                    </button>

                <?php else: ?>

                    <span
                        class="post-menu-item disabled"
                    >

                        ⚠️ Жалоба уже отправлена

                    </span>

                <?php endif; ?>

            <?php endif; ?>


        </div>


    </div>


<?php endif; ?>

</div> <style>
.post-actions-block {
position: relative;

display: flex;

align-items: center;

justify-content: space-between;

gap: 10px;

margin-top: 15px;

padding-top: 12px;

border-top: 1px solid #eee;

}

.post-statistics {
display: flex;

align-items: center;

gap: 15px;

color: #777;

font-size: 14px;

}

.post-stat {
display: inline-flex;

align-items: center;

gap: 4px;

color: #777;

text-decoration: none;

background: none;

border: 0;

padding: 4px;

font-size: 14px;

cursor: default;

}

button.post-stat {
cursor: pointer;
}

button.post-stat:hover {
color: #4f46e5;
}

.post-like-button.liked {
color: #e11d48;
}

.post-menu-wrapper {
position: relative;
}

.post-menu-button {
width: 36px;

height: 36px;

border: 0;

border-radius: 8px;

background: transparent;

color: #555;

font-size: 24px;

line-height: 1;

cursor: pointer;

}

.post-menu-button:hover {
background: #f1f5f9;
}

.post-menu {
display: none;

position: absolute;

right: 0;

bottom: 42px;

min-width: 190px;

background: #fff;

border: 1px solid #e5e7eb;

border-radius: 10px;

box-shadow:
    0 5px 20px
    rgba(0,0,0,.12);

padding: 5px;

z-index: 100;

}

.post-menu-wrapper.open
.post-menu {
display: block;
}

.post-menu-item {
display: block;

width: 100%;

padding: 10px 12px;

border: 0;

border-radius: 7px;

background: transparent;

color: #222;

text-decoration: none;

text-align: left;

font-size: 14px;

cursor: pointer;

}

.post-menu-item:hover {
background: #f3f4f6;
}

.post-menu-item.danger {
color: #dc2626;
}

.post-menu-item.disabled {
color: #999;

cursor: default;

}

.post-menu form {
margin: 0;
}

@media (
max-width: 600px
) {

.post-actions-block {
    align-items: flex-start;
}

.post-statistics {
    gap: 8px;

    flex-wrap: wrap;
}

}

</style>
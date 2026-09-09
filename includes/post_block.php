<?php
/*
ЕДИНЫЙ БЛОК ПУБЛИКАЦИИ
Короткий режим:

$postBlockFull = false;

Полный режим:

$postBlockFull = true;

Используется на:

/users/profile.php
/news/
/news/?id=...
будущий поиск по новостям

Ожидается массив:

$post

Минимальные поля:

id
user_id
title
content
created_at

Желательные поля:

display_name
username
profile_slug
avatar
news_category_name
news_category_slug
in_news_feed
views_count
likes_count
comments_count
reports_count
is_liked

=========================================================
*/

/*
НАСТРОЙКИ РЕЖИМА
*/

if (!isset($postBlockFull)) {
$postBlockFull = false;
}

$postBlockFull = (bool) $postBlockFull;

/*
БЕЗОПАСНЫЙ ВЫВОД
*/

if (!function_exists('postBlockEscape')) {

function postBlockEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

}

/*
ПРОВЕРКА ПУБЛИКАЦИИ
*/

if (
empty($post) ||
empty($post['id'])
) {
return;
}

/*
ID ПУБЛИКАЦИИ
*/

$postId = (int) $post['id'];

/*
ТЕКУЩИЙ ПОЛЬЗОВАТЕЛЬ
*/

$postBlockMyId = 0;

if (function_exists('currentUserId')) {

$postBlockMyId = (int) currentUserId();

}

/*
АВТОР
*/

$postAuthorId = 0;

if (isset($post['user_id'])) {

$postAuthorId = (int) $post['user_id'];

}

$postIsAuthor = (
$postBlockMyId > 0 &&
$postBlockMyId === $postAuthorId
);

/*
ИМЯ АВТОРА
*/

$postAuthorName = '';

if (!empty($post['display_name'])) {

$postAuthorName =
    trim((string) $post['display_name']);

} elseif (!empty($post['username'])) {

$postAuthorName =
    trim((string) $post['username']);

} else {

$postAuthorName = 'Пользователь';

}

/*
USERNAME
*/

$postAuthorUsername = '';

if (!empty($post['username'])) {

$postAuthorUsername =
    trim((string) $post['username']);

}

/*
SLUG
*/

$postAuthorSlug = '';

if (!empty($post['profile_slug'])) {

$postAuthorSlug =
    trim((string) $post['profile_slug']);

}

/*
АВАТАР
*/

$postAuthorAvatar = '';

if (!empty($post['avatar'])) {

$postAuthorAvatar =
    trim((string) $post['avatar']);

}

/*
ССЫЛКА НА ПРОФИЛЬ
*/

$postAuthorUrl = '';

if ($postAuthorSlug !== '') {

$postAuthorUrl =
    '/users/' .
    rawurlencode($postAuthorSlug);

}

/*
ССЫЛКА НА ПОЛНУЮ НОВОСТЬ
*/

$postUrl =
'/news/?id=' .
$postId;

/*
ЗАГОЛОВОК
*/

$postTitle = '';

if (isset($post['title'])) {

$postTitle =
    trim((string) $post['title']);

}

/*
ПОЛНЫЙ ТЕКСТ
*/

$postFullContent = '';

if (isset($post['content'])) {

$postFullContent =
    (string) $post['content'];

}

/*
КОРОТКИЙ ТЕКСТ
*/

$postShortContent =
$postFullContent;

$postIsTruncated = false;

if (
mb_strlen($postFullContent) > 600
) {

$postShortContent =
    mb_substr(
        $postFullContent,
        0,
        600
    );

$postShortContent =
    rtrim($postShortContent) .
    '…';

$postIsTruncated = true;

}

/*
ТЕКСТ ДЛЯ ВЫВОДА
*/

$postContentToShow =
$postBlockFull
? $postFullContent
: $postShortContent;

/*
СТАТИСТИКА
*/

$postViewsCount = 0;
$postLikesCount = 0;
$postCommentsCount = 0;
$postReportsCount = 0;

if (isset($post['views_count'])) {

$postViewsCount =
    (int) $post['views_count'];

}

if (isset($post['likes_count'])) {

$postLikesCount =
    (int) $post['likes_count'];

}

if (isset($post['comments_count'])) {

$postCommentsCount =
    (int) $post['comments_count'];

}

if (isset($post['reports_count'])) {

$postReportsCount =
    (int) $post['reports_count'];

}

/*
ЛАЙК ТЕКУЩЕГО ПОЛЬЗОВАТЕЛЯ
*/

$postIsLiked = false;

if (
isset($post['is_liked']) &&
(int) $post['is_liked'] === 1
) {

$postIsLiked = true;

}

/*
КАТЕГОРИЯ
*/

$postCategoryName = '';

if (!empty($post['news_category_name'])) {

$postCategoryName =
    trim((string) $post['news_category_name']);

}

/*
SLUG КАТЕГОРИИ
*/

$postCategorySlug = '';

if (!empty($post['news_category_slug'])) {

$postCategorySlug =
    trim((string) $post['news_category_slug']);

}

/*
ССЫЛКА НА КАТЕГОРИЮ
Если slug передан SQL-запросом, используем его.

Если slug пока отсутствует, используем
безопасный вариант через id категории.

=========================================================
*/

$postCategoryUrl = '';

if ($postCategorySlug !== '') {

/*
 * Предполагаемый URL категории.
 *
 * При появлении страницы категорий
 * его можно будет изменить в одном месте.
 */

$postCategoryUrl =
    '/news/?category=' .
    rawurlencode($postCategorySlug);

} elseif (
!empty($post['news_category_id'])
) {

$postCategoryUrl =
    '/news/?category_id=' .
    (int) $post['news_category_id'];

}

/*
ОБЩАЯ ЛЕНТА
*/

$postInNewsFeed = false;

if (
isset($post['in_news_feed']) &&
(int) $post['in_news_feed'] === 1
) {

$postInNewsFeed = true;

}

/*
ДАТА
*/

$postCreatedAt = '';

if (!empty($post['created_at'])) {

$postCreatedAt =
    (string) $post['created_at'];

}

/*
ВРЕМЯ

Показываем только часы и минуты.

Например:

22:43
*/

$postTime = '';

if ($postCreatedAt !== '') {

$postTimestamp =
    strtotime($postCreatedAt);

if ($postTimestamp !== false) {

    $postTime =
        date(
            'H:i',
            $postTimestamp
        );

}

}

/*
ИЗОБРАЖЕНИЯ

Пока используем существующую таблицу
post_images.

Позже запрос лучше вынести со страницы,
чтобы при большом количестве публикаций
не делать отдельный SQL-запрос для каждого блока.

=========================================================
*/

$postImages = array();

if (
isset($pdo) &&
$pdo instanceof PDO
) {

try {

    $stmtPostImages =
        $pdo->prepare(
            'SELECT
                id,
                image,
                sort_order,
                created_at

             FROM post_images

             WHERE post_id = ?

             ORDER BY
                sort_order ASC,
                id ASC'
        );

    $stmtPostImages->execute(
        array(
            $postId
        )
    );

    $postImages =
        $stmtPostImages->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Exception $e) {

    $postImages = array();

}

}

/*
ОСТАВЛЯЕМ ТОЛЬКО КОРРЕКТНЫЕ ИЗОБРАЖЕНИЯ
*/

$postValidImages = array();

foreach (
$postImages as $postImage
) {

if (
    empty($postImage['image'])
) {
    continue;
}

$postValidImages[] =
    $postImage;

}

/*
HTML
*/

?>

<article class="post-block <?php echo $postBlockFull ? 'post-block-full' : 'post-block-short'; ?>" data-post-id="<?php echo $postId; ?>" > <!-- ===================================================== ШАПКА ===================================================== --> <div class="post-block-header">
<div class="post-block-author">

    <?php if ($postAuthorUrl !== ''): ?>

        <a
            class="post-block-author-link"
            href="<?php echo
                postBlockEscape(
                    $postAuthorUrl
                );
            ?>"
        >

    <?php endif; ?>

    <?php if ($postAuthorAvatar !== ''): ?>

        <img
            class="post-block-avatar"
            src="<?php echo
                postBlockEscape(
                    $postAuthorAvatar
                );
            ?>"
            alt=""
        >

    <?php else: ?>

        <div
            class="
                post-block-avatar
                post-block-avatar-placeholder
            "
        >
            👤
        </div>

    <?php endif; ?>

    <?php if ($postAuthorUrl !== ''): ?>

        </a>

    <?php endif; ?>

    <div class="post-block-author-data">

        <?php if ($postAuthorUrl !== ''): ?>

            <a
                class="post-block-author-name"
                href="<?php echo
                    postBlockEscape(
                        $postAuthorUrl
                    );
                ?>"
            >

                <?php echo
                    postBlockEscape(
                        $postAuthorName
                    );
                ?>

            </a>

        <?php else: ?>

            <span
                class="post-block-author-name"
            >

                <?php echo
                    postBlockEscape(
                        $postAuthorName
                    );
                ?>

            </span>

        <?php endif; ?>

    </div>

</div>

<!-- =================================================
     МЕНЮ
     ================================================= -->

<div class="post-block-actions">

    <button
        type="button"
        class="post-block-actions-button"
        onclick="togglePostBlockMenu(this)"
        aria-label="Действия"
    >
        ⋮
    </button>

    <div class="post-block-actions-menu">

        <?php if ($postIsAuthor): ?>

            <a
                class="post-block-action-item"
                href="/users/edit_post.php?id=<?php echo
                    $postId;
                ?>"
            >
                ✏️ Редактировать
            </a>

            <button
                type="button"
                class="
                    post-block-action-item
                    post-block-action-danger
                "
                onclick="
                    postBlockConfirmDelete(
                        <?php echo $postId; ?>
                    )
                "
            >
                🗑️ Удалить
            </button>

            <button
                type="button"
                class="post-block-action-item"
                onclick="
                    postBlockOwnerReportMessage();
                "
            >
                🚩 Не жалуйся!
            </button>

        <?php else: ?>

            <a
                class="
                    post-block-action-item
                    post-block-action-danger
                "
                href="/api/report_post.php?id=<?php echo
                    $postId;
                ?>"
            >
                🚩 Пожаловаться
            </a>

        <?php endif; ?>

    </div>

</div>

</div> <!-- ===================================================== МЕТА: АВТОР | ВРЕМЯ | КАТЕГОРИЯ ===================================================== --> <div class="post-block-meta">
<span class="post-block-meta-author">

    <?php echo
        postBlockEscape(
            $postAuthorName
        );
    ?>

</span>

<?php if ($postTime !== ''): ?>

    <span class="post-block-meta-separator">
        |
    </span>

    <time
        class="post-block-meta-time"
        datetime="<?php echo
            postBlockEscape(
                $postCreatedAt
            );
        ?>"
    >

        <?php echo
            postBlockEscape(
                $postTime
            );
        ?>

    </time>

<?php endif; ?>

<?php if ($postCategoryName !== ''): ?>

    <span class="post-block-meta-separator">
        |
    </span>

    <?php if ($postCategoryUrl !== ''): ?>

        <a
            class="post-block-category"
            href="<?php echo
                postBlockEscape(
                    $postCategoryUrl
                );
            ?>"
        >

            <?php echo
                postBlockEscape(
                    $postCategoryName
                );
            ?>

        </a>

    <?php else: ?>

        <span class="post-block-category">

            <?php echo
                postBlockEscape(
                    $postCategoryName
                );
            ?>

        </span>

    <?php endif; ?>

<?php endif; ?>

</div> <!-- ===================================================== ОБЩАЯ ЛЕНТА ===================================================== --> <?php if ($postInNewsFeed): ?>
<div class="post-block-feed-badge">

    📰 В общей ленте

</div>

<?php endif; ?> <!-- ===================================================== ЗАГОЛОВОК ===================================================== --> <?php if ($postTitle !== ''): ?>
<h2 class="post-block-title">

    <?php echo
        postBlockEscape(
            $postTitle
        );
    ?>

</h2>

<?php endif; ?> <!-- ===================================================== ТЕКСТ ===================================================== --> <div class="post-block-content">
<?php echo
    postBlockEscape(
        $postContentToShow
    );
?>

</div> <!-- ===================================================== ГАЛЕРЕЯ =====================================================
 КОРОТКИЙ РЕЖИМ:

 Первое изображение —
 150 × 150.

 Клик ведёт на полную новость.

 ПОЛНЫЙ РЕЖИМ:

 Показываем все изображения.

 Максимальная ширина —
 500px.

 Высота автоматически.

 ===================================================== -->

<?php if ( count($postValidImages) > 0 ): ?>
<?php if (!$postBlockFull): ?>

    <a
        class="post-block-short-image-link"
        href="<?php echo
            postBlockEscape(
                $postUrl
            );
        ?>"
    >

        <img
            class="post-block-short-image"
            src="<?php echo
                postBlockEscape(
                    $postValidImages[0]['image']
                );
            ?>"
            alt=""
            loading="lazy"
        >

    </a>

<?php else: ?>

    <div class="post-block-full-gallery">

        <?php foreach (
            $postValidImages
            as $postImage
        ): ?>

            <a
                class="post-block-full-image-link"
                href="<?php echo
                    postBlockEscape(
                        $postImage['image']
                    );
                ?>"
                target="_blank"
                rel="noopener"
            >

                <img
                    class="post-block-full-image"
                    src="<?php echo
                        postBlockEscape(
                            $postImage['image']
                        );
                    ?>"
                    alt=""
                    loading="lazy"
                >

            </a>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<?php endif; ?> <!-- ===================================================== ЧИТАТЬ ПОЛНОСТЬЮ ===================================================== --> <?php if ( !$postBlockFull && $postIsTruncated ): ?>
<a
    class="post-block-read-more"
    href="<?php echo
        postBlockEscape(
            $postUrl
        );
    ?>"
>
    Читать полностью →
</a>

<?php endif; ?> <!-- ===================================================== НИЖНЯЯ ПАНЕЛЬ ===================================================== --> <div class="post-block-footer">
<!-- =================================================
     ЛАЙК
     ================================================= -->

<div class="post-block-buttons">

    <button
        type="button"
        class="
            post-block-like
            <?php echo
                $postIsLiked
                    ? 'is-liked'
                    : '';
            ?>"
        data-post-id="<?php echo
            $postId;
        ?>"
        aria-label="Поставить лайк"
        onclick="togglePostLike(this)"
    >

        <span
            class="post-block-like-icon"
        ><?php echo
            $postIsLiked
                ? '♥'
                : '♡';
        ?></span>

        <span
            class="post-block-like-count"
        >

            <?php echo
                $postLikesCount;
            ?>

        </span>

    </button>

    <!-- =============================================
         КОММЕНТАРИИ
         ============================================= -->

    <a
        class="post-block-comment-button"
        href="<?php echo
            postBlockEscape(
                $postUrl
            );
        ?>#comments"
    >

        💬

        <span
            class="post-block-comments-count"
        >
            <?php echo
                $postCommentsCount;
            ?>
        </span>

    </a>

</div>

<!-- =================================================
     СТАТИСТИКА
     ================================================= -->

<div class="post-block-stats">

    <span
        class="post-block-stat"
        title="Просмотры"
    >

        👁

        <span
            class="post-block-views-count"
        >
            <?php echo
                $postViewsCount;
            ?>
        </span>

    </span>

    <span
        class="post-block-stat"
        title="Комментарии"
    >

        💬

        <?php echo
            $postCommentsCount;
        ?>

    </span>

    <span
        class="post-block-stat"
        title="Жалобы"
    >

        🚩

        <?php echo
            $postReportsCount;
        ?>

    </span>

</div>

</div> </article> <!-- ===================================================== CSS ===================================================== --> <style>
.post-block {

position: relative;

background: #fff;

border: 1px solid #e5e7eb;

border-radius: 12px;

padding: 18px;

margin-bottom: 18px;

box-shadow:
    0 2px 8px
    rgba(0, 0, 0, .04);

}

/*
ШАПКА
*/

.post-block-header {

display: flex;

align-items: center;

justify-content: space-between;

gap: 12px;

}

.post-block-author {

min-width: 0;

display: flex;

align-items: center;

gap: 10px;

}

.post-block-author-link {

display: flex;

flex-shrink: 0;

color: inherit;

text-decoration: none;

}

.post-block-avatar {

width: 44px;

height: 44px;

border-radius: 50%;

object-fit: cover;

flex-shrink: 0;

}

.post-block-avatar-placeholder {

display: flex;

align-items: center;

justify-content: center;

background: #e5e7eb;

font-size: 22px;

}

.post-block-author-data {

min-width: 0;

}

.post-block-author-name {

font-weight: bold;

color: #222;

text-decoration: none;

}

a.post-block-author-name:hover {

text-decoration: underline;

}

/*
МЕНЮ
*/

.post-block-actions {

position: relative;

flex-shrink: 0;

}

.post-block-actions-button {

width: 36px;

height: 36px;

border: 0;

border-radius: 50%;

background: #f3f4f6;

color: #333;

cursor: pointer;

font-size: 22px;

line-height: 36px;

text-align: center;

}

.post-block-actions-button:hover {

background: #e5e7eb;

}

.post-block-actions-menu {

display: none;

position: absolute;

right: 0;

top: 40px;

min-width: 200px;

background: #fff;

border: 1px solid #e5e7eb;

border-radius: 10px;

box-shadow:
    0 8px 25px
    rgba(0, 0, 0, .12);

overflow: hidden;

z-index: 100;

}

.post-block-actions.open
.post-block-actions-menu {

display: block;

}

.post-block-action-item {

display: block;

width: 100%;

padding: 11px 14px;

border: 0;

background: #fff;

color: #222;

text-align: left;

text-decoration: none;

cursor: pointer;

font-size: 14px;

}

.post-block-action-item:hover {

background: #f8fafc;

}

.post-block-action-danger {

color: #dc2626;

}

/*
МЕТА
*/

.post-block-meta {

display: flex;

align-items: center;

flex-wrap: wrap;

gap: 6px;

margin-top: 10px;

color: #888;

font-size: 13px;

}

.post-block-meta-author {

color: #666;

}

.post-block-meta-separator {

color: #bbb;

}

.post-block-meta-time {

color: #888;

}

.post-block-category {

color: #4f46e5;

text-decoration: none;

font-weight: 600;

}

a.post-block-category:hover {

text-decoration: underline;

}

/*
БЕЙДЖ ОБЩЕЙ ЛЕНТЫ
*/

.post-block-feed-badge {

display: inline-block;

margin-top: 9px;

padding: 4px 8px;

border-radius: 6px;

background: #eef2ff;

color: #4338ca;

font-size: 12px;

font-weight: 600;

}

/*
ЗАГОЛОВОК
*/

.post-block-title {

margin: 12px 0 9px;

color: #222;

font-size: 21px;

line-height: 1.4;

}

/*
ТЕКСТ
*/

.post-block-content {

color: #222;

line-height: 1.65;

white-space: pre-wrap;

word-wrap: break-word;

overflow-wrap: anywhere;

}

/*
ССЫЛКА ЧИТАТЬ ПОЛНОСТЬЮ
*/

.post-block-read-more {

display: inline-block;

margin-top: 10px;

color: #4f46e5;

font-weight: bold;

text-decoration: none;

}

.post-block-read-more:hover {

text-decoration: underline;

}

/*
КОРОТКАЯ КАРТИНКА
*/

.post-block-short-image-link {

display: block;

width: 150px;

height: 150px;

margin-top: 15px;

overflow: hidden;

border-radius: 9px;

background: #f3f4f6;

}

.post-block-short-image {

display: block;

width: 150px;

height: 150px;

object-fit: cover;

transition:
    transform .2s ease;

}

.post-block-short-image-link:hover
.post-block-short-image {

transform: scale(1.03);

}

/*
ПОЛНАЯ ГАЛЕРЕЯ
*/

.post-block-full-gallery {

margin-top: 16px;

}

.post-block-full-image-link {

display: block;

width: 100%;

margin-bottom: 14px;

text-decoration: none;

}

.post-block-full-image {

display: block;

width: auto;

max-width: 500px;

height: auto;

border-radius: 9px;

background: #f3f4f6;

}

/*
FOOTER
*/

.post-block-footer {

display: flex;

align-items: center;

justify-content: space-between;

gap: 12px;

flex-wrap: wrap;

margin-top: 15px;

padding-top: 13px;

border-top: 1px solid #eee;

}

.post-block-buttons {

display: flex;

align-items: center;

gap: 8px;

}

/*
ЛАЙК
*/

.post-block-like,
.post-block-comment-button {

display: inline-flex;

align-items: center;

justify-content: center;

gap: 6px;

min-width: 55px;

min-height: 36px;

padding: 7px 11px;

border-radius: 8px;

border: 1px solid #e5e7eb;

background: #fff;

color: #555;

font-size: 14px;

text-decoration: none;

cursor: pointer;

}

.post-block-like:hover,
.post-block-comment-button:hover {

background: #f8fafc;

}

.post-block-like.is-liked {

color: #dc2626;

background: #fff1f2;

border-color: #fecdd3;

}

.post-block-like-icon {

font-size: 19px;

line-height: 1;

}

.post-block-like-count {

font-weight: bold;

}

/*
СТАТИСТИКА
*/

.post-block-stats {

display: flex;

align-items: center;

gap: 12px;

color: #888;

font-size: 13px;

}

.post-block-stat {

white-space: nowrap;

}

/*
АДАПТИВ
*/

@media (max-width: 600px) {

.post-block {

    padding: 14px;

    border-radius: 10px;

}

.post-block-title {

    font-size: 19px;

}

.post-block-full-image {

    max-width: 100%;

}

.post-block-footer {

    align-items: flex-start;

    flex-direction: column;

}

.post-block-stats {

    width: 100%;

}

}

</style> <!-- ===================================================== JAVASCRIPT ===================================================== --> <script>
/*
МЕНЮ
*/

function togglePostBlockMenu(button)
{

var current =
    button.parentElement;

var allMenus =
    document.querySelectorAll(
        '.post-block-actions'
    );

for (
    var i = 0;
    i < allMenus.length;
    i++
) {

    if (
        allMenus[i] !== current
    ) {

        allMenus[i]
            .classList
            .remove('open');

    }

}

current
    .classList
    .toggle('open');

}

/*
ЗАКРЫТИЕ МЕНЮ ПРИ КЛИКЕ СНАРУЖУ
*/

if (
!window.postBlockMenuListenerAdded
) {

document.addEventListener(
    'click',
    function(event)
    {

        if (
            !event.target.closest(
                '.post-block-actions'
            )
        ) {

            var menus =
                document.querySelectorAll(
                    '.post-block-actions'
                );

            for (
                var i = 0;
                i < menus.length;
                i++
            ) {

                menus[i]
                    .classList
                    .remove('open');

            }

        }

    }
);

window.postBlockMenuListenerAdded =
    true;

}

/*
ЛАЙК
*/

function togglePostLike(button)
{

if (
    button.dataset.loading === '1'
) {

    return;

}

var postId =
    parseInt(
        button.getAttribute(
            'data-post-id'
        ),
        10
    );

if (!postId) {

    return;

}

button.dataset.loading =
    '1';

button.disabled =
    true;

var formData =
    new FormData();

formData.append(
    'post_id',
    postId
);

/*
 * CSRF.
 */

var csrfInput =
    document.querySelector(
        'input[name="csrf_token"]'
    );

if (csrfInput) {

    formData.append(
        'csrf_token',
        csrfInput.value
    );

}

fetch(
    '/api/post_like.php',
    {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }
)
.then(
    function(response)
    {

        if (!response.ok) {

            throw new Error(
                'Ошибка сервера'
            );

        }

        return response.json();

    }
)
.then(
    function(data)
    {

        if (
            !data ||
            data.success !== true
        ) {

            throw new Error(
                'Не удалось изменить лайк'
            );

        }

        var count =
            button.querySelector(
                '.post-block-like-count'
            );

        var icon =
            button.querySelector(
                '.post-block-like-icon'
            );

        if (count) {

            count.textContent =
                data.likes_count;

        }

        if (data.liked) {

            button.classList.add(
                'is-liked'
            );

            if (icon) {

                icon.textContent =
                    '♥';

            }

        } else {

            button.classList.remove(
                'is-liked'
            );

            if (icon) {

                icon.textContent =
                    '♡';

            }

        }

    }
)
.catch(
    function()
    {

        alert(
            'Не удалось изменить лайк. Попробуйте ещё раз.'
        );

    }
)
.finally(
    function()
    {

        button.dataset.loading =
            '0';

        button.disabled =
            false;

    }
);

}

/*
ПРОСМОТР
*/

function registerPostView(postId)
{

if (!postId) {

    return;

}

var formData =
    new FormData();

formData.append(
    'post_id',
    postId
);

var csrfInput =
    document.querySelector(
        'input[name="csrf_token"]'
    );

if (csrfInput) {

    formData.append(
        'csrf_token',
        csrfInput.value
    );

}

fetch(
    '/api/post_view.php',
    {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }
)
.then(
    function(response)
    {

        if (!response.ok) {

            return null;

        }

        return response.json();

    }
)
.then(
    function(data)
    {

        if (
            !data ||
            data.success !== true
        ) {

            return;

        }

        var blocks =
            document.querySelectorAll(
                '.post-block[data-post-id="' +
                postId +
                '"]'
            );

        for (
            var i = 0;
            i < blocks.length;
            i++
        ) {

            var counter =
                blocks[i].querySelector(
                    '.post-block-views-count'
                );

            if (counter) {

                counter.textContent =
                    data.views_count;

            }

        }

    }
)
.catch(
    function()
    {

        /*
         * Просмотр не должен
         * ломать страницу.
         */

    }
);

}

/*
«НЕ ЖАЛУЙСЯ!»
*/

function postBlockOwnerReportMessage()
{

alert(
    'Не жалуйся! Это ваша публикация.'
);

}

/*
УДАЛЕНИЕ
*/

function postBlockConfirmDelete(postId)
{

/*
 * Если на странице уже существует
 * confirmDelete(), используем его.
 */

if (
    typeof confirmDelete ===
    'function'
) {

    confirmDelete(postId);

    return;

}

if (
    !confirm(
        'Удалить публикацию?'
    )
) {

    return;

}

var form =
    document.createElement(
        'form'
    );

form.method =
    'POST';

form.action =
    '/users/delete_post.php';

var postInput =
    document.createElement(
        'input'
    );

postInput.type =
    'hidden';

postInput.name =
    'post_id';

postInput.value =
    postId;

form.appendChild(
    postInput
);

/*
 * CSRF.
 */

var csrfInput =
    document.querySelector(
        'input[name="csrf_token"]'
    );

if (csrfInput) {

    var csrf =
        document.createElement(
            'input'
        );

    csrf.type =
        'hidden';

    csrf.name =
        'csrf_token';

    csrf.value =
        csrfInput.value;

    form.appendChild(
        csrf
    );

}

document.body.appendChild(
    form
);

form.submit();

}

/*
АВТОМАТИЧЕСКАЯ РЕГИСТРАЦИЯ ПРОСМОТРОВ
*/

(function()
{

function registerVisiblePosts()
{

    var blocks =
        document.querySelectorAll(
            '.post-block[data-post-id]'
        );

    for (
        var i = 0;
        i < blocks.length;
        i++
    ) {

        var postId =
            parseInt(
                blocks[i]
                    .getAttribute(
                        'data-post-id'
                    ),
                10
            );

        if (
            postId &&
            !blocks[i].dataset.viewSent
        ) {

            blocks[i].dataset.viewSent =
                '1';

            registerPostView(
                postId
            );

        }

    }

}

if (
    document.readyState ===
    'loading'
) {

    document.addEventListener(
        'DOMContentLoaded',
        registerVisiblePosts
    );

} else {

    registerVisiblePosts();

}

})();

</script>
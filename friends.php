<?php

require_once __DIR__ . '/config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$userId = (int) currentUserId();

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
 * Получаем друзей.
 */

$stmt = $pdo->prepare(
    'SELECT
        u.id,
        u.username,
        u.last_activity,

        p.profile_slug,
        p.display_name,
        p.avatar,

        c.name AS city_name

     FROM friends f

     INNER JOIN users u
        ON u.id = f.friend_id

     LEFT JOIN user_profiles p
        ON p.user_id = u.id

     LEFT JOIN cities c
        ON c.id = u.city_id

     WHERE f.user_id = ?
       AND u.is_blocked = 0

     ORDER BY
        CASE
            WHEN u.last_activity IS NOT NULL
             AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            THEN 0
            ELSE 1
        END,
        COALESCE(p.display_name, u.username) ASC'
);

$stmt->execute(array($userId));

$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Входящие заявки.
 */

$stmt = $pdo->prepare(
    'SELECT
        fr.id,
        fr.sender_id,
        fr.created_at,

        u.username,
        u.last_activity,

        p.profile_slug,
        p.display_name,
        p.avatar

     FROM friend_requests fr

     INNER JOIN users u
        ON u.id = fr.sender_id

     LEFT JOIN user_profiles p
        ON p.user_id = u.id

     WHERE fr.receiver_id = ?
       AND fr.status = "pending"
       AND u.is_blocked = 0

     ORDER BY fr.id DESC'
);

$stmt->execute(array($userId));

$incoming =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Исходящие заявки.
 */

$stmt = $pdo->prepare(
    'SELECT
        fr.id,
        fr.receiver_id,
        fr.created_at,

        u.username,
        u.last_activity,

        p.profile_slug,
        p.display_name,
        p.avatar

     FROM friend_requests fr

     INNER JOIN users u
        ON u.id = fr.receiver_id

     LEFT JOIN user_profiles p
        ON p.user_id = u.id

     WHERE fr.sender_id = ?
       AND fr.status = "pending"
       AND u.is_blocked = 0

     ORDER BY fr.id DESC'
);

$stmt->execute(array($userId));

$outgoing =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Количество входящих заявок.
 */

$incomingCount =
    count($incoming);


/*
 * Проверка онлайн.
 */

function isUserOnline($lastActivity)
{
    if (empty($lastActivity)) {
        return false;
    }

    $timestamp =
        strtotime($lastActivity);

    if ($timestamp === false) {
        return false;
    }

    return $timestamp >= time() - 300;
}


/*
 * Имя пользователя.
 */

function userDisplayName($user)
{
    if (
        isset($user['display_name']) &&
        trim($user['display_name']) !== ''
    ) {
        return $user['display_name'];
    }

    return $user['username'];
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

<title>Друзья — KUPITETUT</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f2f4f7;
    color: #222;
    font-family: Arial, sans-serif;
}

.topbar {
    background: #222;
    padding: 14px 20px;
}

.topbar a {
    color: #fff;
    text-decoration: none;
    margin-right: 20px;
}

.container {
    max-width: 950px;
    margin: 30px auto;
    padding: 0 15px;
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.07);
}

h1,
h2 {
    margin-top: 0;
}

h1 {
    font-size: 28px;
}

h2 {
    font-size: 21px;
    margin-bottom: 18px;
}

.user-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.user {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px;
    border: 1px solid #eee;
    border-radius: 10px;
}

.avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    background: #ddd;
    flex-shrink: 0;
}

.avatar-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.name {
    font-weight: bold;
    font-size: 17px;
}

.username {
    color: #777;
    margin-top: 3px;
}

.city {
    color: #888;
    font-size: 13px;
    margin-top: 4px;
}

.status {
    margin-top: 5px;
    font-size: 13px;
}

.online {
    color: #16a34a;
}

.offline {
    color: #888;
}

.actions {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.button {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 7px;
    text-decoration: none;
    border: 0;
    cursor: pointer;
    font-size: 14px;
}

.primary {
    background: #4f46e5;
    color: #fff;
}

.secondary {
    background: #eee;
    color: #222;
}

.danger {
    background: #fee2e2;
    color: #991b1b;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.empty {
    color: #777;
    padding: 10px 0;
}

.badge {
    display: inline-block;
    background: #ef4444;
    color: #fff;
    min-width: 23px;
    padding: 3px 7px;
    border-radius: 20px;
    text-align: center;
    font-size: 12px;
}

.request {
    border-color: #ddd;
}

@media (max-width: 650px) {

    .user {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .actions {
        width: 100%;
        margin-left: 74px;
    }

    .actions .button {
        flex: 1;
        text-align: center;
    }

}

</style>

</head>

<body>

<div class="topbar">

<a href="chat.php">
💬 KUPITETUT
</a>

<a href="users.php">
🔎 Пользователи
</a>

<a href="friends.php">
👥 Друзья
</a>

<a href="private.php">
💌 Сообщения
</a>

<a href="logout.php">
🚪 Выйти
</a>

</div>


<div class="container">

<h1>
👥 Друзья
</h1>


<!-- =====================================================
     ВХОДЯЩИЕ ЗАЯВКИ
===================================================== -->

<div class="card">

<h2>

📨 Заявки в друзья

<?php if ($incomingCount > 0): ?>

<span class="badge">
<?php echo $incomingCount; ?>
</span>

<?php endif; ?>

</h2>


<?php if (count($incoming) === 0): ?>

<div class="empty">
Новых заявок нет.
</div>

<?php else: ?>

<div class="user-list">

<?php foreach ($incoming as $user): ?>

<div
    class="user request"
    id="request-<?php echo (int) $user['id']; ?>"
>

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


<div class="user-info">

<div class="name">

<?php echo e(
    userDisplayName($user)
); ?>

</div>

<div class="username">

@<?php echo e(
    $user['username']
); ?>

</div>


<?php if (isUserOnline($user['last_activity'])): ?>

<div class="status online">
🟢 онлайн
</div>

<?php else: ?>

<div class="status offline">
⚪ офлайн
</div>

<?php endif; ?>

</div>


<div class="actions">

<a
    class="button secondary"
    href="users/<?php echo e($user['profile_slug']); ?>"
>
👤 Профиль
</a>

<button
    class="button success"
    type="button"
    onclick="acceptRequest(<?php echo (int) $user['id']; ?>)"
>
✅ Принять
</button>

<button
    class="button danger"
    type="button"
    onclick="rejectRequest(<?php echo (int) $user['id']; ?>)"
>
❌ Отклонить
</button>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     ДРУЗЬЯ
===================================================== -->

<div class="card">

<h2>
👥 Мои друзья
</h2>


<?php if (count($friends) === 0): ?>

<div class="empty">

У вас пока нет друзей.

<br><br>

<a
    class="button primary"
    href="users.php"
>
🔎 Найти пользователей
</a>

</div>

<?php else: ?>

<div class="user-list">

<?php foreach ($friends as $user): ?>

<div
    class="user"
    id="friend-<?php echo (int) $user['id']; ?>"
>

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


<div class="user-info">

<div class="name">

<?php echo e(
    userDisplayName($user)
); ?>

</div>


<div class="username">

@<?php echo e(
    $user['username']
); ?>

</div>


<?php if (!empty($user['city_name'])): ?>

<div class="city">

🏙️ <?php echo e(
    $user['city_name']
); ?>

</div>

<?php endif; ?>


<?php if (isUserOnline($user['last_activity'])): ?>

<div class="status online">
🟢 онлайн
</div>

<?php else: ?>

<div class="status offline">
⚪ офлайн
</div>

<?php endif; ?>

</div>


<div class="actions">

<a
    class="button secondary"
    href="users/<?php echo e($user['profile_slug']); ?>"
>
👤 Профиль
</a>

<a
    class="button primary"
    href="private.php?user_id=<?php echo (int) $user['id']; ?>"
>
💌 Написать
</a>

<button
    class="button danger"
    type="button"
    onclick="removeFriend(<?php echo (int) $user['id']; ?>)"
>
🗑️ Удалить
</button>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     ИСХОДЯЩИЕ ЗАЯВКИ
===================================================== -->

<div class="card">

<h2>
📤 Отправленные заявки
</h2>


<?php if (count($outgoing) === 0): ?>

<div class="empty">
Нет ожидающих заявок.
</div>

<?php else: ?>

<div class="user-list">

<?php foreach ($outgoing as $user): ?>

<div class="user">

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


<div class="user-info">

<div class="name">

<?php echo e(
    userDisplayName($user)
); ?>

</div>

<div class="username">

@<?php echo e(
    $user['username']
); ?>

</div>

<div class="status">

⏳ Заявка ожидает ответа

</div>

</div>


<div class="actions">

<a
    class="button secondary"
    href="users/<?php echo e($user['profile_slug']); ?>"
>
👤 Профиль
</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>


<script>

function postRequest(url, data, callback)
{
    var xhr = new XMLHttpRequest();

    xhr.open(
        'POST',
        url,
        true
    );

    xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded'
    );

    xhr.onreadystatechange = function()
    {
        if (
            xhr.readyState === 4
        ) {

            var response;

            try {

                response =
                    JSON.parse(xhr.responseText);

            } catch (e) {

                alert(
                    'Ошибка ответа сервера.'
                );

                return;
            }


            if (!response.success) {

                alert(
                    response.message ||
                    'Произошла ошибка.'
                );

                return;
            }


            callback(response);
        }
    };


    var params = [];

    for (
        var key in data
    ) {

        if (
            data.hasOwnProperty(key)
        ) {

            params.push(
                encodeURIComponent(key) +
                '=' +
                encodeURIComponent(data[key])
            );

        }

    }


    xhr.send(
        params.join('&')
    );
}


function acceptRequest(requestId)
{
    postRequest(
        'api/friend_accept.php',
        {
            request_id: requestId
        },
        function()
        {
            var element =
                document.getElementById(
                    'request-' + requestId
                );

            if (element) {
                element.remove();
            }

            location.reload();
        }
    );
}


function rejectRequest(requestId)
{
    if (
        !confirm(
            'Отклонить заявку?'
        )
    ) {
        return;
    }


    postRequest(
        'api/friend_reject.php',
        {
            request_id: requestId
        },
        function()
        {
            var element =
                document.getElementById(
                    'request-' + requestId
                );

            if (element) {
                element.remove();
            }

            location.reload();
        }
    );
}


function removeFriend(userId)
{
    if (
        !confirm(
            'Удалить пользователя из друзей?'
        )
    ) {
        return;
    }


    postRequest(
        'api/friend_remove.php',
        {
            user_id: userId
        },
        function()
        {
            var element =
                document.getElementById(
                    'friend-' + userId
                );

            if (element) {
                element.remove();
            }

            location.reload();
        }
    );
}

</script>

</body>

</html>
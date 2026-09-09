<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$slug = '';

if (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
}

if ($slug === '') {
    http_response_code(404);
    exit('Профиль не найден');
}


/*
 * Получаем профиль.
 */

$stmt = $pdo->prepare(
    'SELECT
        u.id,
        u.username,
        u.role,
        u.last_activity,
        u.created_at,

        p.profile_slug,
        p.display_name,
        p.gender,
        p.birth_date,
        p.phone,
        p.bio,
        p.avatar,
        p.cover_image,

        c.name AS city_name,
        cat.name AS category_name

     FROM user_profiles p

     INNER JOIN users u
        ON u.id = p.user_id

     LEFT JOIN cities c
        ON c.id = u.city_id

     LEFT JOIN categories cat
        ON cat.id = u.category_id

     WHERE p.profile_slug = ?
       AND u.is_blocked = 0

     LIMIT 1'
);

$stmt->execute(
    array($slug)
);

$user = $stmt->fetch();

if (!$user) {

    http_response_code(404);

    exit('Пользователь не найден');
}


/*
 * Возраст.
 */

$age = null;

if (
    !empty($user['birth_date']) &&
    $user['birth_date'] !== '0000-00-00'
) {

    try {

        $birthDate =
            new DateTime(
                $user['birth_date']
            );

        $today =
            new DateTime();

        $age =
            $today->diff(
                $birthDate
            )->y;

    } catch (Exception $e) {

        $age = null;

    }
}


/*
 * Онлайн.
 *
 * Считаем пользователя онлайн,
 * если активность была менее 5 минут назад.
 */

$isOnline = false;

if (!empty($user['last_activity'])) {

    $lastActivity =
        strtotime(
            $user['last_activity']
        );

    if (
        $lastActivity !== false &&
        $lastActivity >=
        time() - 300
    ) {

        $isOnline = true;

    }
}


/*
 * Это мой профиль?
 */

$myId = currentUserId();

$isMyProfile =
    ((int) $myId === (int) $user['id']);
/*
 * Статус отношений с пользователем.
 */

$friendStatus = 'none';
$friendRequestId = 0;


/*
 * Если это не мой профиль —
 * проверяем дружбу и заявки.
 */

if (!$isMyProfile) {

    /*
     * Проверяем существующую дружбу.
     */

    $stmt = $pdo->prepare(
        'SELECT id
         FROM friends
         WHERE user_id = ?
           AND friend_id = ?
         LIMIT 1'
    );

    $stmt->execute(array(
        $myId,
        $user['id']
    ));

    if ($stmt->fetch()) {

        $friendStatus = 'friends';

    } else {

        /*
         * Проверяем мою исходящую заявку.
         */

        $stmt = $pdo->prepare(
            'SELECT id
             FROM friend_requests
             WHERE sender_id = ?
               AND receiver_id = ?
               AND status = "pending"
             LIMIT 1'
        );

        $stmt->execute(array(
            $myId,
            $user['id']
        ));

        $request = $stmt->fetch();

        if ($request) {

            $friendStatus = 'outgoing';

            $friendRequestId =
                (int) $request['id'];

        } else {

            /*
             * Проверяем входящую заявку.
             */

            $stmt = $pdo->prepare(
                'SELECT id
                 FROM friend_requests
                 WHERE sender_id = ?
                   AND receiver_id = ?
                   AND status = "pending"
                 LIMIT 1'
            );

            $stmt->execute(array(
                $user['id'],
                $myId
            ));

            $request = $stmt->fetch();

            if ($request) {

                $friendStatus = 'incoming';

                $friendRequestId =
                    (int) $request['id'];

            }

        }

    }

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
        <?php echo e(
            $user['display_name']
                ? $user['display_name']
                : $user['username']
        ); ?>
        — KUPITETUT
    </title>

    <style>
.success {
    background: #16a34a;
    color: #fff;
}

button.button {
    border: 0;
    cursor: pointer;
    font-family: Arial, sans-serif;
    font-size: 15px;
}

button.button:disabled {
    opacity: .7;
    cursor: default;
}
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

        .topbar a {
            color: #fff;
            text-decoration: none;
            margin-right: 20px;
        }

        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 15px;
        }

        .profile {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }

        .cover {
            height: 220px;
            background:
                linear-gradient(
                    135deg,
                    #4f46e5,
                    #9333ea
                );
        }

        .profile-main {
            padding: 0 30px 30px;
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid #fff;
            background: #ddd;
            margin-top: -60px;
            object-fit: cover;
        }

        .name {
            font-size: 28px;
            font-weight: bold;
            margin-top: 10px;
        }

        .username {
            color: #777;
            margin-top: 5px;
        }

        .online {
            color: #16a34a;
            font-weight: bold;
        }

        .offline {
            color: #888;
        }

        .info {
            margin-top: 25px;
        }

        .info-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .label {
            color: #777;
            display: inline-block;
            width: 130px;
        }

        .actions {
            margin-top: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 8px;
            text-decoration: none;
            background: #4f46e5;
            color: #fff;
        }

        .button.secondary {
            background: #eee;
            color: #222;
        }

        .bio {
            margin-top: 25px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

    </style>

</head>

<body>

<div class="topbar">

    <a href="../chat.php">
        💬 KUPITETUT
    </a>

    <a href="../users.php">
        🔎 Пользователи
    </a>

    <a href="../private.php">
        💌 Сообщения
    </a>

</div>


<div class="container">

    <div class="profile">

        <div class="cover">

        </div>

        <div class="profile-main">

            <?php if (!empty($user['avatar'])): ?>

                <img
                    class="avatar"
                    src="<?php echo e($user['avatar']); ?>"
                    alt=""
                >

            <?php else: ?>

                <div
                    class="avatar"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        font-size:45px;
                    "
                >
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


            <div class="actions">

                <?php if (!$isMyProfile): ?>

    <a
        class="button"
        href="../private.php?user_id=<?php echo (int) $user['id']; ?>"
    >
        💌 Написать сообщение
    </a>


    <?php if ($friendStatus === 'none'): ?>

        <button
            type="button"
            class="button secondary"
            id="friendButton"
            onclick="sendFriendRequest(<?php echo (int) $user['id']; ?>)"
        >
            👥 Добавить в друзья
        </button>


    <?php elseif ($friendStatus === 'outgoing'): ?>

        <button
            type="button"
            class="button secondary"
            id="friendButton"
            disabled
        >
            ⏳ Заявка отправлена
        </button>


    <?php elseif ($friendStatus === 'incoming'): ?>

        <button
            type="button"
            class="button success"
            id="friendButton"
            onclick="acceptFriendRequest(<?php echo (int) $friendRequestId; ?>)"
        >
            ✅ Принять заявку
        </button>


    <?php elseif ($friendStatus === 'friends'): ?>

        <button
            type="button"
            class="button success"
            id="friendButton"
            onclick="removeFriend(<?php echo (int) $user['id']; ?>)"
        >
            👥 Вы друзья
        </button>

    <?php endif; ?>


<?php else: ?>

    <a
        class="button"
        href="edit.php"
    >
        ✏️ Редактировать профиль
    </a>

<?php endif; ?>
                    <a
                        class="button"
                        href="edit.php"
                    >
                        ✏️ Редактировать профиль
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>
<script>

function sendFriendRequest(userId)
{
    var button =
        document.getElementById(
            'friendButton'
        );

    if (!button) {
        return;
    }

    button.disabled = true;

    var xhr =
        new XMLHttpRequest();

    xhr.open(
        'POST',
        '../api/friend_request.php',
        true
    );

    xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded'
    );

    xhr.onreadystatechange = function()
    {
        if (
            xhr.readyState !== 4
        ) {
            return;
        }


        var response;

        try {

            response =
                JSON.parse(
                    xhr.responseText
                );

        } catch (e) {

            button.disabled = false;

            alert(
                'Ошибка ответа сервера.'
            );

            return;
        }


        if (!response.success) {

            button.disabled = false;

            alert(
                response.message ||
                'Не удалось отправить заявку.'
            );

            return;
        }


        button.innerHTML =
            '⏳ Заявка отправлена';

        button.disabled = true;

        button.className =
            'button secondary';
    };


    xhr.send(
        'user_id=' +
        encodeURIComponent(userId)
    );
}


function acceptFriendRequest(requestId)
{
    var button =
        document.getElementById(
            'friendButton'
        );

    if (!button) {
        return;
    }

    button.disabled = true;


    var xhr =
        new XMLHttpRequest();

    xhr.open(
        'POST',
        '../api/friend_accept.php',
        true
    );

    xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded'
    );

    xhr.onreadystatechange = function()
    {
        if (
            xhr.readyState !== 4
        ) {
            return;
        }


        var response;

        try {

            response =
                JSON.parse(
                    xhr.responseText
                );

        } catch (e) {

            button.disabled = false;

            alert(
                'Ошибка ответа сервера.'
            );

            return;
        }


        if (!response.success) {

            button.disabled = false;

            alert(
                response.message ||
                'Не удалось принять заявку.'
            );

            return;
        }


        button.innerHTML =
            '👥 Вы друзья';

        button.className =
            'button success';

        button.disabled = false;

        button.onclick =
            function()
            {
                removeFriend(
                    <?php echo (int) $user['id']; ?>
                );
            };
    };


    xhr.send(
        'request_id=' +
        encodeURIComponent(requestId)
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


    var button =
        document.getElementById(
            'friendButton'
        );

    if (button) {
        button.disabled = true;
    }


    var xhr =
        new XMLHttpRequest();

    xhr.open(
        'POST',
        '../api/friend_remove.php',
        true
    );

    xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded'
    );

    xhr.onreadystatechange = function()
    {
        if (
            xhr.readyState !== 4
        ) {
            return;
        }


        var response;

        try {

            response =
                JSON.parse(
                    xhr.responseText
                );

        } catch (e) {

            if (button) {
                button.disabled = false;
            }

            alert(
                'Ошибка ответа сервера.'
            );

            return;
        }


        if (!response.success) {

            if (button) {
                button.disabled = false;
            }

            alert(
                response.message ||
                'Не удалось удалить друга.'
            );

            return;
        }


        if (button) {

            button.innerHTML =
                '👥 Добавить в друзья';

            button.className =
                'button secondary';

            button.disabled = false;

            button.onclick =
                function()
                {
                    sendFriendRequest(
                        userId
                    );
                };
        }
    };


    xhr.send(
        'user_id=' +
        encodeURIComponent(userId)
    );
}

</script>
</body>

</html>
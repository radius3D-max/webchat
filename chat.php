<?php

require_once __DIR__ . '/config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$user = currentUser();

if (!$user) {
    header('Location: logout.php');
    exit;
}


/*
 * =========================================================
 * ТЕКУЩИЙ ПОЛЬЗОВАТЕЛЬ
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        u.id,
        u.username,
        u.role,
        c.name AS city_name,
        cat.name AS category_name
     FROM users u
     LEFT JOIN cities c
        ON c.id = u.city_id
     LEFT JOIN categories cat
        ON cat.id = u.category_id
     WHERE u.id = ?
     LIMIT 1'
);

$stmt->execute(
    array(
        $user['id']
    )
);

$currentUser = $stmt->fetch();


if (!$currentUser) {
    header('Location: logout.php');
    exit;
}


/*
 * =========================================================
 * ОНЛАЙН ПОЛЬЗОВАТЕЛИ
 * =========================================================
 */

$stmt = $pdo->query(
    'SELECT
        u.id,
        u.username,
        c.name AS city_name,
        cat.name AS category_name
     FROM users u
     LEFT JOIN cities c
        ON c.id = u.city_id
     LEFT JOIN categories cat
        ON cat.id = u.category_id
     WHERE u.is_blocked = 0
       AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY u.username ASC'
);

$onlineUsers = $stmt->fetchAll();


/*
 * =========================================================
 * ПОСЛЕДНИЕ 50 СООБЩЕНИЙ ОБЩЕГО ЧАТА
 *
 * receiver_id IS NULL означает общий чат.
 * =========================================================
 */

$stmt = $pdo->query(
    'SELECT
        m.id,
        m.message,
        m.created_at,
        m.user_id,
        u.username,
        up.gender
     FROM messages m
     INNER JOIN users u
        ON u.id = m.user_id
     LEFT JOIN user_profiles up
        ON up.user_id = u.id
     WHERE m.receiver_id IS NULL
     ORDER BY m.id DESC
     LIMIT 50'
);

$messages = $stmt->fetchAll();

$messages = array_reverse($messages);

?>
<!DOCTYPE html>

<html lang="ru">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Kupitetut — Общий чат</title>


<style>

/*
 * =========================================================
 * RESET
 * =========================================================
 */

* {
    box-sizing: border-box;
}


/*
 * =========================================================
 * BODY
 * =========================================================
 */

html,
body {
    margin: 0;
    padding: 0;
    height: 100%;
}

body {
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f3f4f8;

    color: #222;
}


/*
 * =========================================================
 * HEADER
 * =========================================================
 */

.header {
    height: 60px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 20px;

    background: #667eea;

    color: #fff;
}

.logo {
    font-size: 21px;

    font-weight: bold;
}

.header-right {
    display: flex;

    align-items: center;

    gap: 15px;
}

.header-right a {
    color: #fff;

    text-decoration: none;
}

.logout {
    padding: 7px 12px;

    background:
        rgba(255,255,255,0.15);

    border-radius: 6px;
}


/*
 * =========================================================
 * LAYOUT
 * =========================================================
 */

.layout {
    display: flex;

    height:
        calc(100vh - 60px);

    max-width: 1400px;

    margin: 0 auto;
}


/*
 * =========================================================
 * LEFT SIDEBAR
 * =========================================================
 */

.sidebar {
    width: 240px;

    flex-shrink: 0;

    background: #fff;

    border-right: 1px solid #ddd;

    padding: 20px;

    overflow-y: auto;
}

.profile-card {
    padding-bottom: 20px;

    margin-bottom: 20px;

    border-bottom:
        1px solid #eee;
}

.avatar {
    width: 60px;
    height: 60px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 10px;

    border-radius: 50%;

    background: #667eea;

    color: #fff;

    font-size: 24px;

    font-weight: bold;
}

.profile-name {
    font-size: 18px;

    font-weight: bold;
}

.profile-info {
    margin-top: 7px;

    color: #777;

    font-size: 13px;

    line-height: 1.6;
}

.menu a {
    display: block;

    padding: 11px 12px;

    margin-bottom: 5px;

    color: #444;

    text-decoration: none;

    border-radius: 7px;
}

.menu a:hover {
    background: #f0f2ff;

    color: #667eea;
}


/*
 * =========================================================
 * CHAT
 * =========================================================
 */

.chat {
    flex: 1;

    display: flex;

    flex-direction: column;

    min-width: 0;

    background: #f8f9fc;
}

.chat-header {
    padding: 18px 22px;

    flex-shrink: 0;

    background: #fff;

    border-bottom:
        1px solid #ddd;

    font-size: 18px;

    font-weight: bold;
}

.messages {
    flex: 1;

    min-height: 0;

    overflow-y: auto;

    padding: 20px;
}

.message {
    margin-bottom: 16px;
}

.message-header {
    display: flex;

    align-items: center;

    gap: 6px;

    margin-bottom: 4px;
}


/*
 * ИКОНКА ПОЛЬЗОВАТЕЛЯ
 */

.gender-icon {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 24px;
    height: 24px;

    flex-shrink: 0;

    margin-right: 2px;

    font-size: 18px;

    line-height: 1;

    cursor: pointer;

    vertical-align: middle;

    transition:
        transform 0.15s;
}

.gender-icon:hover {
    transform:
        scale(1.15);
}


/*
 * НИКНЕЙМ
 *
 * Ник не является ссылкой.
 * Клик выбирает пользователя
 * как получателя приватного сообщения.
 */

.message-user {
    color: #667eea;

    font-weight: bold;

    cursor: pointer;

    text-decoration: none;
}

.message-user:hover {
    text-decoration: underline;
}


/*
 * Время
 */

.message-time {
    color: #999;

    font-size: 11px;
}


/*
 * Текст сообщения
 */

.message-text {
    display: inline-block;

    max-width: 80%;

    padding: 10px 13px;

    background: #fff;

    border-radius: 10px;

    box-shadow:
        0 1px 3px
        rgba(0,0,0,0.06);

    word-wrap: break-word;
}

.empty {
    margin-top: 60px;

    text-align: center;

    color: #999;
}


/*
 * =========================================================
 * PRIVATE MINI CHAT
 * =========================================================
 */

.private-minichat {
    height: 170px;

    flex-shrink: 0;

    display: flex;

    flex-direction: column;

    background: #fff;

    border-top:
        1px solid #ddd;

    border-bottom:
        1px solid #ddd;
}

.private-minichat-header {
    height: 38px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    padding: 0 12px;

    background: #f7f8fc;

    border-bottom:
        1px solid #eee;

    color: #555;

    font-size: 13px;

    font-weight: bold;
}

.private-tabs {
    display: flex;

    gap: 5px;

    padding: 5px 10px;

    overflow-x: auto;

    flex-shrink: 0;

    border-bottom:
        1px solid #eee;
}

.private-tab {
    flex-shrink: 0;

    padding: 5px 10px;

    border:
        1px solid #ddd;

    border-radius: 6px;

    background: #fff;

    color: #555;

    font-size: 12px;

    cursor: pointer;
}

.private-tab:hover {
    background: #f0f2ff;

    color: #667eea;
}

.private-tab.active {
    background: #667eea;

    border-color: #667eea;

    color: #fff;
}

.private-minichat-messages {
    flex: 1;

    min-height: 0;

    overflow-y: scroll;

    padding: 7px 12px;

    font-size: 12px;
}

.private-mini-empty {
    padding: 18px;

    text-align: center;

    color: #aaa;
}

.private-mini-message {
    margin-bottom: 5px;

    line-height: 1.35;
}

.private-mini-message-name {
    color: #667eea;

    font-weight: bold;
}

.private-mini-message.mine {
    text-align: left;
}

.private-mini-message.mine
.private-mini-message-name {
    color: #555;
}


/*
 * =========================================================
 * SEND FORM
 * =========================================================
 */

.message-form {
    display: flex;

    gap: 10px;

    padding: 15px;

    flex-shrink: 0;

    background: #fff;

    border-top:
        1px solid #ddd;
}

.message-form input[type="text"] {
    flex: 1;

    min-width: 0;

    padding: 12px;

    border:
        1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    outline: none;
}

.message-form input[type="text"]:focus {
    border-color: #667eea;
}

.message-form button {
    padding: 0 18px;

    border: 0;

    border-radius: 8px;

    background: #667eea;

    color: #fff;

    font-size: 15px;

    cursor: pointer;
}

.message-form button:hover {
    background: #596bd4;
}

#privateSendButton {
    background: #e85d9e;
}

#privateSendButton:hover {
    background: #d94e90;
}


/*
 * =========================================================
 * ONLINE
 * =========================================================
 */

.online {
    width: 270px;

    flex-shrink: 0;

    background: #fff;

    border-left:
        1px solid #ddd;

    overflow-y: auto;
}

.online-header {
    padding: 18px;

    border-bottom:
        1px solid #ddd;

    font-weight: bold;
}

.online-count {
    color: #667eea;
}

.online-user {
    display: flex;

    align-items: center;

    gap: 10px;

    padding: 12px 15px;

    border-bottom:
        1px solid #f1f1f1;

    color: #333;

    text-decoration: none;
}

.online-user:hover {
    background: #f7f7ff;
}

.online-dot {
    width: 9px;
    height: 9px;

    flex-shrink: 0;

    background: #36c76c;

    border-radius: 50%;
}

.online-user-name {
    font-weight: 600;
}

.online-user-info {
    margin-top: 2px;

    color: #999;

    font-size: 11px;
}


/*
 * =========================================================
 * USER POPUP
 * =========================================================
 */

.user-menu-trigger {
    cursor: pointer;
}

.user-popup {
    position: fixed;

    display: none;

    width: 190px;

    padding: 8px 0;

    background: #fff;

    border:
        1px solid #ddd;

    border-radius: 9px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,0.15);

    z-index: 1000;
}

.user-popup.show {
    display: block;
}

.user-popup-name {
    padding: 10px 13px;

    border-bottom:
        1px solid #eee;

    font-weight: bold;

    color: #667eea;
}

.user-popup a,
.user-popup button {
    display: block;

    width: 100%;

    padding: 9px 13px;

    border: 0;

    background: transparent;

    color: #333;

    text-align: left;

    text-decoration: none;

    font-family: inherit;

    font-size: 14px;

    cursor: pointer;
}

.user-popup a:hover,
.user-popup button:hover {
    background: #f0f2ff;
}


/*
 * =========================================================
 * MOBILE
 * =========================================================
 */

@media (max-width: 700px) {

    .sidebar {
        display: none;
    }

    .online {
        display: none;
    }

    .message-text {
        max-width: 90%;
    }

    .private-minichat {
        height: 150px;
    }

    .message-form {
        gap: 6px;

        padding: 10px;
    }

    .message-form button {
        padding: 0 10px;

        font-size: 13px;
    }

}

</style>

</head>


<body>


<!-- =======================================================
     HEADER
     ======================================================== -->

<header class="header">

    <div class="logo">
        💬 Kupitetut
    </div>


    <div class="header-right">

        <a href="profile.php">

            <?php
            echo htmlspecialchars(
                $currentUser['username'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </a>


        <a
            class="logout"
            href="logout.php"
        >
            Выйти
        </a>

    </div>

</header>


<div class="layout">


<!-- =======================================================
     LEFT SIDEBAR
     ======================================================== -->

<aside class="sidebar">

    <div class="profile-card">

        <div class="avatar">

            <?php
            echo htmlspecialchars(
                strtoupper(
                    substr(
                        $currentUser['username'],
                        0,
                        1
                    )
                ),
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>


        <div class="profile-name">

            <?php
            echo htmlspecialchars(
                $currentUser['username'],
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>


        <div class="profile-info">

            <?php if (!empty($currentUser['city_name'])): ?>

                📍
                <?php
                echo htmlspecialchars(
                    $currentUser['city_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

                <br>

            <?php endif; ?>


            <?php if (!empty($currentUser['category_name'])): ?>

                🏷️
                <?php
                echo htmlspecialchars(
                    $currentUser['category_name'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            <?php endif; ?>

        </div>

    </div>


    <nav class="menu">

        <a href="chat.php">
            💬 Общий чат
        </a>

        <a href="users.php">
            👥 Пользователи
        </a>

        <a href="private.php">
            ✉️ Личные сообщения
        </a>

        <a href="profile.php">
            ⚙️ Мой профиль
        </a>


        <?php if ($currentUser['role'] === 'admin'): ?>

            <a href="admin/index.php">
                🛠 Админка
            </a>

        <?php endif; ?>

    </nav>

</aside>


<!-- =======================================================
     CHAT
     ======================================================== -->

<main class="chat">


    <div class="chat-header">
        💬 Общий чат
    </div>


    <!-- ID текущего пользователя -->

    <input
        type="hidden"
        id="currentUserId"
        value="<?php echo (int) $currentUser['id']; ?>"
    >


    <div
        class="messages"
        id="messages"
    >

        <?php if (!$messages): ?>

            <div class="empty">

                Пока нет сообщений.<br>

                Напишите первым!

            </div>

        <?php endif; ?>


        <?php foreach ($messages as $msg): ?>

            <div
                class="message"
                data-id="<?php
                    echo (int) $msg['id'];
                ?>"
            >

                <div class="message-header">


                    <!-- ИКОНКА -->

                    <span
                        class="gender-icon user-menu-trigger"
                        data-user-id="<?php
                            echo (int) $msg['user_id'];
                        ?>"
                        data-username="<?php
                            echo htmlspecialchars(
                                $msg['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                    >

                        <?php if ($msg['gender'] === 'Женщина'): ?>

                            👩

                        <?php elseif ($msg['gender'] === 'Мужчина'): ?>

                            👨

                        <?php elseif ($msg['gender'] === 'Другой'): ?>

                            🐬

                        <?php else: ?>

                            ❓

                        <?php endif; ?>

                    </span>


                    <!-- НИКНЕЙМ -->

                    <span
                        class="message-user private-user-select"
                        data-user-id="<?php
                            echo (int) $msg['user_id'];
                        ?>"
                        data-username="<?php
                            echo htmlspecialchars(
                                $msg['username'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                    >

                        <?php
                        echo htmlspecialchars(
                            $msg['username'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </span>


                    <!-- ВРЕМЯ -->

                    <span class="message-time">

                        <?php
                        echo htmlspecialchars(
                            $msg['created_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </span>

                </div>


                <div class="message-text">

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $msg['message'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    );
                    ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- ===================================================
         PRIVATE MINI CHAT
         ==================================================== -->

    <div
        class="private-minichat"
        id="privateMiniChat"
    >

        <div class="private-minichat-header">

            💌 Приватный миничат

        </div>


        <div
            class="private-tabs"
            id="privateTabs"
        >

            <button
                type="button"
                class="private-tab active"
            >
                Нет выбранного диалога
            </button>

        </div>


        <div
            class="private-minichat-messages"
            id="privateMiniMessages"
        >

            <div class="private-mini-empty">

                Выберите пользователя
                и начните приватный диалог.

            </div>

        </div>

    </div>


    <!-- ===================================================
         SEND FORM
         ==================================================== -->

    <form
        class="message-form"
        id="messageForm"
        method="POST"
        action="api/send_message.php"
    >


        <!-- Текущий приватный получатель -->

        <input
            type="hidden"
            id="privateReceiverId"
            name="private_receiver_id"
            value=""
        >


        <!-- Поле сообщения -->

        <input
            type="text"
            id="messageInput"
            name="message"
            maxlength="2000"
            placeholder="Напишите сообщение..."
            autocomplete="off"
            required
        >


        <!-- Общий чат -->

        <button
            type="submit"
            id="publicSendButton"
        >
            Отправить
        </button>


        <!-- Приват -->

        <button
            type="button"
            id="privateSendButton"
        >
            💌 Приват
        </button>

    </form>

</main>


<!-- =======================================================
     ONLINE USERS
     ======================================================== -->

<aside class="online">

    <div class="online-header">

        🟢 Онлайн:

        <span class="online-count">

            <?php
            echo count($onlineUsers);
            ?>

        </span>

    </div>


    <?php if (!$onlineUsers): ?>

        <div
            style="
                padding:20px;
                color:#999;
            "
        >

            Сейчас никого нет онлайн.

        </div>

    <?php endif; ?>


    <?php foreach ($onlineUsers as $onlineUser): ?>

        <a
            class="online-user"
            href="private.php?user_id=<?php
                echo (int) $onlineUser['id'];
            ?>"
        >

            <span class="online-dot"></span>


            <div>

                <div class="online-user-name">

                    <?php
                    echo htmlspecialchars(
                        $onlineUser['username'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </div>


                <div class="online-user-info">

                    <?php if (!empty($onlineUser['city_name'])): ?>

                        <?php
                        echo htmlspecialchars(
                            $onlineUser['city_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    <?php endif; ?>


                    <?php if (
                        !empty($onlineUser['city_name']) &&
                        !empty($onlineUser['category_name'])
                    ): ?>

                        ·

                    <?php endif; ?>


                    <?php if (!empty($onlineUser['category_name'])): ?>

                        <?php
                        echo htmlspecialchars(
                            $onlineUser['category_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    <?php endif; ?>

                </div>

            </div>

        </a>

    <?php endforeach; ?>

</aside>


</div>


<!-- =======================================================
     USER POPUP
     ======================================================== -->

<div
    id="userMenu"
    class="user-popup"
>

    <div
        id="userMenuName"
        class="user-popup-name"
    ></div>


    <a
        id="userMenuProfile"
        href="#"
    >
        👤 Профиль
    </a>


    <a
        id="userMenuMessages"
        href="#"
    >
        💌 Сообщения
    </a>


    <a
        id="userMenuFriends"
        href="#"
    >
        👥 Друзья
    </a>


    <button
        type="button"
        id="userMenuBlock"
    >
        🚫 Заблокировать в чате
    </button>


    <button
        type="button"
        id="userMenuReport"
    >
        ⚠️ Пожаловаться
    </button>

</div>


<!-- =======================================================
     JAVASCRIPT
     ======================================================== -->

<script src="assets/js/chat.js"></script>

<script src="assets/js/user_menu.js"></script>

<script src="assets/js/private_chat.js"></script>

</body>

</html>
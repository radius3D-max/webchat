<?php

/*
 * /private.php
 */

require_once __DIR__ . '/config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$myId = currentUserId();

$targetId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

$targetUser = null;
$targetBlockedByMe = false;
$targetBlockedMe = false;

if ($targetId > 0 && $targetId != $myId) {

    $stmt = $pdo->prepare(
        'SELECT
            u.id,
            u.username,
            u.last_activity,
            c.name AS city_name,
            cat.name AS category_name
         FROM users u
         LEFT JOIN cities c ON c.id = u.city_id
         LEFT JOIN categories cat ON cat.id = u.category_id
         WHERE u.id = ?
           AND u.is_blocked = 0
         LIMIT 1'
    );

    $stmt->execute(array($targetId));

    $targetUser = $stmt->fetch();
}

/*
 * Проверяем блокировку между пользователями.
 */

if ($targetUser) {

    $stmt = $pdo->prepare(
        'SELECT
            user_id,
            blocked_user_id
         FROM user_blocks
         WHERE
            (user_id = ? AND blocked_user_id = ?)
            OR
            (user_id = ? AND blocked_user_id = ?)'
    );

    $stmt->execute(array(
        $myId,
        $targetId,
        $targetId,
        $myId
    ));

    $blocks = $stmt->fetchAll();

    foreach ($blocks as $block) {

        if (
            (int) $block['user_id'] === $myId &&
            (int) $block['blocked_user_id'] === $targetId
        ) {
            $targetBlockedByMe = true;
        }

        if (
            (int) $block['user_id'] === $targetId &&
            (int) $block['blocked_user_id'] === $myId
        ) {
            $targetBlockedMe = true;
        }
    }
}

/*
 * =========================================================
 * ЛИЧНЫЕ ДИАЛОГИ
 * =========================================================
 *
 * personal_messages содержит ссылки на сообщения
 * из private_messages.
 *
 * Получаем:
 * - собеседника;
 * - последнее сообщение;
 * - дату последнего сообщения;
 * - количество непрочитанных сообщений.
 */

$stmt = $pdo->prepare(
    'SELECT
        u.id,
        u.username,
        u.last_activity,

        pm.message AS last_message,
        pm.created_at AS last_message_time,

        COALESCE(unread.unread_count, 0) AS unread_count

     FROM users u

     INNER JOIN
     (
         /*
          * Находим последнее personal_message
          * для каждого собеседника.
          */
         SELECT
             CASE
                 WHEN sender_id = ? THEN receiver_id
                 ELSE sender_id
             END AS user_id,

             MAX(id) AS personal_message_id

         FROM personal_messages

         WHERE
             sender_id = ?
             OR receiver_id = ?

         GROUP BY user_id

     ) last_pm
         ON last_pm.user_id = u.id

     INNER JOIN personal_messages pmsg
         ON pmsg.id = last_pm.personal_message_id

     INNER JOIN private_messages pm
         ON pm.id = pmsg.private_message_id

     LEFT JOIN
     (
         /*
          * Количество непрочитанных сообщений,
          * адресованных текущему пользователю.
          */
         SELECT
             sender_id,
             COUNT(*) AS unread_count

         FROM personal_messages

         WHERE
             receiver_id = ?
             AND is_read = 0

         GROUP BY sender_id

     ) unread
         ON unread.sender_id = u.id

     WHERE
         u.is_blocked = 0

     ORDER BY
         pmsg.id DESC'
);

$stmt->execute(
    array(
        $myId,
        $myId,
        $myId,
        $myId
    )
);

$dialogs = $stmt->fetchAll();

/*
 * Если выбран пользователь,
 * получаем сообщения диалога.
 */
$privateMessages = array();

/*
 * Если собеседник заблокировал меня,
 * историю всё равно показываем.
 *
 * Но форма отправки будет недоступна.
 */

if ($targetUser) {

    $stmt = $pdo->prepare(
        'SELECT
            pm.id,
            pm.sender_id,
            pm.receiver_id,
            pm.message,
            pm.created_at,
            u.username AS sender_name
         FROM private_messages pm
         INNER JOIN users u
            ON u.id = pm.sender_id
         WHERE
            (pm.sender_id = ? AND pm.receiver_id = ?)
            OR
            (pm.sender_id = ? AND pm.receiver_id = ?)
         ORDER BY pm.id ASC
         LIMIT 100'
    );

    $stmt->execute(array(
        $myId,
        $targetId,
        $targetId,
        $myId
    ));

    $privateMessages = $stmt->fetchAll();


    /*
     * Помечаем входящие сообщения прочитанными.
     */
    $stmt = $pdo->prepare(
        'UPDATE private_messages
         SET is_read = 1
         WHERE receiver_id = ?
           AND sender_id = ?
           AND is_read = 0'
    );

    $stmt->execute(array(
        $myId,
        $targetId
    ));
}


/*
 * Онлайн-пользователи.
 */
$stmt = $pdo->query(
    'SELECT
        u.id,
        u.username,
        u.last_activity
     FROM users u
     WHERE u.is_blocked = 0
       AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
     ORDER BY u.username ASC'
);

$onlineUsers = $stmt->fetchAll();


function privateIsOnline($lastActivity)
{
    if (!$lastActivity) {
        return false;
    }

    return strtotime($lastActivity) >= time() - 300;
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

<title>Личные сообщения — Kupitetut</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f4f8;
    color: #222;
}


/* HEADER */

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

    background: rgba(255,255,255,0.15);

    border-radius: 6px;
}


/* MAIN */

.private-layout {
    display: flex;

    height: calc(100vh - 60px);

    max-width: 1400px;

    margin: 0 auto;

    background: #fff;
}


/* USERS */

.users {
    width: 280px;

    border-right: 1px solid #ddd;

    overflow-y: auto;
}

.users-title {
    padding: 18px;

    border-bottom: 1px solid #ddd;

    font-weight: bold;
}

.user-item {
    display: flex;

    align-items: center;

    gap: 10px;

    padding: 13px 15px;

    color: #333;

    text-decoration: none;

    border-bottom: 1px solid #f1f1f1;
}

.user-item:hover,
.user-item.active {
    background: #f0f2ff;
}

.dot {
    width: 9px;
    height: 9px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #aaa;
}

.dot.online {
    background: #35c76b;
}

.user-name {
    font-weight: 600;
}

.dialog-preview {
    margin-top: 4px;
    color: #888;
    font-size: 11px;
}

.user-item.has-unread {
    background: #f5f6ff;
}

.user-item.has-unread .user-name {
    color: #667eea;
    font-weight: bold;
}

/* DIALOG */

.dialog {
    flex: 1;

    display: flex;

    flex-direction: column;

    min-width: 0;

    background: #f8f9fc;
}

.dialog-header {
    min-height: 67px;

    display: flex;

    align-items: center;

    padding: 15px 20px;

    background: #fff;

    border-bottom: 1px solid #ddd;
}

.dialog-name {
    font-size: 18px;
    font-weight: bold;
}

.dialog-status {
    margin-top: 3px;

    color: #777;

    font-size: 12px;
}

.dialog-messages {
    flex: 1;

    overflow-y: auto;

    padding: 20px;
}

.pm {
    margin-bottom: 14px;
}

.pm.mine {
    text-align: right;
}

.pm-bubble {
    display: inline-block;

    max-width: 75%;

    padding: 10px 13px;

    border-radius: 12px;

    background: #fff;

    text-align: left;

    box-shadow:
        0 1px 3px
        rgba(0,0,0,0.08);

    word-wrap: break-word;
}

.pm.mine .pm-bubble {
    background: #667eea;
    color: #fff;
}

.pm-time {
    margin-top: 4px;

    color: #999;

    font-size: 10px;
}

.empty {
    margin-top: 70px;

    text-align: center;

    color: #999;
}


/* FORM */

.send-form {
    display: flex;

    gap: 10px;

    padding: 15px;

    background: #fff;

    border-top: 1px solid #ddd;
}

.send-form input {
    flex: 1;

    min-width: 0;

    padding: 12px;

    border: 1px solid #ddd;

    border-radius: 8px;

    font-size: 15px;

    outline: none;
}

.send-form input:focus {
    border-color: #667eea;
}

.send-form button {
    padding: 0 22px;

    border: 0;

    border-radius: 8px;

    background: #667eea;

    color: #fff;

    cursor: pointer;

    font-size: 15px;
}


/* MOBILE */

@media (max-width: 700px) {

    .users {
        width: 100px;
    }

    .users-title {
        padding: 15px 8px;
    }

    .user-item {
        padding: 12px 8px;
    }

    .user-item .user-info {
        display: none;
    }

    .pm-bubble {
        max-width: 90%;
    }

}

</style>

</head>

<body>


<header class="header">

    <div class="logo">
        💬 Kupitetut
    </div>

    <div class="header-right">

        <a href="chat.php">
            Общий чат
        </a>

        <a class="logout" href="logout.php">
            Выйти
        </a>

    </div>

</header>


<div class="private-layout">


<!-- USERS -->

<aside class="users">

    <div class="users-title">
        💌 Диалоги
    </div>


    <?php if (!$dialogs): ?>

        <div style="padding:20px;color:#999;">
            Пока нет диалогов.
        </div>

    <?php endif; ?>


    <?php foreach ($dialogs as $dialog): ?>

        <a
            class="user-item <?php
                echo (
                    $targetId == $dialog['id']
                    ? 'active'
                    : ''
                );
            ?>"
            href="private.php?user_id=<?php
                echo (int) $dialog['id'];
            ?>"
        >

            <span class="dot <?php
                echo privateIsOnline(
                    $dialog['last_activity']
                )
                    ? 'online'
                    : '';
            ?>"></span>


            <div class="user-info">

               <div class="user-name">
    <?php
    echo htmlspecialchars(
        $dialog['username'],
        ENT_QUOTES,
        'UTF-8'
    );
    ?>

    <?php if ((int) $dialog['unread_count'] > 0): ?>

        <span
            style="
                display:inline-block;
                min-width:20px;
                padding:2px 6px;
                margin-left:6px;
                border-radius:10px;
                background:#e74c3c;
                color:#fff;
                font-size:11px;
                text-align:center;
                vertical-align:middle;
            "
        >
            <?php
            echo (int) $dialog['unread_count'];
            ?>
        </span>

    <?php endif; ?>
</div>

<?php if (!empty($dialog['last_message'])): ?>

    <div
        style="
            margin-top:4px;
            color:#888;
            font-size:11px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            max-width:190px;
        "
    >
        <?php
        echo htmlspecialchars(
            $dialog['last_message'],
            ENT_QUOTES,
            'UTF-8'
        );
        ?>
    </div>

<?php endif; ?>

            </div>

        </a>

    <?php endforeach; ?>


    <div
        style="
            padding:15px;
            border-top:1px solid #ddd;
            font-weight:bold;
        "
    >
        🟢 Онлайн
    </div>


    <?php foreach ($onlineUsers as $online): ?>

        <?php if ((int) $online['id'] != $myId): ?>

            <a
                class="user-item <?php
                    echo (
                        $targetId == $online['id']
                        ? 'active'
                        : ''
                    );
                ?>"
                href="private.php?user_id=<?php
                    echo (int) $online['id'];
                ?>"
            >

                <span class="dot online"></span>

                <div class="user-info">

                    <div class="user-name">

                        <?php
                        echo htmlspecialchars(
                            $online['username'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </div>

                </div>

            </a>

        <?php endif; ?>

    <?php endforeach; ?>

</aside>


<!-- DIALOG -->

<main class="dialog">


<?php if ($targetUser): ?>

    <div class="dialog-header">

        <div>

<div class="dialog-name">

    <?php
    echo htmlspecialchars(
        $targetUser['username'],
        ENT_QUOTES,
        'UTF-8'
    );
    ?>

</div>

<div style="margin-top:8px;">

    <?php if ($targetBlockedByMe): ?>

        <form
            method="POST"
            action="api/unblock_user.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="user_id"
                value="<?php echo (int) $targetUser['id']; ?>"
            >

            <button
                type="submit"
                style="
                    padding:6px 10px;
                    border:1px solid #ddd;
                    border-radius:6px;
                    background:#fff;
                    color:#667eea;
                    cursor:pointer;
                "
            >
                🔓 Разблокировать
            </button>

        </form>

    <?php else: ?>

        <form
            method="POST"
            action="api/block_user.php"
            style="display:inline;"
        >

            <input
                type="hidden"
                name="user_id"
                value="<?php echo (int) $targetUser['id']; ?>"
            >

            <button
                type="submit"
                style="
                    padding:6px 10px;
                    border:1px solid #ddd;
                    border-radius:6px;
                    background:#fff;
                    color:#d9534f;
                    cursor:pointer;
                "
            >
                🚫 Заблокировать
            </button>

        </form>

    <?php endif; ?>

</div>


            <div class="dialog-status">

                <?php if (
                    privateIsOnline(
                        $targetUser['last_activity']
                    )
                ): ?>

                    🟢 онлайн

                <?php else: ?>

                    ⚪ не в сети

                <?php endif; ?>

            </div>

        </div>

    </div>


    <div
        class="dialog-messages"
        id="privateMessages"
    >

        <?php if (!$privateMessages): ?>

            <div class="empty">

                Начните диалог 👋

            </div>

        <?php endif; ?>


        <?php foreach ($privateMessages as $pm): ?>

            <div class="pm <?php
                echo (
                    (int) $pm['sender_id'] === $myId
                    ? 'mine'
                    : ''
                );
            ?>">

                <div class="pm-bubble">

                    <?php
                    echo nl2br(
                        htmlspecialchars(
                            $pm['message'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    );
                    ?>

                </div>


                <div class="pm-time">

                    <?php
                    echo htmlspecialchars(
                        $pm['created_at'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>


    <?php if ($targetBlockedMe): ?>

    <div
        style="
            padding:15px;
            background:#fff3f3;
            border-top:1px solid #f0cccc;
            color:#b33;
            text-align:center;
        "
    >
        🚫 Этот пользователь запретил вам отправлять
        приватные сообщения.
    </div>

<?php elseif ($targetBlockedByMe): ?>

    <div
        style="
            padding:15px;
            background:#f5f5f5;
            border-top:1px solid #ddd;
            color:#777;
            text-align:center;
        "
    >
        🔒 Вы заблокировали этого пользователя.
        Разблокируйте его, чтобы продолжить переписку.
    </div>

<?php else: ?>

    <form
        class="send-form"
        method="POST"
        action="api/send_private.php"
    >

        <input
            type="hidden"
            id="privateUserId"
            value="<?php echo (int) $targetUser['id']; ?>"
        >

        <input
            type="hidden"
            name="receiver_id"
            value="<?php echo (int) $targetUser['id']; ?>"
        >

        <input
            type="text"
            name="message"
            maxlength="2000"
            placeholder="Напишите сообщение..."
            autocomplete="off"
            required
        >

        <button type="submit">
            ➤
        </button>

    </form>

<?php endif; ?>

<?php else: ?>

    <div class="empty">

        <h2>💌 Личные сообщения</h2>

        <p>
            Выберите пользователя слева,
            чтобы начать диалог.
        </p>

    </div>

<?php endif; ?>


</main>

</div><script>
    window.currentUserId =
        <?php echo (int) $myId; ?>;
</script>

<script src="assets/js/private.js?v=4"></script>
</body>

</html>

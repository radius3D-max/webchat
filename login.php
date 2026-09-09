<?php

require_once __DIR__ . '/config/auth.php';

$message = '';
$messageType = '';


/*
 * Если пользователь уже авторизован —
 * отправляем его в чат.
 */
if (isLoggedIn()) {
    header('Location: chat.php');
    exit;
}


/*
 * Сообщение после регистрации.
 */
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $message = 'Регистрация успешно завершена. Теперь войдите.';
    $messageType = 'success';
}


/*
 * Обработка входа.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim(
        isset($_POST['username'])
            ? $_POST['username']
            : ''
    );

    $password = isset($_POST['password'])
        ? $_POST['password']
        : '';


    if ($username === '' || $password === '') {

        $message = 'Введите имя пользователя и пароль.';
        $messageType = 'error';

    } else {

        $stmt = $pdo->prepare(
            'SELECT
                id,
                username,
                password_hash,
                role,
                is_blocked
             FROM users
             WHERE username = ?
             LIMIT 1'
        );

        $stmt->execute(array($username));

        $user = $stmt->fetch();


        /*
         * Проверяем пользователя и пароль.
         */
        if (!$user || !password_verify($password, $user['password_hash'])) {

            $message = 'Неверное имя пользователя или пароль.';
            $messageType = 'error';

        } elseif ((int) $user['is_blocked'] === 1) {

            $message = 'Ваш аккаунт заблокирован.';
            $messageType = 'error';

        } else {

            /*
             * Создаём новую сессию.
             */
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];


            /*
             * Обновляем активность.
             */
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET last_activity = NOW()
                 WHERE id = ?'
            );

            $stmt->execute(
                array((int) $user['id'])
            );


            /*
             * Администратор идёт в админку,
             * обычный пользователь — в чат.
             */
            if ($user['role'] === 'admin') {

                header('Location: admin/index.php');
                exit;

            } else {

                header('Location: chat.php');
                exit;
            }
        }
    }
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

    <title>Вход — Kupitetut WebChat</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #667eea,
                    #764ba2
                );

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 430px;
            padding: 20px;
        }

        .login-box {
            background: #fff;
            border-radius: 16px;
            padding: 35px;

            box-shadow:
                0 15px 40px
                rgba(0, 0, 0, 0.20);
        }

        h1 {
            margin: 0 0 8px;

            text-align: center;

            color: #222;
        }

        .subtitle {
            margin: 0 0 25px;

            text-align: center;

            color: #777;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            color: #333;

            font-weight: 600;
        }

        input {
            width: 100%;

            padding: 12px 14px;

            border: 1px solid #ddd;
            border-radius: 8px;

            font-size: 15px;

            outline: none;
        }

        input:focus {
            border-color: #667eea;

            box-shadow:
                0 0 0 3px
                rgba(102, 126, 234, 0.12);
        }

        button {
            width: 100%;

            padding: 13px;

            border: 0;
            border-radius: 8px;

            background: #667eea;

            color: #fff;

            font-size: 16px;
            font-weight: 600;

            cursor: pointer;
        }

        button:hover {
            background: #5568d9;
        }

        .message {
            margin-bottom: 18px;

            padding: 12px;

            border-radius: 8px;

            text-align: center;
        }

        .message.error {
            background: #ffe5e5;
            color: #b00020;
        }

        .message.success {
            background: #e4f8e9;
            color: #176b2c;
        }

        .footer {
            margin-top: 22px;

            text-align: center;

            color: #777;
        }

        .footer a {
            color: #667eea;

            text-decoration: none;

            font-weight: 600;
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="login-box">

        <h1>💬 Kupitetut</h1>

        <p class="subtitle">
            Вход в WebChat
        </p>


        <?php if ($message !== ''): ?>

            <div class="message <?php echo $messageType; ?>">

                <?php
                echo htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>

            </div>

        <?php endif; ?>


        <form method="POST" action="login.php">

            <div class="form-group">

                <label for="username">
                    Имя пользователя
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Пароль
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button type="submit">
                Войти
            </button>

        </form>


        <div class="footer">

            Нет аккаунта?

            <a href="register.php">
                Зарегистрироваться
            </a>

        </div>

    </div>

</div>

</body>

</html>
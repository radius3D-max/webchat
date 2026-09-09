<?php

require_once __DIR__ . '/config/auth.php';

$message = '';
$messageType = '';

/*
 * Если пользователь уже вошёл —
 * отправляем его в чат.
 */
if (isLoggedIn()) {
    header('Location: chat.php');
    exit;
}


/*
 * Получаем города.
 */
$cities = $pdo->query(
    'SELECT id, name
     FROM cities
     ORDER BY name'
)->fetchAll();


/*
 * Получаем категории.
 */
$categories = $pdo->query(
    'SELECT id, name
     FROM categories
     ORDER BY name'
)->fetchAll();


/*
 * Обработка формы.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $cityId = isset($_POST['city_id'])
        ? (int) $_POST['city_id']
        : 0;

    $categoryId = isset($_POST['category_id'])
        ? (int) $_POST['category_id']
        : 0;


    /*
     * Проверяем имя.
     */
    if ($username === '') {

        $message = 'Введите имя пользователя.';
        $messageType = 'error';

    } elseif (function_exists('mb_strlen')
        && (mb_strlen($username) < 3 || mb_strlen($username) > 50)) {

        $message = 'Имя пользователя должно содержать от 3 до 50 символов.';
        $messageType = 'error';

    } elseif (!function_exists('mb_strlen')
        && (strlen($username) < 3 || strlen($username) > 50)) {

        $message = 'Имя пользователя должно содержать от 3 до 50 символов.';
        $messageType = 'error';

    } elseif (!preg_match('/^[\p{L}\p{N}_\- ]+$/u', $username)) {

        $message = 'В имени пользователя есть недопустимые символы.';
        $messageType = 'error';

    } elseif (strlen($password) < 6) {

        $message = 'Пароль должен содержать минимум 6 символов.';
        $messageType = 'error';

    } else {

        /*
         * Проверяем, не занят ли ник.
         */
        $stmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE username = ?
             LIMIT 1'
        );

        $stmt->execute(array($username));

        if ($stmt->fetch()) {

            $message = 'Это имя пользователя уже занято.';
            $messageType = 'error';

        } else {

            /*
             * Проверяем город.
             */
            if ($cityId > 0) {

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM cities
                     WHERE id = ?
                     LIMIT 1'
                );

                $stmt->execute(array($cityId));

                if (!$stmt->fetch()) {
                    $cityId = 0;
                }

            }


            /*
             * Проверяем категорию.
             */
            if ($categoryId > 0) {

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM categories
                     WHERE id = ?
                     LIMIT 1'
                );

                $stmt->execute(array($categoryId));

                if (!$stmt->fetch()) {
                    $categoryId = 0;
                }

            }


            /*
             * Создаём хэш пароля.
             */
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /*
             * Создаём пользователя.
             */
            $stmt = $pdo->prepare(
                'INSERT INTO users
                (
                    username,
                    password_hash,
                    city_id,
                    category_id,
                    role,
                    is_blocked,
                    last_activity
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    "user",
                    0,
                    NOW()
                )'
            );

            $stmt->execute(array(
                $username,
                $passwordHash,
                $cityId > 0 ? $cityId : null,
                $categoryId > 0 ? $categoryId : null
            ));


            /*
             * Регистрация успешна.
             */
            header('Location: login.php?registered=1');
            exit;
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

    <title>Регистрация — Kupitetut WebChat</title>

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

        .register-container {
            width: 100%;
            max-width: 430px;
            padding: 20px;
        }

        .register-box {
            background: #ffffff;
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

        input,
        select {
            width: 100%;
            padding: 12px 14px;

            border: 1px solid #ddd;
            border-radius: 8px;

            font-size: 15px;
            outline: none;

            background: #fff;
        }

        input:focus,
        select:focus {
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

<div class="register-container">

    <div class="register-box">

        <h1>💬 Kupitetut</h1>

        <p class="subtitle">
            Создайте аккаунт
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


        <form method="POST" action="register.php">

            <div class="form-group">

                <label for="username">
                    Имя пользователя
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    maxlength="50"
                    value="<?php
                        echo htmlspecialchars(
                            isset($_POST['username'])
                                ? $_POST['username']
                                : '',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
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
                    minlength="6"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="city_id">
                    Город
                </label>

                <select id="city_id" name="city_id">

                    <option value="0">
                        Выберите город
                    </option>

                    <?php foreach ($cities as $city): ?>

                        <option
                            value="<?php echo (int) $city['id']; ?>"
                        >
                            <?php
                            echo htmlspecialchars(
                                $city['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="category_id">
                    Категория
                </label>

                <select id="category_id" name="category_id">

                    <option value="0">
                        Выберите категорию
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?php echo (int) $category['id']; ?>"
                        >
                            <?php
                            echo htmlspecialchars(
                                $category['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <button type="submit">
                Зарегистрироваться
            </button>

        </form>


        <div class="footer">

            Уже есть аккаунт?

            <a href="login.php">
                Войти
            </a>

        </div>

    </div>

</div>

</body>

</html>
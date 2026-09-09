<?php

require_once __DIR__ . '/../config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$userId = (int) currentUserId();

$message = '';
$error = '';

/*
 * Получаем пользователя из users.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        username,
        city_id
     FROM users
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute(array($userId));

$baseUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$baseUser) {
    die('Пользователь не найден. ID: ' . $userId);
}


/*
 * Получаем профиль.
 */

$stmt = $pdo->prepare(
    'SELECT
        user_id,
        profile_slug,
        display_name,
        gender,
        birth_date,
        phone,
        phone_normalized,
        bio,
        avatar,
        cover_image
     FROM user_profiles
     WHERE user_id = ?
     LIMIT 1'
);

$stmt->execute(array($userId));

$profile = $stmt->fetch(PDO::FETCH_ASSOC);


/*
 * Если профиль отсутствует — создаём.
 */

if (!$profile) {

    $slug = strtolower(
        preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '',
            $baseUser['username']
        )
    );

    if ($slug === '') {
        $slug = 'user_' . $userId;
    }

    $originalSlug = $slug;
    $counter = 2;

    while (true) {

        $stmt = $pdo->prepare(
            'SELECT user_id
             FROM user_profiles
             WHERE profile_slug = ?
             LIMIT 1'
        );

        $stmt->execute(array($slug));

        if (!$stmt->fetch()) {
            break;
        }

        $slug =
            $originalSlug .
            '_' .
            $counter;

        $counter++;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO user_profiles
        (
            user_id,
            profile_slug,
            display_name,
            created_at,
            updated_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )'
    );

    $stmt->execute(
        array(
            $userId,
            $slug,
            $baseUser['username']
        )
    );

    $stmt = $pdo->prepare(
        'SELECT
            user_id,
            profile_slug,
            display_name,
            gender,
            birth_date,
            phone,
            phone_normalized,
            bio,
            avatar,
            cover_image
         FROM user_profiles
         WHERE user_id = ?
         LIMIT 1'
    );

    $stmt->execute(array($userId));

    $profile =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
 * Обработка формы.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $displayName =
        isset($_POST['display_name'])
            ? trim($_POST['display_name'])
            : '';

    $profileSlug =
        isset($_POST['profile_slug'])
            ? trim($_POST['profile_slug'])
            : '';

    $gender =
        isset($_POST['gender'])
            ? trim($_POST['gender'])
            : '';

    $birthDate =
        isset($_POST['birth_date'])
            ? trim($_POST['birth_date'])
            : '';

    $phone =
        isset($_POST['phone'])
            ? trim($_POST['phone'])
            : '';

    $bio =
        isset($_POST['bio'])
            ? trim($_POST['bio'])
            : '';

    $cityId =
        isset($_POST['city_id'])
            ? (int) $_POST['city_id']
            : 0;


    /*
     * Проверяем адрес профиля.
     */

    if ($profileSlug === '') {

        $error =
            'Укажите адрес профиля.';

    } elseif (
        !preg_match(
            '/^[A-Za-z0-9_-]{3,100}$/',
            $profileSlug
        )
    ) {

        $error =
            'Адрес может содержать только латинские буквы, цифры, "_" и "-".';

    }


    /*
     * Проверяем уникальность адреса.
     */

    if ($error === '') {

        $stmt = $pdo->prepare(
            'SELECT user_id
             FROM user_profiles
             WHERE profile_slug = ?
               AND user_id != ?
             LIMIT 1'
        );

        $stmt->execute(
            array(
                $profileSlug,
                $userId
            )
        );

        if ($stmt->fetch()) {

            $error =
                'Этот адрес профиля уже занят.';

        }

    }


    /*
     * Нормализация телефона.
     */

    $phoneNormalized =
        preg_replace(
            '/[^0-9]/',
            '',
            $phone
        );


    /*
     * Проверяем дату рождения.
     */

    if (
        $error === '' &&
        $birthDate !== ''
    ) {

        $dateObject =
            DateTime::createFromFormat(
                'Y-m-d',
                $birthDate
            );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $birthDate
        ) {

            $error =
                'Неверная дата рождения.';

        }

    }


    /*
     * Сохраняем профиль.
     */

    if ($error === '') {

        $stmt = $pdo->prepare(
            'UPDATE user_profiles
             SET
                profile_slug = ?,
                display_name = ?,
                gender = ?,
                birth_date = ?,
                phone = ?,
                phone_normalized = ?,
                bio = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE user_id = ?'
        );

        $stmt->execute(
            array(
                $profileSlug,

                $displayName !== ''
                    ? $displayName
                    : null,

                $gender !== ''
                    ? $gender
                    : null,

                $birthDate !== ''
                    ? $birthDate
                    : null,

                $phone !== ''
                    ? $phone
                    : null,

                $phoneNormalized !== ''
                    ? $phoneNormalized
                    : null,

                $bio !== ''
                    ? $bio
                    : null,

                $userId
            )
        );


        /*
         * Город хранится в users.city_id.
         */

        $stmt = $pdo->prepare(
            'UPDATE users
             SET city_id = ?
             WHERE id = ?'
        );

        $stmt->execute(
            array(
                $cityId > 0
                    ? $cityId
                    : null,

                $userId
            )
        );


        /*
         * Обновляем данные на странице.
         */

        $profile['profile_slug'] =
            $profileSlug;

        $profile['display_name'] =
            $displayName;

        $profile['gender'] =
            $gender;

        $profile['birth_date'] =
            $birthDate;

        $profile['phone'] =
            $phone;

        $profile['phone_normalized'] =
            $phoneNormalized;

        $profile['bio'] =
            $bio;

        $baseUser['city_id'] =
            $cityId > 0
                ? $cityId
                : null;

        $message =
            'Профиль успешно сохранён.';
    }
}


/*
 * Получаем города.
 */

$cities = array();

try {

    $stmt = $pdo->query(
        'SELECT
            id,
            name,
            country
         FROM cities
         WHERE is_active = 1
         ORDER BY name ASC'
    );

    $cities =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $cities = array();
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
Редактирование профиля — KUPITETUT
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f2f4f7;
    font-family: Arial, sans-serif;
    color: #222;
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
    max-width: 700px;
    margin: 30px auto;
    padding: 0 15px;
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

h1 {
    margin-top: 0;
}

label {
    display: block;
    margin-top: 18px;
    margin-bottom: 7px;
    font-weight: bold;
}

input,
select,
textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 15px;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

.hint {
    color: #777;
    font-size: 13px;
    margin-top: 5px;
}

.success {
    background: #dcfce7;
    color: #166534;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 15px;
}

button {
    margin-top: 25px;
    padding: 12px 22px;
    border: 0;
    border-radius: 7px;
    background: #4f46e5;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
}

.profile-link {
    margin-top: 20px;
    padding: 12px;
    background: #f3f4f6;
    border-radius: 7px;
    word-break: break-all;
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

<div class="card">

<h1>
👤 Редактирование профиля
</h1>


<?php if ($message !== ''): ?>

<div class="success">
<?php echo e($message); ?>
</div>

<?php endif; ?>


<?php if ($error !== ''): ?>

<div class="error">
<?php echo e($error); ?>
</div>

<?php endif; ?>


<form method="POST">


<label>
Логин
</label>

<input
    type="text"
    value="<?php echo e(
        $baseUser['username']
    ); ?>"
    disabled
>

<div class="hint">
Логин используется для входа и не изменяется.
</div>


<label>
Адрес профиля
</label>

<input
    type="text"
    name="profile_slug"
    value="<?php echo e(
        $profile['profile_slug']
    ); ?>"
    maxlength="100"
    required
>

<div class="hint">
Например: ivan_777
</div>


<label>
Имя
</label>

<input
    type="text"
    name="display_name"
    value="<?php echo e(
        $profile['display_name']
    ); ?>"
    maxlength="150"
>


<label>
Гендер
</label>

<select name="gender">

<option value="">
Не указан
</option>

<option
    value="Мужчина"
    <?php
    if ($profile['gender'] === 'Мужчина') {
        echo 'selected';
    }
    ?>
>
Мужчина
</option>

<option
    value="Женщина"
    <?php
    if ($profile['gender'] === 'Женщина') {
        echo 'selected';
    }
    ?>
>
Женщина
</option>

<option
    value="Другой"
    <?php
    if ($profile['gender'] === 'Другой') {
        echo 'selected';
    }
    ?>
>
Другой
</option>

</select>


<label>
Дата рождения
</label>

<input
    type="date"
    name="birth_date"
    value="<?php echo e(
        $profile['birth_date']
    ); ?>"
>


<label>
Город
</label>

<select name="city_id">

<option value="0">
Не указан
</option>

<?php foreach ($cities as $city): ?>

<option
    value="<?php echo (int) $city['id']; ?>"
    <?php
    if (
        (int) $baseUser['city_id'] ===
        (int) $city['id']
    ) {
        echo 'selected';
    }
    ?>
>

<?php echo e(
    $city['name']
); ?>

<?php

if (!empty($city['country'])) {

    echo ' — ' .
        e($city['country']);

}

?>

</option>

<?php endforeach; ?>

</select>


<label>
Телефон
</label>

<input
    type="text"
    name="phone"
    value="<?php echo e(
        $profile['phone']
    ); ?>"
    maxlength="50"
    placeholder="+380 98 111 11 11"
>

<div class="hint">
Формат телефона не важен. Для поиска будет использоваться нормализованный номер.
</div>


<label>
О себе
</label>

<textarea
    name="bio"
    maxlength="5000"
    placeholder="Расскажите немного о себе..."
><?php echo e(
    $profile['bio']
); ?></textarea>


<button type="submit">
💾 Сохранить профиль
</button>

</form>


<div class="profile-link">

🔗 Ваш профиль:

<br>

<a
    href="../users/<?php echo e(
        $profile['profile_slug']
    ); ?>"
>
https://kupitetut.com/users/<?php echo e(
    $profile['profile_slug']
); ?>
</a>

</div>


</div>

</div>

</body>

</html>
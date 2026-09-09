<?php

require_once __DIR__ . '/config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

$myId = (int) currentUserId();

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
 * Параметры поиска.
 */

$q = isset($_GET['q'])
    ? trim($_GET['q'])
    : '';

$phone = isset($_GET['phone'])
    ? trim($_GET['phone'])
    : '';

$cityId = isset($_GET['city_id'])
    ? (int) $_GET['city_id']
    : 0;

$categoryId = isset($_GET['category_id'])
    ? (int) $_GET['category_id']
    : 0;

$gender = isset($_GET['gender'])
    ? trim($_GET['gender'])
    : '';

$minAge = isset($_GET['min_age'])
    ? (int) $_GET['min_age']
    : 0;

$maxAge = isset($_GET['max_age'])
    ? (int) $_GET['max_age']
    : 0;

$online = isset($_GET['online'])
    ? (int) $_GET['online']
    : 0;


/*
 * Нормализуем телефон.
 *
 * Например:
 *
 * 098111111
 * 098-111-11-11
 * 0 9 8 1 1 1 1 1 1
 *
 * превращаются в:
 *
 * 098111111
 */

$phoneNormalized =
    preg_replace(
        '/[^0-9]/',
        '',
        $phone
    );


/*
 * Основной SQL.
 */

$sql = '
    SELECT
        u.id,
        u.username,
        u.city_id,
        u.category_id,
        u.last_activity,
        u.created_at,

        p.profile_slug,
        p.display_name,
        p.gender,
        p.birth_date,
        p.phone,
        p.avatar,
        p.bio,

        c.name AS city_name,
        c.country AS city_country,

        cat.name AS category_name

    FROM users u

    LEFT JOIN user_profiles p
        ON p.user_id = u.id

    LEFT JOIN cities c
        ON c.id = u.city_id

    LEFT JOIN categories cat
        ON cat.id = u.category_id

    WHERE u.is_blocked = 0
';


$params = array();


/*
 * Имя или login.
 */

if ($q !== '') {

    $sql .= '
        AND (
            u.username LIKE ?
            OR p.display_name LIKE ?
        )
    ';

    $search =
        '%' . $q . '%';

    $params[] = $search;
    $params[] = $search;
}


/*
 * Телефон.
 */

if ($phoneNormalized !== '') {

    $sql .= '
        AND p.phone_normalized LIKE ?
    ';

    $params[] =
        '%' .
        $phoneNormalized .
        '%';
}


/*
 * Город.
 */

if ($cityId > 0) {

    $sql .= '
        AND u.city_id = ?
    ';

    $params[] = $cityId;
}


/*
 * Категория.
 */

if ($categoryId > 0) {

    $sql .= '
        AND u.category_id = ?
    ';

    $params[] = $categoryId;
}


/*
 * Гендер.
 */

if ($gender !== '') {

    $sql .= '
        AND p.gender = ?
    ';

    $params[] = $gender;
}


/*
 * Возраст.
 *
 * Дата рождения:
 *
 * максимальная дата рождения
 * соответствует минимальному возрасту.
 */

if ($minAge > 0) {

    $maxBirthDate =
        date(
            'Y-m-d',
            strtotime(
                '-' . $minAge . ' years'
            )
        );

    $sql .= '
        AND p.birth_date <= ?
    ';

    $params[] =
        $maxBirthDate;
}


/*
 * Максимальный возраст.
 */

if ($maxAge > 0) {

    $minBirthDate =
        date(
            'Y-m-d',
            strtotime(
                '-' . ($maxAge + 1) . ' years'
            )
        );

    $sql .= '
        AND p.birth_date > ?
    ';

    $params[] =
        $minBirthDate;
}


/*
 * Только онлайн.
 *
 * Онлайн = активность менее 5 минут назад.
 */

if ($online === 1) {

    $sql .= '
        AND u.last_activity >=
            DATE_SUB(
                NOW(),
                INTERVAL 5 MINUTE
            )
    ';
}


/*
 * Сначала онлайн,
 * затем новые пользователи.
 */

$sql .= '
    ORDER BY
        CASE
            WHEN u.last_activity >=
                DATE_SUB(
                    NOW(),
                    INTERVAL 5 MINUTE
                )
            THEN 0
            ELSE 1
        END,
        u.created_at DESC
    LIMIT 200
';


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$users =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Города.
 */

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


/*
 * Категории.
 */

$categories = array();

try {

    $stmt = $pdo->query(
        'SELECT
            id,
            name
         FROM categories
         WHERE is_active = 1
         ORDER BY name ASC'
    );

    $categories =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $categories = array();

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
Пользователи — KUPITETUT
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
    max-width: 1100px;
    margin: 30px auto;
    padding: 0 15px;
}

.card {
    background: #fff;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

h1 {
    margin-top: 0;
}

.search-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.field label {
    display: block;
    margin-bottom: 6px;
    font-weight: bold;
}

input,
select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 7px;
    font-size: 15px;
}

.search-button {
    margin-top: 20px;
    padding: 11px 20px;
    border: 0;
    border-radius: 7px;
    background: #4f46e5;
    color: white;
    font-size: 15px;
    cursor: pointer;
}

.clear {
    margin-left: 10px;
    color: #555;
}

.users-grid {
    display: grid;
    grid-template-columns:
        repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.user-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    background: #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    margin-bottom: 12px;
}

.user-name {
    font-size: 19px;
    font-weight: bold;
}

.username {
    color: #777;
    margin-top: 3px;
}

.online {
    color: #16a34a;
    font-size: 14px;
    margin-top: 8px;
}

.offline {
    color: #888;
    font-size: 14px;
    margin-top: 8px;
}

.info {
    margin-top: 12px;
    color: #555;
    line-height: 1.5;
}

.actions {
    margin-top: 15px;
}

.button {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 7px;
    text-decoration: none;
    background: #4f46e5;
    color: #fff;
    margin-right: 5px;
}

.button.secondary {
    background: #eee;
    color: #222;
}

.empty {
    padding: 30px;
    text-align: center;
    color: #777;
}

@media (max-width: 800px) {

    .users-grid {
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}

@media (max-width: 600px) {

    .search-grid {
        grid-template-columns: 1fr;
    }

    .users-grid {
        grid-template-columns: 1fr;
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

<a href="private.php">
💌 Личные сообщения
</a>

<a href="profile.php">
👤 Профиль
</a>

</div>


<div class="container">


<div class="card">

<h1>
🔎 Поиск пользователей
</h1>


<form method="GET">

<div class="search-grid">


<div class="field">

<label>
Имя или login
</label>

<input
    type="text"
    name="q"
    value="<?php echo e($q); ?>"
    placeholder="Например: testuser"
>

</div>


<div class="field">

<label>
Телефон
</label>

<input
    type="text"
    name="phone"
    value="<?php echo e($phone); ?>"
    placeholder="098-111-11-11"
>

</div>


<div class="field">

<label>
Город
</label>

<select name="city_id">

<option value="0">
Все города
</option>

<?php foreach ($cities as $city): ?>

<option
    value="<?php echo (int) $city['id']; ?>"
    <?php
    if (
        $cityId ===
        (int) $city['id']
    ) {
        echo 'selected';
    }
    ?>
>

<?php echo e($city['name']); ?>

<?php if (!empty($city['country'])): ?>

 — <?php echo e($city['country']); ?>

<?php endif; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Категория
</label>

<select name="category_id">

<option value="0">
Все категории
</option>

<?php foreach ($categories as $category): ?>

<option
    value="<?php echo (int) $category['id']; ?>"
    <?php
    if (
        $categoryId ===
        (int) $category['id']
    ) {
        echo 'selected';
    }
    ?>
>

<?php echo e(
    $category['name']
); ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="field">

<label>
Гендер
</label>

<select name="gender">

<option value="">
Любой
</option>

<option
    value="Мужчина"
    <?php
    if ($gender === 'Мужчина') {
        echo 'selected';
    }
    ?>
>
Мужчина
</option>

<option
    value="Женщина"
    <?php
    if ($gender === 'Женщина') {
        echo 'selected';
    }
    ?>
>
Женщина
</option>

<option
    value="Другой"
    <?php
    if ($gender === 'Другой') {
        echo 'selected';
    }
    ?>
>
Другой
</option>

</select>

</div>


<div class="field">

<label>
Возраст от
</label>

<input
    type="number"
    name="min_age"
    min="1"
    max="120"
    value="<?php echo $minAge > 0
        ? (int) $minAge
        : ''; ?>"
    placeholder="18"
>

</div>


<div class="field">

<label>
Возраст до
</label>

<input
    type="number"
    name="max_age"
    min="1"
    max="120"
    value="<?php echo $maxAge > 0
        ? (int) $maxAge
        : ''; ?>"
    placeholder="60"
>

</div>


<div class="field">

<label>
Статус
</label>

<select name="online">

<option
    value="0"
>
Все пользователи
</option>

<option
    value="1"
    <?php
    if ($online === 1) {
        echo 'selected';
    }
    ?>
>
🟢 Только онлайн
</option>

</select>

</div>


</div>


<button
    class="search-button"
    type="submit"
>
🔎 Найти
</button>


<a
    class="clear"
    href="users.php"
>
Сбросить
</a>


</form>

</div>


<div class="card">

<strong>
Найдено пользователей:
</strong>

<?php echo count($users); ?>

</div>


<?php if (!$users): ?>

<div class="card empty">

😔 Пользователи не найдены.

</div>

<?php else: ?>

<div class="users-grid">

<?php foreach ($users as $user): ?>


<?php

$profileSlug =
    !empty($user['profile_slug'])
        ? $user['profile_slug']
        : '';

$displayName =
    !empty($user['display_name'])
        ? $user['display_name']
        : $user['username'];

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
 * Вычисляем возраст.
 */

$age = null;

if (
    !empty($user['birth_date']) &&
    $user['birth_date'] !== '0000-00-00'
) {

    try {

        $birth =
            new DateTime(
                $user['birth_date']
            );

        $today =
            new DateTime();

        $age =
            $today->diff(
                $birth
            )->y;

    } catch (Exception $e) {

        $age = null;

    }
}

?>


<div class="user-card">


<?php if (!empty($user['avatar'])): ?>

<img
    class="avatar"
    src="<?php echo e(
        $user['avatar']
    ); ?>"
    alt=""
>

<?php else: ?>

<div class="avatar">
👤
</div>

<?php endif; ?>


<div class="user-name">

<?php echo e(
    $displayName
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


<?php if ($age !== null): ?>

<div>
🎂 <?php echo (int) $age; ?> лет
</div>

<?php endif; ?>


<?php if (!empty($user['gender'])): ?>

<div>
⚧ <?php echo e(
    $user['gender']
); ?>
</div>

<?php endif; ?>


<?php if (!empty($user['city_name'])): ?>

<div>
🏙️ <?php echo e(
    $user['city_name']
); ?>
</div>

<?php endif; ?>


<?php if (!empty($user['category_name'])): ?>

<div>
🏷️ <?php echo e(
    $user['category_name']
); ?>
</div>

<?php endif; ?>


</div>


<div class="actions">


<?php if ($profileSlug !== ''): ?>

<a
    class="button"
    href="users/<?php echo e(
        $profileSlug
    ); ?>"
>
👤 Профиль
</a>

<?php endif; ?>


<?php if (
    (int) $user['id'] !== $myId
): ?>

<a
    class="button secondary"
    href="private.php?user_id=<?php echo (int) $user['id']; ?>"
>
💌 Написать
</a>

<?php endif; ?>


</div>


</div>


<?php endforeach; ?>

</div>

<?php endif; ?>


</div>

</body>

</html>
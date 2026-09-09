<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Метод не поддерживается.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$cityId = (int) ($_POST['city_id'] ?? 0);
$categoryId = (int) ($_POST['category_id'] ?? 0);


/*
 * Проверяем ник.
 */
if ($username === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Введите имя пользователя.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
    echo json_encode([
        'success' => false,
        'message' => 'Имя пользователя должно содержать от 3 до 50 символов.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
 * Разрешаем только буквы, цифры, пробел,
 * подчёркивание и дефис.
 */
if (!preg_match('/^[\p{L}\p{N}_\- ]+$/u', $username)) {
    echo json_encode([
        'success' => false,
        'message' => 'В имени пользователя есть недопустимые символы.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
 * Проверяем пароль.
 */
if (strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Пароль должен содержать минимум 6 символов.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
 * Проверяем существование города.
 */
if ($cityId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id FROM cities WHERE id = ? LIMIT 1'
    );

    $stmt->execute([$cityId]);

    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Выбранный город не существует.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
} else {
    $cityId = null;
}


/*
 * Проверяем существование категории.
 */
if ($categoryId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id FROM categories WHERE id = ? LIMIT 1'
    );

    $stmt->execute([$categoryId]);

    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Выбранная категория не существует.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
} else {
    $categoryId = null;
}


/*
 * Проверяем уникальность имени.
 */
$stmt = $pdo->prepare(
    'SELECT id
     FROM users
     WHERE username = ?
     LIMIT 1'
);

$stmt->execute([$username]);

if ($stmt->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'Это имя пользователя уже занято.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
 * Хешируем пароль.
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
        (username, password_hash, city_id, category_id, role, last_activity)
     VALUES
        (?, ?, ?, ?, "user", NOW())'
);

$stmt->execute([
    $username,
    $passwordHash,
    $cityId,
    $categoryId
]);


echo json_encode([
    'success' => true,
    'message' => 'Регистрация успешно завершена.'
], JSON_UNESCAPED_UNICODE);
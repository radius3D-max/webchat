<?php

$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'chat';
$dbUser = 'chat';

/*
 * Здесь укажи НОВЫЙ пароль пользователя MySQL.
 */
$dbPassword = 'deleted';


$dsn =
    'mysql:host=' . $dbHost .
    ';port=' . $dbPort .
    ';dbname=' . $dbName .
    ';charset=utf8mb4';


$options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
);


try {

    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        $options
    );

} catch (PDOException $e) {

    http_response_code(500);

    echo '<h2>Ошибка подключения к базе данных</h2>';

    /*
     * Подробную ошибку базы данных
     * не показываем посетителям сайта.
     *
     * Её лучше смотреть через журнал ошибок хостинга.
     */

    exit;
}
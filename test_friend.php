<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Friend API Test</title>
</head>

<body>

<h1>Тест друзей</h1>

<p>
    Текущий пользователь: testuser (ID 2)
</p>

<form method="POST" action="/api/friend_request.php">

    <input
        type="hidden"
        name="user_id"
        value="3"
    >

    <button type="submit">
        Отправить заявку administrator (ID 3)
    </button>

</form>

</body>
</html>
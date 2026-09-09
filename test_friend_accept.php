<?php
require_once __DIR__ . '/config/auth.php';

requireLogin();
checkBlocked();
updateLastActivity();

echo '<h1>Friend Accept Test</h1>';

echo '<p>User ID: ' . (int) currentUserId() . '</p>';

echo '<form method="POST" action="/api/friend_accept.php">';

echo '<input type="hidden" name="request_id" value="1">';

echo '<button type="submit">';
echo 'Принять заявку №1';
echo '</button>';

echo '</form>';
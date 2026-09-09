<?php

echo 'CHAT TEST OK';

require_once __DIR__ . '/config/auth.php';

echo '<br>AUTH OK';

if (isLoggedIn()) {
    echo '<br>USER LOGGED IN';
    echo '<br>User ID: ' . currentUserId();
} else {
    echo '<br>USER NOT LOGGED IN';
}
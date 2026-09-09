<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once __DIR__.'/../config/auth.php';
requireLogin();
checkBlocked();
updateLastActivity();
header('Content-Type: application/json; charset=UTF-8');

if($_SERVER['REQUEST_METHOD']!=='POST'){
    http_response_code(405);
    echo json_encode(array('success'=>false,'error'=>'Недопустимый метод запроса.'),JSON_UNESCAPED_UNICODE);
    exit;
}

$receiverId=isset($_POST['private_receiver_id'])?(int)$_POST['private_receiver_id']:0;

if($receiverId>0){
    $_POST['receiver_id']=$receiverId;
    require __DIR__.'/send_private.php';
    exit;
}

require __DIR__.'/send_public.php';
exit;
?>
<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once __DIR__.'/config/auth.php';
requireLogin();
checkBlocked();
updateLastActivity();
$user=currentUser();
if(!$user){header('Location: logout.php');exit;}
$stmt=$pdo->prepare('SELECT u.id,u.username,u.role,c.name AS city_name,cat.name AS category_name FROM users u LEFT JOIN cities c ON c.id=u.city_id LEFT JOIN categories cat ON cat.id=u.category_id WHERE u.id=? LIMIT 1');
$stmt->execute(array($user['id']));
$currentUser=$stmt->fetch();
if(!$currentUser){header('Location: logout.php');exit;}
$stmt=$pdo->query('SELECT u.id,u.username,c.name AS city_name,cat.name AS category_name FROM users u LEFT JOIN cities c ON c.id=u.city_id LEFT JOIN categories cat ON cat.id=u.category_id WHERE u.is_blocked=0 AND u.last_activity>=DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY u.username ASC');
$onlineUsers=$stmt->fetchAll();
$stmt = $pdo->query(
    'SELECT
        m.id,
        m.message,
        m.created_at,
        m.user_id,
        m.receiver_id,
        sender.username AS sender_username
     FROM messages m
     INNER JOIN users sender
     ON sender.id = m.user_id
     WHERE m.receiver_id IS NULL
     ORDER BY m.id DESC
     LIMIT 50'
);
$messages=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Kupitetut — Общий чат</title><style>
*{box-sizing:border-box}
html,body{margin:0;padding:0;height:100%}
body{font-family:Arial,Helvetica,sans-serif;background:#f3f4f8;color:#222}
.header{height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:#667eea;color:#fff}
.logo{font-size:21px;font-weight:bold}
.header-right{display:flex;align-items:center;gap:15px}
.header-right a{color:#fff;text-decoration:none}
.logout{padding:7px 12px;background:rgba(255,255,255,.15);border-radius:6px}
.layout{display:flex;height:calc(100vh - 60px);max-width:1400px;margin:0 auto}
.sidebar{width:240px;flex-shrink:0;background:#fff;border-right:1px solid #ddd;padding:20px;overflow-y:auto}
.profile-card{padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid #eee}
.avatar{width:60px;height:60px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;border-radius:50%;background:#667eea;color:#fff;font-size:24px;font-weight:bold}
.profile-name{font-size:18px;font-weight:bold}
.profile-info{margin-top:7px;color:#777;font-size:13px;line-height:1.6}
.menu a{display:block;padding:11px 12px;margin-bottom:5px;color:#444;text-decoration:none;border-radius:7px}
.menu a:hover{background:#f0f2ff;color:#667eea}
.chat{flex:1;display:flex;flex-direction:column;min-width:0;background:#f8f9fc}
.chat-title{display:flex;align-items:center;gap:12px;margin-left:25px;margin-right:auto;font-size:18px;font-weight:bold;color:#fff}
.messages{flex:1;min-height:0;overflow-y:auto;padding:20px}
.message{display:flex;align-items:flex-start;width:100%;margin-bottom:10px;line-height:1.5}
.message-time{flex-shrink:0;margin-right:7px;color:#999;font-size:11px;white-space:nowrap}
.message-header{display:inline-flex;align-items:center;flex-shrink:0;gap:5px;margin-right:7px;white-space:nowrap}
.message-user{color:#667eea;font-weight:bold;cursor:pointer;text-decoration:none}
.message-user:hover{text-decoration:underline}
.message-arrow{color:#e85d9e;font-weight:bold;white-space:nowrap}
.message-recipient{color:#e85d9e;font-weight:bold;cursor:pointer;text-decoration:none}
.message-recipient:hover{text-decoration:underline}
.message-text{flex:1;min-width:0;color:#222;overflow-wrap:anywhere;word-break:break-word}
.message-addressed-to-me{background:rgba(232,93,158,.06);border-radius:6px;padding:2px 5px}
.message-mine{background:rgba(102,126,234,.05);border-radius:6px;padding:2px 5px}
.empty{margin-top:60px;text-align:center;color:#999}
.private-minichat{height:115px;flex-shrink:0;display:flex;flex-direction:column;background:#fff;border-top:1px solid #ddd;border-bottom:1px solid #ddd}
.private-minichat-header{height:38px;flex-shrink:0;display:flex;align-items:center;padding:0 12px;background:#f7f8fc;border-bottom:1px solid #eee;color:#555;font-size:13px;font-weight:bold}
.private-tabs{display:flex;gap:5px;padding:5px 10px;overflow-x:auto;flex-shrink:0;border-bottom:1px solid #eee}
.private-tab{flex-shrink:0;padding:5px 10px;border:1px solid #ddd;border-radius:6px;background:#fff;color:#555;font-size:12px;cursor:pointer}
.private-tab:hover{background:#f0f2ff;color:#667eea}
.private-tab.active{background:#667eea;border-color:#667eea;color:#fff}
.private-minichat-messages{flex:1;min-height:0;overflow-y:scroll;padding:7px 12px;font-size:12px}
.private-mini-empty{padding:18px;text-align:center;color:#aaa}
.private-mini-message{margin-bottom:5px;line-height:1.35}
.private-mini-message-name{color:#667eea;font-weight:bold}
.private-mini-message.mine{text-align:left}
.private-mini-message.mine .private-mini-message-name{color:#555}
.message-form{display:flex;gap:10px;padding:15px;flex-shrink:0;background:#fff;border-top:1px solid #ddd}
.message-form input[type=text]{flex:1;min-width:0;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:15px;outline:none}
.message-form input[type=text]:focus{border-color:#667eea}
.message-form button{padding:0 18px;border:0;border-radius:8px;background:#667eea;color:#fff;font-size:15px;cursor:pointer}
.message-form button:hover{background:#596bd4}
#privateSendButton{background:#e85d9e}
#privateSendButton:hover{background:#d94e90}
.online{width:270px;flex-shrink:0;background:#fff;border-left:1px solid #ddd;overflow-y:auto}
.online-header{padding:18px;border-bottom:1px solid #ddd;font-weight:bold}
.online-count{color:#667eea}
.online-user{display:flex;align-items:center;gap:10px;padding:12px 15px;border-bottom:1px solid #f1f1f1;color:#333;text-decoration:none}
.online-user:hover{background:#f7f7ff}
.online-dot{width:9px;height:9px;flex-shrink:0;background:#36c76c;border-radius:50%}
.online-user-name{font-weight:600}
.online-user-info{margin-top:2px;color:#999;font-size:11px}
@media (max-width:700px){.sidebar{display:none}.online{display:none}.message{display:block}.message-time{display:inline-block;margin-right:5px}.message-header{display:inline-flex}.message-text{display:inline}.private-minichat{height:120px}.message-form{gap:6px;padding:10px}.message-form button{padding:0 10px;font-size:13px}}
.chat-date-time{display:inline-block;padding:5px 9px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);border-radius:6px;color:#fff;font-size:17px;font-weight:normal;line-height:1}
.chat-weather{display:inline-block;padding:5px 9px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);border-radius:6px;color:#fff;font-size:17px;font-weight:normal;line-height:1}
</style></head><body>
<header class="header"><div class="logo">💬 Kupitetut - Чат</div><div class="chat-title"><span id="chatDateTime" class="chat-date-time"></span><span id="chatWeather" class="chat-weather" data-city="<?php echo htmlspecialchars($currentUser['city_name'],ENT_QUOTES,'UTF-8'); ?>">🌤️ Загрузка...</span></div><div class="header-right"><a href="profile.php"><?php echo htmlspecialchars($currentUser['username'],ENT_QUOTES,'UTF-8'); ?></a><a class="logout" href="logout.php">Выйти</a></div></header>
<div class="layout">
<aside class="sidebar"><div class="profile-card"><div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($currentUser['username'],0,1)),ENT_QUOTES,'UTF-8'); ?></div><div class="profile-name"><?php echo htmlspecialchars($currentUser['username'],ENT_QUOTES,'UTF-8'); ?></div><div class="profile-info"><?php if(!empty($currentUser['city_name'])): ?>📍 <?php echo htmlspecialchars($currentUser['city_name'],ENT_QUOTES,'UTF-8'); ?><br><?php endif;if(!empty($currentUser['category_name'])): ?>🏷️ <?php echo htmlspecialchars($currentUser['category_name'],ENT_QUOTES,'UTF-8'); ?><?php endif; ?></div></div><nav class="menu"><a href="chat.php">💬 Общий чат</a><a href="users.php">👥 Пользователи</a><a href="private.php">✉️ Личные сообщения</a><a href="profile.php">⚙️ Мой профиль</a><?php if($currentUser['role']==='admin'): ?><a href="admin/index.php">🛠 Админка</a><?php endif; ?></nav></aside>
<main class="chat">
<input type="hidden" id="currentUserId" value="<?php echo (int)$currentUser['id']; ?>">
<div class="messages" id="messages"><?php if(!$messages): ?><div class="empty">Пока нет сообщений.<br>Напишите первым!</div><?php endif; ?><?php foreach($messages as $msg): ?><?php $senderId=(int)$msg['user_id'];$receiverId=!empty($msg['receiver_id'])?(int)$msg['receiver_id']:0;$isMine=$senderId===(int)$currentUser['id'];$isAddressedToMe=$receiverId>0&&$receiverId===(int)$currentUser['id'];$messageClasses='message';if($isMine)$messageClasses.=' message-mine';if($isAddressedToMe)$messageClasses.=' message-addressed-to-me';$messageTime=date('H:i',strtotime($msg['created_at'])); ?><div class="<?php echo htmlspecialchars($messageClasses,ENT_QUOTES,'UTF-8'); ?>" data-id="<?php echo (int)$msg['id']; ?>" data-user-id="<?php echo $senderId; ?>" data-receiver-id="<?php echo $receiverId; ?>"><span class="message-time"><?php echo htmlspecialchars($messageTime,ENT_QUOTES,'UTF-8'); ?></span><span class="message-header"><span class="message-user private-user-select" data-user-id="<?php echo $senderId; ?>" data-username="<?php echo htmlspecialchars($msg['sender_username'],ENT_QUOTES,'UTF-8'); ?>" role="button" tabindex="0"><?php echo htmlspecialchars($msg['sender_username'],ENT_QUOTES,'UTF-8'); ?></span><?php if($receiverId>0): ?><span class="message-arrow">-->>></span><span class="message-recipient private-user-select" data-user-id="<?php echo $receiverId; ?>" data-username="<?php echo htmlspecialchars($msg['receiver_username'],ENT_QUOTES,'UTF-8'); ?>" role="button" tabindex="0"><?php echo htmlspecialchars($msg['receiver_username'],ENT_QUOTES,'UTF-8'); ?></span><?php endif; ?><span>:</span></span><span class="message-text"><?php $messageText=preg_replace('/\s*\R\s*/u',' ',$msg['message']);echo htmlspecialchars($messageText,ENT_QUOTES,'UTF-8'); ?></span></div><?php endforeach; ?></div>
<div class="private-minichat" id="privateMiniChat"><div class="private-minichat-messages" id="privateMiniMessages"><div class="private-mini-empty">Выберите пользователя и начните приватный диалог.</div></div></div>
<form class="message-form" id="messageForm" method="POST" action="api/send_message.php"><input type="hidden" id="privateReceiverId" name="private_receiver_id" value=""><input type="text" id="messageInput" name="message" maxlength="2000" placeholder="Напишите сообщение..." autocomplete="off" required><button type="submit" id="publicSendButton">Отправить</button><button type="button" id="privateSendButton">💌 Приват</button></form>
</main>
<aside class="online"><div class="online-header">🟢 Онлайн: <span class="online-count"><?php echo count($onlineUsers); ?></span></div><?php if(!$onlineUsers): ?><div style="padding:20px;color:#999">Сейчас никого нет онлайн.</div><?php endif; ?><?php foreach($onlineUsers as $onlineUser): ?><a class="online-user" href="private.php?user_id=<?php echo (int)$onlineUser['id']; ?>"><span class="online-dot"></span><div><div class="online-user-name"><?php echo htmlspecialchars($onlineUser['username'],ENT_QUOTES,'UTF-8'); ?></div><div class="online-user-info"><?php if(!empty($onlineUser['city_name'])): ?><?php echo htmlspecialchars($onlineUser['city_name'],ENT_QUOTES,'UTF-8'); ?><?php endif;if(!empty($onlineUser['city_name'])&&!empty($onlineUser['category_name'])): ?> · <?php endif;if(!empty($onlineUser['category_name'])): ?><?php echo htmlspecialchars($onlineUser['category_name'],ENT_QUOTES,'UTF-8'); ?><?php endif; ?></div></div></a><?php endforeach; ?></aside>
</div>
<script src="assets/js/chat.js"></script><script src="assets/js/private_chat.js"></script><script>
(function(){var clock=document.getElementById('chatDateTime');if(!clock)return;function updateTime(){var now=new Date(),days=['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'],dayName=days[now.getDay()],day=String(now.getDate()).padStart(2,'0'),month=String(now.getMonth()+1).padStart(2,'0'),year=now.getFullYear(),hours=String(now.getHours()).padStart(2,'0'),minutes=String(now.getMinutes()).padStart(2,'0'),seconds=String(now.getSeconds()).padStart(2,'0');clock.textContent=dayName+' — '+day+'.'+month+'.'+year+' '+hours+':'+minutes+':'+seconds}updateTime();setInterval(updateTime,1000)})();
</script><script>
(function(){var weather=document.getElementById('chatWeather');if(!weather)return;var city=weather.getAttribute('data-city');if(!city){weather.textContent='🌤️ Погода недоступна';return}var geocodeUrl='https://geocoding-api.open-meteo.com/v1/search?name='+encodeURIComponent(city)+'&count=1&language=ru&format=json',xhr=new XMLHttpRequest();xhr.open('GET',geocodeUrl,true);xhr.onreadystatechange=function(){if(xhr.readyState!==4)return;if(xhr.status!==200){weather.textContent='🌤️ Погода недоступна';return}try{var data=JSON.parse(xhr.responseText);if(!data.results||!data.results.length){weather.textContent='🌤️ Погода недоступна';return}var location=data.results[0];loadWeather(location.latitude,location.longitude,location.name)}catch(e){console.log('Ошибка геокодирования:',e);weather.textContent='🌤️ Погода недоступна'}};xhr.send();function loadWeather(latitude,longitude,locationName){var weatherUrl='https://api.open-meteo.com/v1/forecast?latitude='+encodeURIComponent(latitude)+'&longitude='+encodeURIComponent(longitude)+'&current=temperature_2m,weather_code&temperature_unit=celsius&timezone=auto',weatherXhr=new XMLHttpRequest();weatherXhr.open('GET',weatherUrl,true);weatherXhr.onreadystatechange=function(){if(weatherXhr.readyState!==4)return;if(weatherXhr.status!==200){weather.textContent='🌤️ Погода недоступна';return}try{var data=JSON.parse(weatherXhr.responseText);if(!data.current){weather.textContent='🌤️ Погода недоступна';return}var temperature=Math.round(data.current.temperature_2m),code=parseInt(data.current.weather_code,10),icon=getWeatherIcon(code);weather.textContent=icon+' '+(temperature>0?'+':'')+temperature+'°C · '+locationName}catch(e){console.log('Ошибка погоды:',e);weather.textContent='🌤️ Погода недоступна'}};weatherXhr.send()}function getWeatherIcon(code){if(code===0)return'☀️';if(code===1||code===2)return'🌤️';if(code===3)return'☁️';if(code===45||code===48)return'🌫️';if(code>=51&&code<=57)return'🌦️';if(code>=61&&code<=67)return'🌧️';if(code>=71&&code<=77)return'❄️';if(code>=80&&code<=82)return'🌦️';if(code>=85&&code<=86)return'🌨️';if(code>=95&&code<=99)return'⛈️';return'🌤️'}})();
</script></body></html>
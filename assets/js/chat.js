(function () {

    var messagesBox = document.getElementById('messages');
    var messageForm = document.getElementById('messageForm');
    var messageInput = document.getElementById('messageInput');
    var receiverInput = document.getElementById('privateReceiverId');

    if (!messagesBox) {
        return;
    }

    var currentUserIdElement =
        document.getElementById('currentUserId');

    var currentUserId = 0;

    if (currentUserIdElement) {
        currentUserId =
            parseInt(currentUserIdElement.value, 10) || 0;
    }

    var lastMessageId = 0;

    var existingMessages =
        messagesBox.querySelectorAll('.message');

if(existingMessages.length>0){
    var first=existingMessages[0],
        firstId=first.getAttribute('data-id');

    if(firstId)
        lastMessageId=parseInt(firstId,10);
}
    }


    /*
     * =========================================================
     * HTML ESCAPE
     * =========================================================
     */

    function escapeHtml(text) {

        var div =
            document.createElement('div');

        div.textContent =
            text == null ? '' : String(text);

        return div.innerHTML;
    }


    /*
     * =========================================================
     * ВРЕМЯ
     * =========================================================
     */

    function formatMessageTime(createdAt) {

        if (!createdAt) {
            return '';
        }

        var value =
            String(createdAt);

        var match =
            value.match(
                /(\d{2}):(\d{2})(?::\d{2})?$/
            );

        if (match) {
            return match[1] + ':' + match[2];
        }

        return '';
    }


    /*
     * =========================================================
     * ЕДИНЫЙ ШАБЛОН ПУБЛИЧНОГО СООБЩЕНИЯ
     * =========================================================
     */

    function createMessageHtml(message) {

        var messageId =
            parseInt(message.id, 10) || 0;

        var senderId =
            parseInt(message.user_id, 10) || 0;

        var senderUsername =
            message.sender_username ||
            message.username ||
            'Пользователь';

        var messageTime =
            formatMessageTime(
                message.created_at
            );

        var messageText =
            String(message.message || '')
                .replace(/\s*\r?\n\s*/g, ' ');

        var classes =
            'message';

        if (senderId === currentUserId) {
            classes += ' message-mine';
        }


        return (
            '<div class="' +
                escapeHtml(classes) +
                '"' +
                ' data-id="' +
                messageId +
                '"' +
                ' data-user-id="' +
                senderId +
                '">' +

                '<span class="message-time">' +
                    escapeHtml(messageTime) +
                '</span>' +

                '<span class="message-header">' +

                    '<span ' +
                        'class="message-user private-user-select" ' +
                        'data-user-id="' +
                            senderId +
                        '" ' +
                        'data-username="' +
                            escapeHtml(senderUsername) +
                        '" ' +
                        'role="button" ' +
                        'tabindex="0">' +

                        escapeHtml(senderUsername) +

                    '</span>' +

                    '<span>:</span>' +

                '</span>' +

                '<span class="message-text">' +
                    escapeHtml(messageText) +
                '</span>' +

            '</div>'
        );
    }


    /*
     * =========================================================
     * ДОБАВЛЕНИЕ СООБЩЕНИЯ
     * =========================================================
     */

    function appendMessage(message) {

        var messageId =
            parseInt(message.id, 10) || 0;

        if (!messageId) {
            return;
        }

        if (
            messagesBox.querySelector(
                '.message[data-id="' +
                messageId +
                '"]'
            )
        ) {
            return;
        }

        var html =
            createMessageHtml(message);

        messagesBox.insertAdjacentHTML(
            'afterbegin',
            html
        );

        if (messageId > lastMessageId) {
            lastMessageId = messageId;
        }
    }


    /*
     * =========================================================
     * ЗАГРУЗКА ПУБЛИЧНЫХ СООБЩЕНИЙ
     * =========================================================
     */

    var loadingMessages = false;

    function loadMessages() {

        if (loadingMessages) {
            return;
        }

        loadingMessages = true;

        var xhr =
            new XMLHttpRequest();

        var url =
            'api/get_messages.php' +
            '?after_id=' +
            encodeURIComponent(lastMessageId) +
            '&t=' +
            new Date().getTime();

        xhr.open(
            'GET',
            url,
            true
        );

        xhr.onreadystatechange =
            function () {

                if (xhr.readyState !== 4) {
                    return;
                }

                loadingMessages = false;

                if (
                    xhr.status < 200 ||
                    xhr.status >= 300
                ) {
                    return;
                }

                try {

                    var data =
                        JSON.parse(
                            xhr.responseText
                        );

                    if (
                        !data ||
                        !data.length
                    ) {
                        return;
                    }

                    var wasAtBottom =
                        messagesBox.scrollTop +
                        messagesBox.clientHeight >=
                        messagesBox.scrollHeight - 80;


                    for (
                        var i = 0;
                        i < data.length;
                        i++
                    ) {

                        appendMessage(
                            data[i]
                        );
                    }


                    if (wasAtBottom) {

                        messagesBox.scrollTop =
                            messagesBox.scrollHeight;
                    }

                } catch (error) {

                    console.log(
                        'Ошибка JSON:',
                        error
                    );
                }
            };


        xhr.onerror =
            function () {

                loadingMessages = false;

                console.log(
                    'Ошибка сети при получении сообщений.'
                );
            };


        xhr.send();
    }


    /*
     * =========================================================
     * ОТПРАВКА ТОЛЬКО ПУБЛИЧНОГО СООБЩЕНИЯ
     * =========================================================
     */

    if (messageForm) {

        messageForm.addEventListener(
            'submit',
            function (event) {

                event.preventDefault();

                if (!messageInput) {
                    return;
                }

                var message =
                    messageInput.value.trim();

                if (!message) {
                    return;
                }

                if (
                    messageInput.getAttribute(
                        'data-sending'
                    ) === '1'
                ) {
                    return;
                }

                /*
                 * При отправке в общий чат
                 * получатель всегда сбрасывается.
                 */

                if (receiverInput) {
                    receiverInput.value = '';
                }

                messageInput.setAttribute(
                    'data-sending',
                    '1'
                );

                var xhr =
                    new XMLHttpRequest();

                xhr.open(
                    'POST',
                    'api/send_public.php',
                    true
                );

                xhr.setRequestHeader(
                    'Content-Type',
                    'application/x-www-form-urlencoded; charset=UTF-8'
                );


                xhr.onreadystatechange =
                    function () {

                        if (
                            xhr.readyState !== 4
                        ) {
                            return;
                        }

                        messageInput.removeAttribute(
                            'data-sending'
                        );


                        if (
                            xhr.status < 200 ||
                            xhr.status >= 300
                        ) {

                            alert(
                                'Не удалось отправить сообщение.'
                            );

                            return;
                        }


                        try {

                            var result =
                                JSON.parse(
                                    xhr.responseText
                                );


                            if (
                                !result ||
                                result.success !== true
                            ) {

                                alert(
                                    result &&
                                    result.error
                                        ? result.error
                                        : 'Не удалось отправить сообщение.'
                                );

                                return;
                            }


                            messageInput.value = '';

                            messageInput.placeholder =
                                'Напишите сообщение...';

                            messageInput.focus();

                            loadMessages();

                        } catch (error) {

                            console.log(
                                'Ошибка ответа send_public.php:',
                                error
                            );

                            alert(
                                'Сервер вернул некорректный ответ.'
                            );
                        }
                    };


                xhr.send(
                    'message=' +
                    encodeURIComponent(message)
                );

            },
            false
        );
    }


    /*
     * =========================================================
     * ПЕРВИЧНАЯ ЗАГРУЗКА
     * =========================================================
     */

    loadMessages();


    /*
     * =========================================================
     * AJAX ОБНОВЛЕНИЕ
     * =========================================================
     */

    setInterval(
        loadMessages,
        3000
    );

})();
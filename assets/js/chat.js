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

    /*
     * =========================================================
     * ПОСЛЕДНИЙ ID УЖЕ ЗАГРУЖЕННОГО СООБЩЕНИЯ
     * =========================================================
     */

    var lastMessageId = 0;

    var existingMessages =
        messagesBox.querySelectorAll('.message');

    for (var i = 0; i < existingMessages.length; i++) {

        var existingId =
            parseInt(
                existingMessages[i].getAttribute('data-id'),
                10
            ) || 0;

        if (existingId > lastMessageId) {
            lastMessageId = existingId;
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
     * СОЗДАНИЕ HTML СООБЩЕНИЯ
     * =========================================================
     */

    function createMessageHtml(message) {

        var messageId =
            parseInt(message.id, 10) || 0;

        var senderId =
            parseInt(message.user_id, 10) || 0;

        var receiverId =
            parseInt(message.receiver_id, 10) || 0;

        var senderUsername =
            message.sender_username ||
            message.username ||
            'Пользователь';

        var receiverUsername =
            message.receiver_username ||
            '';

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

        if (
            receiverId > 0 &&
            receiverId === currentUserId
        ) {
            classes += ' message-addressed-to-me';
        }

        var html =
            '<div class="' +
                escapeHtml(classes) +
                '"' +
                ' data-id="' +
                messageId +
                '"' +
                ' data-user-id="' +
                senderId +
                '"' +
                ' data-receiver-id="' +
                receiverId +
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

                    '</span>';

        /*
         * Если сервер когда-нибудь вернёт приватное
         * сообщение через этот API, сохраним его отображение.
         */

        if (
            receiverId > 0 &&
            receiverUsername
        ) {

            html +=
                '<span class="message-arrow">-->>></span>' +

                '<span ' +
                    'class="message-recipient private-user-select" ' +
                    'data-user-id="' +
                        receiverId +
                    '" ' +
                    'data-username="' +
                        escapeHtml(receiverUsername) +
                    '" ' +
                    'role="button" ' +
                    'tabindex="0">' +

                    escapeHtml(receiverUsername) +

                '</span>';
        }

        html +=
                '<span>:</span>' +
            '</span>' +

            '<span class="message-text">' +
                escapeHtml(messageText) +
            '</span>' +

        '</div>';

        return html;
    }


    /*
     * =========================================================
     * ДОБАВЛЕНИЕ НОВЫХ СООБЩЕНИЙ
     * =========================================================
     */

    function appendMessage(message) {

        var messageId =
            parseInt(message.id, 10) || 0;

        if (!messageId) {
            return;
        }

        /*
         * Защита от дублей.
         */

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

        /*
         * В chat.php сообщения находятся в порядке:
         *
         * новое
         * старое
         * старое
         *
         * Поэтому новое сообщение вставляем в начало.
         */

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
     * ЗАГРУЗКА НОВЫХ ПУБЛИЧНЫХ СООБЩЕНИЙ
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
                    console.log(
                        'Ошибка получения сообщений. HTTP ' +
                        xhr.status
                    );

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

                    /*
                     * Сервер отдаёт сообщения DESC:
                     *
                     * 105
                     * 104
                     * 103
                     *
                     * Нам нужно вставить их в обратном порядке:
                     *
                     * 103
                     * 104
                     * 105
                     *
                     * Тогда после insertAdjacentHTML('afterbegin')
                     * итоговый порядок будет:
                     *
                     * 105
                     * 104
                     * 103
                     * старые...
                     */

                    var wasAtTop =
                        messagesBox.scrollTop <= 30;

                    for (
                        var i = data.length - 1;
                        i >= 0;
                        i--
                    ) {

                        appendMessage(
                            data[i]
                        );
                    }

                    /*
                     * Если пользователь находился вверху,
                     * оставляем его возле новых сообщений.
                     *
                     * Если он прокрутил чат вниз — не прыгаем.
                     */

                    if (wasAtTop) {
                        messagesBox.scrollTop = 0;
                    }

                } catch (error) {

                    console.log(
                        'Ошибка JSON при получении сообщений:',
                        error,
                        xhr.responseText
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
     * ОТПРАВКА ПУБЛИЧНОГО СООБЩЕНИЯ
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

                /*
                 * Не отправляем повторно,
                 * пока предыдущий запрос не завершился.
                 */

                if (
                    messageInput.getAttribute(
                        'data-sending'
                    ) === '1'
                ) {
                    return;
                }

                /*
                 * Если это общий чат,
                 * получатель должен быть пустым.
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

                /*
                 * Используем общий send_message.php.
                 * Он сам определяет, что это публичное сообщение.
                 */

                xhr.open(
                    'POST',
                    'api/send_message.php',
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

                            /*
                             * Очищаем поле сразу после успешной отправки.
                             */

                            messageInput.value = '';

                            messageInput.placeholder =
                                'Напишите сообщение...';

                            messageInput.focus();

                            /*
                             * Сразу проверяем сервер,
                             * чтобы сообщение появилось
                             * без ожидания 3 секунд.
                             */

                            loadMessages();

                        } catch (error) {

                            console.log(
                                'Ошибка ответа send_message.php:',
                                error,
                                xhr.responseText
                            );

                            alert(
                                'Сервер вернул некорректный ответ.'
                            );
                        }
                    };

                xhr.onerror =
                    function () {

                        messageInput.removeAttribute(
                            'data-sending'
                        );

                        alert(
                            'Ошибка сети. Сообщение не отправлено.'
                        );
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
     * ПЕРВИЧНАЯ ПРОВЕРКА
     * =========================================================
     */

    loadMessages();


    /*
     * =========================================================
     * АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ
     * =========================================================
     *
     * Проверяем новые сообщения каждые 3 секунды.
     */

    setInterval(
        loadMessages,
        3000
    );

})();
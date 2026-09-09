(function () {

    var messagesBox =
        document.getElementById('privateMessages');

    if (!messagesBox) {
        return;
    }

    var userIdElement =
        document.getElementById('privateUserId');

    if (!userIdElement) {
        return;
    }

    var targetUserId =
        parseInt(
            userIdElement.value,
            10
        );

    if (!targetUserId) {
        return;
    }


    var firstLoad = true;


    function escapeHtml(text) {

        var div =
            document.createElement('div');

        div.textContent = text;

        return div.innerHTML;
    }


    function isAtBottom() {

        return (
            messagesBox.scrollTop +
            messagesBox.clientHeight >=
            messagesBox.scrollHeight - 80
        );
    }


    function scrollToBottom() {

        messagesBox.scrollTop =
            messagesBox.scrollHeight;
    }


    function renderMessages(data) {

        var html = '';

        for (
            var i = 0;
            i < data.length;
            i++
        ) {

            var msg = data[i];

            var mine = false;

            if (window.currentUserId) {

                mine =
                    parseInt(
                        msg.sender_id,
                        10
                    ) ===
                    parseInt(
                        window.currentUserId,
                        10
                    );

            } else {

                mine =
                    parseInt(
                        msg.sender_id,
                        10
                    ) !== targetUserId;

            }


            html +=
                '<div class="pm ' +
                (mine ? 'mine' : '') +
                '">' +

                    '<div class="pm-bubble">' +
                        escapeHtml(
                            msg.message
                        ).replace(
                            /\n/g,
                            '<br>'
                        ) +
                    '</div>' +

                    '<div class="pm-time">' +
                        escapeHtml(
                            msg.created_at
                        ) +
                    '</div>' +

                '</div>';
        }


        if (!html) {

            html =
                '<div class="empty">' +
                'Начните диалог 👋' +
                '</div>';
        }


        messagesBox.innerHTML = html;
    }


    function loadPrivateMessages() {

        /*
         * Запоминаем состояние ДО обновления.
         */

        var atBottom =
            isAtBottom();

        var oldScrollTop =
            messagesBox.scrollTop;


        var xhr =
            new XMLHttpRequest();


        xhr.open(
            'GET',
            'api/get_private.php?user_id=' +
            targetUserId +
            '&t=' +
            new Date().getTime(),
            true
        );


        xhr.onreadystatechange =
            function () {

                if (
                    xhr.readyState !== 4 ||
                    xhr.status !== 200
                ) {
                    return;
                }


                try {

                    var data =
                        JSON.parse(
                            xhr.responseText
                        );


                    if (!data) {
                        return;
                    }


                    renderMessages(data);


                    /*
                     * ПЕРВАЯ загрузка:
                     * оставляем скролл сверху.
                     */

                    if (firstLoad) {

                        messagesBox.scrollTop = 0;

                        firstLoad = false;

                        return;
                    }


                    /*
                     * Если пользователь был внизу —
                     * остаёмся внизу.
                     */

                    if (atBottom) {

                        scrollToBottom();

                    } else {

                        /*
                         * Если пользователь читал историю,
                         * возвращаем его туда же.
                         */

                        messagesBox.scrollTop =
                            oldScrollTop;
                    }


                } catch (e) {

                    console.log(
                        'Ошибка ЛС:',
                        e
                    );
                }

            };


        xhr.send();

    }


    /*
     * Первая загрузка.
     */

    loadPrivateMessages();


    /*
     * Автоматическое обновление.
     */

    setInterval(
        loadPrivateMessages,
        2000
    );


    /*
     * AJAX-отправка приватного сообщения.
     */

    var form =
        document.querySelector('.send-form');


    if (form) {

        form.addEventListener(
            'submit',
            function (event) {

                event.preventDefault();


                var input =
                    form.querySelector(
                        'input[name="message"]'
                    );


                var receiver =
                    form.querySelector(
                        'input[name="receiver_id"]'
                    );


                if (!input || !receiver) {
                    return;
                }


                var message =
                    input.value.trim();


                if (!message) {
                    return;
                }


                var sendButton =
                    form.querySelector(
                        'button[type="submit"]'
                    );


                if (sendButton) {
                    sendButton.disabled = true;
                }


                var xhr =
                    new XMLHttpRequest();


                xhr.open(
                    'POST',
                    'api/send_private.php',
                    true
                );


                xhr.setRequestHeader(
                    'Content-Type',
                    'application/x-www-form-urlencoded'
                );


                xhr.onreadystatechange =
                    function () {

                        if (
                            xhr.readyState !== 4
                        ) {
                            return;
                        }


                        if (
                            xhr.status >= 200 &&
                            xhr.status < 300
                        ) {

                            /*
                             * Очищаем поле.
                             */

                            input.value = '';


                            /*
                             * После отправки
                             * загружаем актуальную историю.
                             */

                            loadPrivateMessages();


                        } else {

                            alert(
                                'Не удалось отправить сообщение.'
                            );
                        }


                        if (sendButton) {
                            sendButton.disabled = false;
                        }

                    };


                xhr.send(
                    'receiver_id=' +
                    encodeURIComponent(
                        receiver.value
                    ) +
                    '&message=' +
                    encodeURIComponent(
                        message
                    )
                );

            },
            false
        );

    }

})();
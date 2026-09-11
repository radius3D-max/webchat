(function () {

    /*
     * =====================================================
     * НАСТРОЙКИ
     * =====================================================
     */

    var messagesBox =
        document.getElementById('privateMessages');

    var userIdElement =
        document.getElementById('privateUserId');

    var targetUserId = 0;


    if (userIdElement) {

        targetUserId =
            parseInt(
                userIdElement.value,
                10
            );

    }


    var firstLoad = true;


    /*
     * =====================================================
     * HTML ESCAPE
     * =====================================================
     */

    function escapeHtml(text) {

        var div =
            document.createElement('div');

        div.textContent =
            text === null ||
            typeof text === 'undefined'
                ? ''
                : String(text);

        return div.innerHTML;
    }


    /*
     * =====================================================
     * ПРОКРУТКА
     * =====================================================
     */

    function isAtBottom() {

        if (!messagesBox) {
            return true;
        }

        return (
            messagesBox.scrollTop +
            messagesBox.clientHeight >=
            messagesBox.scrollHeight - 80
        );
    }


    function scrollToBottom() {

        if (!messagesBox) {
            return;
        }

        messagesBox.scrollTop =
            messagesBox.scrollHeight;
    }


    /*
     * =====================================================
     * СООБЩЕНИЯ
     * =====================================================
     */

    function renderMessages(data) {

        if (!messagesBox) {
            return;
        }

        var html = '';


        for (
            var i = 0;
            i < data.length;
            i++
        ) {

            var msg =
                data[i];

            var mine =
                parseInt(
                    msg.sender_id,
                    10
                ) ===
                parseInt(
                    window.currentUserId,
                    10
                );


            html +=
                '<div class="pm ' +
                (
                    mine
                        ? 'mine'
                        : ''
                ) +
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


        messagesBox.innerHTML =
            html;
    }


    /*
     * =====================================================
     * ЗАГРУЗКА ТЕКУЩЕГО ДИАЛОГА
     * =====================================================
     */

    function loadPrivateMessages() {

        if (
            !messagesBox ||
            !targetUserId
        ) {
            return;
        }


        var atBottom =
            isAtBottom();

        var oldScrollTop =
            messagesBox.scrollTop;


        var xhr =
            new XMLHttpRequest();


        xhr.open(
            'GET',
            'api/get_private.php?user_id=' +
            encodeURIComponent(
                targetUserId
            ) +
            '&t=' +
            new Date().getTime(),
            true
        );


        xhr.onreadystatechange =
            function () {

                if (
                    xhr.readyState !== 4
                ) {
                    return;
                }


                if (
                    xhr.status !== 200
                ) {

                    console.log(
                        'Ошибка get_private.php:',
                        xhr.status
                    );

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


                    renderMessages(
                        data
                    );


                    if (firstLoad) {

                        messagesBox.scrollTop =
                            messagesBox.scrollHeight;

                        firstLoad =
                            false;

                        /*
                         * После открытия диалога
                         * сразу обновляем список.
                         */
                        loadPrivateDialogs();

                        return;
                    }


                    if (atBottom) {

                        scrollToBottom();

                    } else {

                        messagesBox.scrollTop =
                            oldScrollTop;
                    }


                    /*
                     * Обновляем счётчики.
                     */
                    loadPrivateDialogs();

                } catch (e) {

                    console.log(
                        'Ошибка обработки сообщений:',
                        e
                    );

                }

            };


        xhr.send();

    }


    /*
     * =====================================================
     * РЕНДЕР СПИСКА ДИАЛОГОВ
     * =====================================================
     */

    function renderPrivateDialogs(data) {

        var usersContainer =
            document.querySelector(
                '.users'
            );


        if (!usersContainer) {
            return;
        }


        /*
         * Удаляем только элементы,
         * которые созданы JavaScript.
         */

        var oldItems =
            usersContainer.querySelectorAll(
                '.private-dialog-item'
            );


        for (
            var i = 0;
            i < oldItems.length;
            i++
        ) {

            oldItems[i]
                .parentNode
                .removeChild(
                    oldItems[i]
                );
        }


        /*
         * Находим заголовок "Онлайн".
         */

        var onlineHeader =
            null;

        var children =
            usersContainer.children;


        for (
            var j = 0;
            j < children.length;
            j++
        ) {

            var text =
                children[j]
                    .textContent
                    .replace(
                        /\s/g,
                        ''
                    );


            if (
                text.indexOf(
                    '🟢Онлайн'
                ) !== -1
            ) {

                onlineHeader =
                    children[j];

                break;
            }
        }


        /*
         * Если диалогов нет.
         */

        if (
            !data ||
            !data.length
        ) {

            return;
        }


        /*
         * Создаём список.
         */

        for (
            var n = 0;
            n < data.length;
            n++
        ) {

            var dialog =
                data[n];


            var item =
                document.createElement(
                    'a'
                );


            item.className =
                'user-item private-dialog-item';


            /*
             * Активный диалог.
             */

            if (
                targetUserId &&
                parseInt(
                    dialog.id,
                    10
                ) === targetUserId
            ) {

                item.className +=
                    ' active';
            }


            /*
             * Непрочитанные.
             *
             * Если этот диалог сейчас открыт,
             * сообщения уже были помечены прочитанными.
             */

            if (
                parseInt(
                    dialog.unread_count,
                    10
                ) > 0
            ) {

                item.className +=
                    ' has-unread';
            }


            item.href =
                'private.php?user_id=' +
                encodeURIComponent(
                    dialog.id
                );


            /*
             * Индикатор онлайн.
             */

            var dot =
                document.createElement(
                    'span'
                );

            dot.className =
                'dot';


            if (
                dialog.last_activity
            ) {

                var activity =
                    new Date(
                        String(
                            dialog.last_activity
                        ).replace(
                            ' ',
                            'T'
                        )
                    );


                var now =
                    new Date();


                var diff =
                    now.getTime() -
                    activity.getTime();


                if (
                    diff >= 0 &&
                    diff <= 300000
                ) {

                    dot.className +=
                        ' online';
                }
            }


            item.appendChild(
                dot
            );


            /*
             * Информация.
             */

            var info =
                document.createElement(
                    'div'
                );

            info.className =
                'user-info';


            /*
             * Имя.
             */

            var name =
                document.createElement(
                    'div'
                );

            name.className =
                'user-name';


            name.appendChild(
                document.createTextNode(
                    dialog.username
                )
            );


            /*
             * Красный счётчик.
             */

            if (
                parseInt(
                    dialog.unread_count,
                    10
                ) > 0
            ) {

                var badge =
                    document.createElement(
                        'span'
                    );

                badge.className =
                    'unread-badge';


                badge.appendChild(
                    document.createTextNode(
                        String(
                            dialog.unread_count
                        )
                    )
                );


                name.appendChild(
                    badge
                );
            }


            info.appendChild(
                name
            );


            /*
             * Последнее сообщение.
             */

            if (
                dialog.last_message
            ) {

                var preview =
                    document.createElement(
                        'div'
                    );

                preview.className =
                    'dialog-preview';


                var previewText =
                    String(
                        dialog.last_message
                    );


                if (
                    previewText.length > 45
                ) {

                    previewText =
                        previewText.substring(
                            0,
                            45
                        ) +
                        '…';
                }


                preview.appendChild(
                    document.createTextNode(
                        previewText
                    )
                );


                info.appendChild(
                    preview
                );
            }


            item.appendChild(
                info
            );


            /*
             * Вставляем перед блоком "Онлайн".
             */

            if (onlineHeader) {

                usersContainer.insertBefore(
                    item,
                    onlineHeader
                );

            } else {

                usersContainer.appendChild(
                    item
                );
            }
        }
    }


    /*
     * =====================================================
     * ЗАГРУЗКА СПИСКА ДИАЛОГОВ
     * =====================================================
     */

    function loadPrivateDialogs() {

        var xhr =
            new XMLHttpRequest();


        xhr.open(
            'GET',
            'api/get_private_dialogs.php?t=' +
            new Date().getTime(),
            true
        );


        xhr.onreadystatechange =
            function () {

                if (
                    xhr.readyState !== 4
                ) {
                    return;
                }


                if (
                    xhr.status !== 200
                ) {

                    console.log(
                        'Ошибка get_private_dialogs.php:',
                        xhr.status
                    );

                    return;
                }


                try {

                    var data =
                        JSON.parse(
                            xhr.responseText
                        );


                    renderPrivateDialogs(
                        data
                    );

                } catch (e) {

                    console.log(
                        'Ошибка списка диалогов:',
                        e
                    );

                }
            };


        xhr.send();
    }


    /*
     * =====================================================
     * ОТПРАВКА СООБЩЕНИЯ
     * =====================================================
     */

    var form =
        document.querySelector(
            '.send-form'
        );


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


                if (
                    !input ||
                    !receiver
                ) {

                    return;
                }


                var message =
                    input.value.trim();


                if (!message) {
                    return;
                }


                var button =
                    form.querySelector(
                        'button[type="submit"]'
                    );


                if (button) {
                    button.disabled = true;
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

                            input.value =
                                '';


                            loadPrivateMessages();

                            loadPrivateDialogs();

                        } else {

                            alert(
                                'Не удалось отправить сообщение.'
                            );
                        }


                        if (button) {
                            button.disabled = false;
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


    /*
     * =====================================================
     * ПЕРВЫЙ ЗАПУСК
     * =====================================================
     */

    loadPrivateDialogs();


    if (
        messagesBox &&
        targetUserId
    ) {

        loadPrivateMessages();
    }


    /*
     * =====================================================
     * АВТООБНОВЛЕНИЕ
     * =====================================================
     */

    setInterval(
        function () {

            loadPrivateDialogs();

        },
        2000
    );


    if (
        messagesBox &&
        targetUserId
    ) {

        setInterval(
            function () {

                loadPrivateMessages();

            },
            2000
        );
    }
/*
 * =====================================================
 * АВТООБНОВЛЕНИЕ СПИСКА ЛИЧНЫХ ДИАЛОГОВ
 * =====================================================
 */

(function () {

  

            /*
             * Ищем существующий элемент
             * этого пользователя.
             */

            var item =
                document.querySelector(
                    '.private-dialog-item[data-user-id="' +
                    userId +
                    '"]'
                );


            if (!item) {

                /*
                 * Если старый список не имеет
                 * data-user-id, ищем ссылку.
                 */

                var links =
                    document.querySelectorAll(
                        'a[href*="private.php?user_id=' +
                        userId +
                        '"]'
                    );


                if (links.length) {
                    item = links[0];
                }
            }


            if (!item) {
                continue;
            }


            /*
             * Ищем существующий счётчик.
             */

            var badge =
                item.querySelector(
                    '.unread-badge'
                );


            /*
             * Есть непрочитанные.
             */

            if (unread > 0) {

                if (!badge) {

                    badge =
                        document.createElement(
                            'span'
                        );

                    badge.className =
                        'unread-badge';


                    var name =
                        item.querySelector(
                            '.user-name'
                        );


                    if (name) {

                        name.appendChild(
                            badge
                        );
                    }
                }


                if (badge) {

                    badge.textContent =
                        String(unread);
                }


                item.className =
                    item.className
                        .replace(
                            /\bhas-unread\b/g,
                            ''
                        ) +
                    ' has-unread';

            }


            /*
             * Непрочитанных нет.
             */

            else {

                if (badge) {

                    badge.parentNode
                        .removeChild(
                            badge
                        );
                }


                item.className =
                    item.className
                        .replace(
                            /\bhas-unread\b/g,
                            ''
                        );
            }
        }
    }


    /*
     * Проверяем каждые 2 секунды.
     */

    setInterval(
        refreshPrivateDialogs,
        2000
    );


    /*
     * Проверяем сразу.
     */

    refreshPrivateDialogs();

/*
 * =====================================================
 * АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ НЕПРОЧИТАННЫХ ЛС
 * =====================================================
 */

(function () {

    function updatePrivateUnread() {

        var xhr =
            new XMLHttpRequest();

        xhr.open(
            'GET',
            'api/get_private_dialogs.php?t=' +
            new Date().getTime(),
            true
        );

        xhr.onreadystatechange =
            function () {

                if (
                    xhr.readyState !== 4
                ) {
                    return;
                }

                if (
                    xhr.status !== 200
                ) {
                    return;
                }

                try {

                    var dialogs =
                        JSON.parse(
                            xhr.responseText
                        );

                    if (!dialogs) {
                        return;
                    }


                    for (
                        var i = 0;
                        i < dialogs.length;
                        i++
                    ) {

                        updateDialogUnread(
                            dialogs[i]
                        );

                    }

                } catch (e) {

                    console.log(
                        'Ошибка обновления unread:',
                        e
                    );
                }
            };

        xhr.send();
    }


    function updateDialogUnread(dialog) {

        var userId =
            parseInt(
                dialog.id,
                10
            );

        var unread =
            parseInt(
                dialog.unread_count,
                10
            );


        /*
         * Ищем именно ссылку пользователя.
         *
         * Например:
         *
         * private.php?user_id=6
         */

        var item =
            document.querySelector(
                'a[href="private.php?user_id=' +
                userId +
                '"].private-dialog-item'
            );


        /*
         * Если старый HTML ещё не имеет
         * private-dialog-item, ищем просто ссылку.
         */

        if (!item) {

            item =
                document.querySelector(
                    'a[href="private.php?user_id=' +
                    userId +
                    '"]'
                );
        }


        if (!item) {
            return;
        }


        var name =
            item.querySelector(
                '.user-name'
            );


        if (!name) {
            return;
        }


        /*
         * Ищем наш специальный счётчик.
         */

        var badge =
            name.querySelector(
                '.unread-badge'
            );


        /*
         * Если старый PHP уже создал
         * счётчик без класса,
         * определяем его по содержимому.
         */

        if (!badge) {

            var spans =
                name.querySelectorAll(
                    'span'
                );


            for (
                var i = 0;
                i < spans.length;
                i++
            ) {

                if (
                    spans[i].textContent
                        .replace(/\s/g, '')
                        .match(/^[0-9]+$/)
                ) {

                    badge =
                        spans[i];

                    badge.className =
                        'unread-badge';

                    break;
                }
            }
        }


        /*
         * ===============================================
         * ЕСТЬ НЕПРОЧИТАННЫЕ
         * ===============================================
         */

        if (unread > 0) {

            if (!badge) {

                badge =
                    document.createElement(
                        'span'
                    );

                badge.className =
                    'unread-badge';

                name.appendChild(
                    badge
                );
            }


            badge.textContent =
                String(unread);


            /*
             * Красный цвет.
             */

            badge.style.display =
                'inline-block';

            badge.style.minWidth =
                '20px';

            badge.style.padding =
                '2px 6px';

            badge.style.marginLeft =
                '6px';

            badge.style.borderRadius =
                '10px';

            badge.style.background =
                '#e74c3c';

            badge.style.color =
                '#fff';

            badge.style.fontSize =
                '11px';

            badge.style.textAlign =
                'center';

            badge.style.verticalAlign =
                'middle';


            /*
             * Красный элемент списка.
             */

            if (
                item.className
                    .indexOf(
                        'has-unread'
                    ) === -1
            ) {

                item.className +=
                    ' has-unread';
            }

        }


        /*
         * ===============================================
         * ПРОЧИТАНО
         * ===============================================
         */

        else {

            if (badge) {

                badge.parentNode
                    .removeChild(
                        badge
                    );
            }


            /*
             * Убираем has-unread.
             */

            item.className =
                item.className.replace(
                    /\s*has-unread/g,
                    ''
                );
        }
    }


    /*
     * Первый запрос сразу.
     */

    updatePrivateUnread();


    /*
     * Затем каждые 2 секунды.
     */

    setInterval(
        updatePrivateUnread,
        2000
    );

})();
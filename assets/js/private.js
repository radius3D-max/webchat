(function () {

    var messagesBox =
        document.getElementById('privateMessages');

    var userIdElement =
        document.getElementById('privateUserId');

    /*
     * =====================================================
     * ЕСЛИ МЫ НА СТРАНИЦЕ БЕЗ ОТКРЫТОГО ДИАЛОГА
     * =====================================================
     */

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
     * ЭКРАНИРОВАНИЕ HTML
     * =====================================================
     */

    function escapeHtml(text) {

        var div =
            document.createElement('div');

        div.textContent =
            text === null || typeof text === 'undefined'
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
     * ОТОБРАЖЕНИЕ СООБЩЕНИЙ
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
                false;


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
                    ) !==
                    targetUserId;

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


        messagesBox.innerHTML =
            html;
    }


    /*
     * =====================================================
     * ЗАГРУЗКА ТЕКУЩЕГО ПРИВАТНОГО ДИАЛОГА
     * =====================================================
     */

    function loadPrivateMessages() {

        if (!messagesBox || !targetUserId) {
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
            encodeURIComponent(targetUserId) +
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
                     * Первая загрузка.
                     */

                    if (firstLoad) {

                        messagesBox.scrollTop =
                            0;

                        firstLoad =
                            false;

                        /*
                         * После открытия диалога
                         * обновляем список ЛС.
                         */
                        loadPrivateDialogs();

                        return;
                    }


                    /*
                     * Если пользователь был внизу —
                     * остаёмся внизу.
                     */

                    if (atBottom) {

                        scrollToBottom();

                    } else {

                        messagesBox.scrollTop =
                            oldScrollTop;
                    }


                    /*
                     * После прочтения обновляем
                     * красные счётчики.
                     */

                    loadPrivateDialogs();

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
     * =====================================================
     * СОЗДАНИЕ HTML СПИСКА ДИАЛОГОВ
     * =====================================================
     */

    function renderPrivateDialogs(data) {

        var usersContainer =
            document.querySelector('.users');

        if (!usersContainer) {
            return;
        }


        /*
         * Заголовок списка.
         */

        var title =
            usersContainer.querySelector(
                '.users-title'
            );


        /*
         * Блок "Онлайн" сохраняем.
         */

        var onlineTitle =
            null;

        var allItems =
            usersContainer.querySelectorAll(
                '.user-item'
            );


        for (
            var i = 0;
            i < allItems.length;
            i++
        ) {

            /*
             * Первый блок user-item относится
             * к диалогам.
             *
             * Онлайн-блоки будут добавляться ниже.
             */

        }


        /*
         * Удаляем только старые элементы диалогов.
         *
         * Они будут иметь специальный класс:
         * private-dialog-item
         */

        var oldDialogs =
            usersContainer.querySelectorAll(
                '.private-dialog-item'
            );


        for (
            var j = 0;
            j < oldDialogs.length;
            j++
        ) {

            oldDialogs[j].parentNode.removeChild(
                oldDialogs[j]
            );

        }


        /*
         * Находим место перед заголовком "🟢 Онлайн".
         */

        var children =
            usersContainer.children;

        var onlineHeader =
            null;


        for (
            var k = 0;
            k < children.length;
            k++
        ) {

            if (
                children[k].textContent
                    .replace(/\s/g, '')
                    .indexOf('🟢Онлайн') !== -1
            ) {

                onlineHeader =
                    children[k];

                break;
            }
        }


        /*
         * Если диалогов нет.
         */

        if (!data || !data.length) {

            var empty =
                document.createElement('div');

            empty.className =
                'private-dialog-item';

            empty.style.padding =
                '20px';

            empty.style.color =
                '#999';

            empty.innerHTML =
                'Пока нет диалогов.';


            if (onlineHeader) {

                usersContainer.insertBefore(
                    empty,
                    onlineHeader
                );

            } else {

                usersContainer.appendChild(
                    empty
                );
            }

            return;
        }


        /*
         * Создаём диалоги в порядке,
         * который пришёл с сервера.
         */

        for (
            var n = 0;
            n < data.length;
            n++
        ) {

            var dialog =
                data[n];


            var item =
                document.createElement('a');

            item.className =
                'user-item private-dialog-item';


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
             * Непрочитанные сообщения.
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
             * Онлайн.
             */

            var dot =
                document.createElement('span');

            dot.className =
                'dot';


            if (
                dialog.last_activity
            ) {

                var lastActivity =
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
                    lastActivity.getTime();


                if (
                    diff >= 0 &&
                    diff <= 300000
                ) {

                    dot.className +=
                        ' online';

                }
            }


            item.appendChild(dot);


            /*
             * Информация.
             */

            var info =
                document.createElement('div');

            info.className =
                'user-info';


            /*
             * Имя.
             */

            var name =
                document.createElement('div');

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
                    document.createElement('span');

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
                    document.createElement('div');

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
             * Вставляем диалог перед блоком Онлайн.
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
     * ЗАГРУЗКА СПИСКА ЛИЧНЫХ ДИАЛОГОВ
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
                        'Ошибка загрузки списка ЛС:',
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
                        'Ошибка списка ЛС:',
                        e
                    );

                }

            };


        xhr.send();

    }


    /*
     * =====================================================
     * ПЕРВАЯ ЗАГРУЗКА
     * =====================================================
     */

    if (
        messagesBox &&
        targetUserId
    ) {

        loadPrivateMessages();

    } else {

        /*
         * Если диалог ещё не выбран,
         * всё равно загружаем список ЛС.
         */

        loadPrivateDialogs();

    }


    /*
     * =====================================================
     * АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ
     * =====================================================
     *
     * Каждые 2 секунды:
     *
     * - сообщения текущего диалога;
     * - список диалогов;
     * - новые сообщения;
     * - unread.
     *
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
     * AJAX-ОТПРАВКА ПРИВАТНОГО СООБЩЕНИЯ
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


                var sendButton =
                    form.querySelector(
                        'button[type="submit"]'
                    );


                if (sendButton) {

                    sendButton.disabled =
                        true;

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


                            /*
                             * Сразу обновляем сообщения.
                             */

                            loadPrivateMessages();


                            /*
                             * И список диалогов.
                             */

                            loadPrivateDialogs();

                        } else {

                            alert(
                                'Не удалось отправить сообщение.'
                            );

                        }


                        if (sendButton) {

                            sendButton.disabled =
                                false;

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
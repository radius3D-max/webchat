document.addEventListener('DOMContentLoaded', function () {

    /*
     * =========================================================
     * ЭЛЕМЕНТЫ ПРИВАТНОГО МИНИЧАТА
     * =========================================================
     */

    const messageForm =
        document.getElementById('messageForm');

    const messageInput =
        document.getElementById('messageInput');

    const privateReceiverId =
        document.getElementById('privateReceiverId');

    const privateSendButton =
        document.getElementById('privateSendButton');

    const privateMiniChat =
        document.getElementById('privateMiniChat');

    const privateMiniMessages =
        document.getElementById('privateMiniMessages');

    const currentUserElement =
        document.getElementById('currentUserId');


    if (
        !messageForm ||
        !messageInput ||
        !privateReceiverId ||
        !privateSendButton ||
        !privateMiniChat ||
        !privateMiniMessages
    ) {
        console.warn(
            'Private chat: required elements not found'
        );

        return;
    }


    /*
     * =========================================================
     * ТЕКУЩИЙ ПОЛЬЗОВАТЕЛЬ
     * =========================================================
     */

    const currentUserId =
        currentUserElement
            ? parseInt(
                currentUserElement.value,
                10
            )
            : 0;


    /*
     * =========================================================
     * МИНИЧАТ ВСЕГДА ВИДИМ
     * =========================================================
     */

    privateMiniChat.style.display = 'flex';


    /*
     * =========================================================
     * ВЫБОР ПОЛЬЗОВАТЕЛЯ
     * =========================================================
     */

    document.addEventListener(
        'click',
        function (event) {

            const user =
                event.target.closest(
                    '.private-user-select'
                );


            if (!user) {
                return;
            }


            const userId =
                parseInt(
                    user.dataset.userId,
                    10
                );


            const username =
                user.dataset.username ||
                'Пользователь';


            if (
                !userId ||
                userId <= 0
            ) {
                return;
            }


            if (
                currentUserId &&
                userId === currentUserId
            ) {
                return;
            }


privateReceiverId.value = userId;

messageInput.placeholder =
    'Сообщение для ' +
    username +
    '...';

loadPrivateMessages(userId);

messageInput.focus();

        },
        false
    );


    /*
     * =========================================================
     * СОЗДАНИЕ DOM-ЭЛЕМЕНТА СООБЩЕНИЯ
     * =========================================================
     */

    function createMessageElement(message) {

        const element =
            document.createElement('div');


        element.className =
            'private-mini-message';


        /*
         * Запоминаем ID сообщения
         * непосредственно в DOM.
         *
         * Это позволяет при следующем AJAX
         * не создавать его повторно.
         */

        if (message.id != null) {

            element.dataset.messageId =
                String(message.id);

        }


        const senderId =
            parseInt(
                message.sender_id,
                10
            );


        const isMine =
            currentUserId &&
            senderId === currentUserId;


        if (isMine) {

            element.classList.add(
                'mine'
            );

        }


        const senderName =
            message.sender_name ||
            'Пользователь';


        const receiverName =
            message.receiver_name ||
            'Пользователь';


        const text =
            message.message || '';


        const time =
            message.created_at || '';


        element.innerHTML =

            '<span class="private-mini-message-time">' +
                escapeHtml(time) +
            '</span> ' +

            '<span class="private-mini-message-name">' +
                escapeHtml(senderName) +
            '</span> ' +

            '<span class="private-mini-message-arrow">' +
                '→' +
            '</span> ' +

            '<span class="private-mini-message-name">' +
                escapeHtml(receiverName) +
            '</span>' +

            '<span class="private-mini-message-text">' +
                ': ' +
                escapeHtml(text) +
            '</span>';


        return element;

    }


    /*
     * =========================================================
     * ПРОВЕРКА — ЕСТЬ ЛИ УЖЕ ТАКОЕ СООБЩЕНИЕ
     * =========================================================
     */

    function messageAlreadyExists(messageId) {

        if (!messageId) {
            return false;
        }


        return !!privateMiniMessages.querySelector(
            '.private-mini-message[data-message-id="' +
            CSS.escape(String(messageId)) +
            '"]'
        );

    }


    /*
     * =========================================================
     * ДОБАВЛЕНИЕ НОВОГО СООБЩЕНИЯ
     * =========================================================
     *
     * ВАЖНО:
     *
     * Мы НИКОГДА не очищаем privateMiniMessages.
     *
     * Существующий DOM остаётся на месте.
     *
     * Поэтому браузер сам сохраняет нативный scroll.
     * =========================================================
     */

    function appendMessage(message) {

        const messageId =
            message.id != null
                ? String(message.id)
                : '';


        /*
         * Если сообщение уже есть —
         * ничего не делаем.
         */

        if (
            messageId &&
            messageAlreadyExists(messageId)
        ) {
            return false;
        }


        const element =
            createMessageElement(message);


        privateMiniMessages.appendChild(
            element
        );


        return true;

    }


    /*
     * =========================================================
     * ПЕРВОНАЧАЛЬНАЯ ОТРИСОВКА
     * =========================================================
     *
     * Выполняется только один раз.
     *
     * Здесь допускается очистить контейнер,
     * потому что пользователь ещё не взаимодействовал
     * со скроллом.
     * =========================================================
     */

    let firstLoad = true;


    function renderInitialMessages(messages) {

        privateMiniMessages.innerHTML = '';


        if (!messages.length) {

            privateMiniMessages.innerHTML =
                '<div class="private-mini-empty">' +
                    'Пока нет приватных сообщений.' +
                '</div>';

            return;
        }


        messages.forEach(
            function (message) {

                appendMessage(
                    message
                );

            }
        );

    }


    /*
     * =========================================================
     * ЗАГРУЗКА ПРИВАТНЫХ СООБЩЕНИЙ
     * =========================================================
     */

async function loadPrivateMessages(targetUserId) {

        if (!targetUserId) {
            return;
        }

        try {

            const response = await fetch(
                'api/get_private.php?user_id=' +
                encodeURIComponent(targetUserId),
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                throw new Error(
                    'HTTP ' + response.status
                );
            }

            const messages =
                await response.json();

            if (!Array.isArray(messages)) {
                throw new Error(
                    'Некорректный ответ сервера.'
                );
            }

            renderInitialMessages(messages);

        } catch (error) {

            console.error(
                'Private chat load error:',
                error
            );

        }
    }


    /*
     * =========================================================
     * ПРИВАТНАЯ ОТПРАВКА
     * =========================================================
     */

    privateSendButton.addEventListener(
        'click',
        async function (event) {

            event.preventDefault();
            event.stopPropagation();


            const receiverId =
                parseInt(
                    privateReceiverId.value,
                    10
                );


            if (!receiverId) {

                alert(
                    'Сначала выберите пользователя.'
                );

                return;
            }


            const text =
                messageInput.value.trim();


            if (!text) {

                messageInput.focus();

                return;
            }


            privateSendButton.disabled =
                true;


            try {

                const formData =
                    new FormData();


                formData.append(
                    'receiver_id',
                    receiverId
                );


                formData.append(
                    'message',
                    text
                );


                const response =
                    await fetch(
                        'api/send_private.php',
                        {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        }
                    );


                if (!response.ok) {

                    throw new Error(
                        'HTTP ' +
                        response.status
                    );

                }


                const data =
                    await response.json();


                if (!data.success) {

                    throw new Error(
                        data.error ||
                        'Не удалось отправить сообщение.'
                    );

                }


                /*
                 * Очищаем поле после успешной отправки.
                 */

                messageInput.value = '';


                /*
                 * Не вставляем data.message вручную.
                 *
                 * Следующая загрузка AJAX сама найдёт
                 * новое сообщение и добавит его один раз.
                 */

                await loadPrivateMessages(receiverId);


            } catch (error) {

                console.error(
                    'Private chat send error:',
                    error
                );


                alert(
                    error.message ||
                    'Ошибка отправки сообщения.'
                );


            } finally {

                privateSendButton.disabled =
                    false;

            }

        },
        false
    );


    /*
     * =========================================================
     * HTML ESCAPE
     * =========================================================
     */

    function escapeHtml(value) {

        const div =
            document.createElement('div');


        div.textContent =
            value == null
                ? ''
                : String(value);


        return div.innerHTML;

    }

    /*
     * =========================================================
     * АВТООБНОВЛЕНИЕ
     * =========================================================
     */

setInterval(
    function () {
        const receiverId =
            parseInt(
                privateReceiverId.value,
                10
            );

        if (receiverId) {
            loadPrivateMessages(receiverId);
        }
    },
    3000
);

});
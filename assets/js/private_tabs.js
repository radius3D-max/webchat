document.addEventListener('DOMContentLoaded', function () {

    /*
     * =========================================================
     * ELEMENTS
     * =========================================================
     */

    const privateMiniChat =
        document.getElementById('privateMiniChat');

    const privateTabs =
        document.getElementById('privateTabs');

    const privateMiniMessages =
        document.getElementById('privateMiniMessages');

    const privateReceiverId =
        document.getElementById('privateReceiverId');

    const messageInput =
        document.getElementById('messageInput');


    /*
     * Если необходимых элементов нет —
     * ничего не делаем.
     */

    if (
        !privateMiniChat ||
        !privateTabs ||
        !privateMiniMessages ||
        !privateReceiverId
    ) {
        console.warn(
            'Private tabs: required elements not found'
        );

        return;
    }


    /*
     * =========================================================
     * STATE
     * =========================================================
     */

    const privateUsers = {};

    let activeUserId = null;


    /*
     * =========================================================
     * HELPERS
     * =========================================================
     */

    function normalizeUserId(value) {

        const id = parseInt(value, 10);

        if (
            Number.isNaN(id) ||
            id <= 0
        ) {
            return null;
        }

        return id;
    }


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
     * SHOW MINI CHAT
     * =========================================================
     */

    function showMiniChat() {

        privateMiniChat.style.display = '';
    }


    /*
     * =========================================================
     * CREATE TAB
     * =========================================================
     */

    function createPrivateTab(
        userId,
        username
    ) {

        userId =
            normalizeUserId(userId);

        if (!userId) {
            return null;
        }


        /*
         * Запоминаем пользователя.
         */

        privateUsers[userId] = {
            id: userId,
            username:
                username ||
                'Пользователь'
        };


        /*
         * Проверяем,
         * существует ли уже вкладка.
         */

        let tab =
            privateTabs.querySelector(
                '.private-tab[data-user-id="' +
                userId +
                '"]'
            );


        /*
         * Если вкладка уже есть —
         * просто возвращаем её.
         */

        if (tab) {
            return tab;
        }


        /*
         * Удаляем стартовую вкладку
         * "Нет выбранного диалога".
         *
         * Она не имеет data-user-id.
         */

        const emptyTab =
            privateTabs.querySelector(
                '.private-tab:not([data-user-id])'
            );

        if (emptyTab) {
            emptyTab.remove();
        }


        /*
         * Создаём новую вкладку.
         */

        tab =
            document.createElement('button');

        tab.type = 'button';

        tab.className =
            'private-tab';

        tab.dataset.userId =
            String(userId);


        /*
         * Имя пользователя.
         */

        const name =
            document.createElement('span');

        name.className =
            'private-tab-name';

        name.textContent =
            username ||
            'Пользователь';


        /*
         * Кнопка закрытия.
         */

        const close =
            document.createElement('span');

        close.className =
            'private-tab-close';

        close.title =
            'Закрыть';

        close.textContent =
            '×';


        tab.appendChild(name);

        tab.appendChild(close);

        privateTabs.appendChild(tab);


        return tab;
    }


    /*
     * =========================================================
     * ACTIVATE TAB
     * =========================================================
     */

    function activatePrivateTab(userId) {

        userId =
            normalizeUserId(userId);

        if (!userId) {
            return;
        }


        activeUserId =
            userId;


        /*
         * Записываем выбранного получателя
         * в hidden input.
         */

        privateReceiverId.value =
            String(userId);


        /*
         * Показываем миничат.
         */

        showMiniChat();


        /*
         * Переключаем CSS active.
         */

        const tabs =
            privateTabs.querySelectorAll(
                '.private-tab[data-user-id]'
            );


        tabs.forEach(function (tab) {

            const tabUserId =
                normalizeUserId(
                    tab.dataset.userId
                );

            tab.classList.toggle(
                'active',
                tabUserId === userId
            );

        });


        /*
         * Меняем placeholder.
         */

        const user =
            privateUsers[userId];

        if (
            user &&
            messageInput
        ) {

            messageInput.placeholder =
                'Сообщение для ' +
                user.username +
                '...';
        }


        /*
         * Пока просто показываем,
         * какой диалог выбран.
         *
         * Загрузку истории подключим
         * следующим шагом.
         */

        privateMiniMessages.innerHTML =
            '<div class="private-mini-empty">' +
            'Диалог с ' +
            escapeHtml(
                user
                    ? user.username
                    : 'пользователем'
            ) +
            '</div>';
    }


    /*
     * =========================================================
     * CLOSE TAB
     * =========================================================
     */

    function closePrivateTab(userId) {

        userId =
            normalizeUserId(userId);

        if (!userId) {
            return;
        }


        const tab =
            privateTabs.querySelector(
                '.private-tab[data-user-id="' +
                userId +
                '"]'
            );


        if (tab) {
            tab.remove();
        }


        delete privateUsers[userId];


        /*
         * Если закрыли неактивную вкладку —
         * ничего больше не делаем.
         */

        if (activeUserId !== userId) {
            return;
        }


        /*
         * Ищем оставшиеся вкладки.
         */

        const remainingTabs =
            privateTabs.querySelectorAll(
                '.private-tab[data-user-id]'
            );


        /*
         * Есть другие диалоги.
         */

        if (remainingTabs.length > 0) {

            const lastTab =
                remainingTabs[
                    remainingTabs.length - 1
                ];

            const newUserId =
                normalizeUserId(
                    lastTab.dataset.userId
                );


            if (newUserId) {

                activatePrivateTab(
                    newUserId
                );

                return;
            }
        }


        /*
         * Диалогов больше нет.
         */

        activeUserId = null;

        privateReceiverId.value = '';

        privateMiniMessages.innerHTML =
            '<div class="private-mini-empty">' +
            'Выберите пользователя ' +
            'и начните приватный диалог.' +
            '</div>';


        privateMiniChat.style.display =
            'none';


        if (messageInput) {

            messageInput.placeholder =
                'Напишите сообщение...';
        }
    }


    /*
     * =========================================================
     * CLICK ON TABS
     * =========================================================
     */

    privateTabs.addEventListener(
        'click',
        function (event) {

            const closeButton =
                event.target.closest(
                    '.private-tab-close'
                );


            /*
             * Закрытие вкладки.
             */

            if (closeButton) {

                const tab =
                    closeButton.closest(
                        '.private-tab'
                    );

                if (!tab) {
                    return;
                }


                const userId =
                    normalizeUserId(
                        tab.dataset.userId
                    );

                if (userId) {

                    event.preventDefault();

                    closePrivateTab(
                        userId
                    );
                }

                return;
            }


            /*
             * Обычный клик по вкладке.
             */

            const tab =
                event.target.closest(
                    '.private-tab[data-user-id]'
                );

            if (!tab) {
                return;
            }


            const userId =
                normalizeUserId(
                    tab.dataset.userId
                );

            if (!userId) {
                return;
            }


            activatePrivateTab(
                userId
            );
        }
    );


    /*
     * =========================================================
     * WATCH #privateReceiverId
     * =========================================================
     *
     * ВАЖНО:
     *
     * user_menu.js уже записывает сюда:
     *
     * 4
     *
     * Поэтому нам не нужно перехватывать
     * его click-событие.
     *
     * Мы просто наблюдаем за изменением
     * hidden input.
     */

    let previousReceiverId =
        normalizeUserId(
            privateReceiverId.value
        );


    function checkReceiver() {

        const newReceiverId =
            normalizeUserId(
                privateReceiverId.value
            );


        /*
         * Значение не изменилось.
         */

        if (
            newReceiverId ===
            previousReceiverId
        ) {
            return;
        }


        previousReceiverId =
            newReceiverId;


        /*
         * Получатель сброшен.
         */

        if (!newReceiverId) {
            return;
        }


        /*
         * =====================================================
         * ПОЛУЧАЕМ USERNAME
         * =====================================================
         *
         * Ищем username в общем чате.
         */

        const userElement =
            document.querySelector(
                '.private-user-select[data-user-id="' +
                newReceiverId +
                '"]'
            );


        let username =
            'Пользователь';


        if (userElement) {

            username =
                userElement.dataset.username ||
                userElement.textContent.trim() ||
                'Пользователь';
        }


        /*
         * Создаём вкладку.
         */

        createPrivateTab(
            newReceiverId,
            username
        );


        /*
         * Делаем её активной.
         */

        activatePrivateTab(
            newReceiverId
        );
    }


    /*
     * Hidden input не генерирует
     * событие change при обычном
     * присваивании .value = ...
     *
     * Поэтому используем небольшой
     * наблюдатель.
     */

    setInterval(
        checkReceiver,
        100
    );


    /*
     * =========================================================
     * INITIAL STATE
     * =========================================================
     */

    /*
     * На старте миничат скрыт.
     */

    privateMiniChat.style.display =
        'none';


    /*
     * Экспортируем небольшие функции
     * для будущего private_chat.js.
     */

    window.privateTabs = {

        create: createPrivateTab,

        activate: activatePrivateTab,

        close: closePrivateTab,

        getActiveUserId: function () {

            return activeUserId;
        }

    };


    console.log(
        'Private tabs initialized'
    );

});
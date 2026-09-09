(function () {

    var menu = document.getElementById('userMenu');

    if (!menu) {
        return;
    }


    var menuName =
        document.getElementById('userMenuName');

    var profileLink =
        document.getElementById('userMenuProfile');

    var messagesLink =
        document.getElementById('userMenuMessages');

    var friendsLink =
        document.getElementById('userMenuFriends');

    var blockButton =
        document.getElementById('userMenuBlock');

    var reportButton =
        document.getElementById('userMenuReport');


    var selectedUserId = 0;
    var selectedUsername = '';


    /*
     * ---------------------------------------------------------
     * ПОЗИЦИОНИРОВАНИЕ USER MENU
     * ---------------------------------------------------------
     */

    function positionMenu(target) {

        var rect =
            target.getBoundingClientRect();


        menu.style.visibility = 'hidden';
        menu.style.display = 'block';


        var menuWidth =
            menu.offsetWidth;

        var menuHeight =
            menu.offsetHeight;


        var screenWidth =
            document.documentElement.clientWidth;

        var screenHeight =
            document.documentElement.clientHeight;


        var margin = 8;


        var left =
            rect.left;

        var top =
            rect.bottom + 5;


        if (
            left + menuWidth >
            screenWidth - margin
        ) {

            left =
                screenWidth -
                menuWidth -
                margin;
        }


        if (
            top + menuHeight >
            screenHeight - margin
        ) {

            top =
                rect.top -
                menuHeight -
                5;
        }


        if (top < margin) {

            top = margin;
        }


        if (left < margin) {

            left = margin;
        }


        menu.style.left =
            Math.round(left) + 'px';

        menu.style.top =
            Math.round(top) + 'px';

        menu.style.visibility =
            'visible';

        menu.className =
            'user-popup show';
    }


    /*
     * ---------------------------------------------------------
     * ЗАКРЫТИЕ MENU
     * ---------------------------------------------------------
     */

    function closeMenu() {

        menu.className =
            'user-popup';

        menu.style.display = '';
        menu.style.visibility = '';
    }


    /*
     * ---------------------------------------------------------
     * CLICK
     * ---------------------------------------------------------
     */

    document.addEventListener(
        'click',
        function (event) {

            /*
             * =================================================
             * ВАЖНО
             * =================================================
             *
             * .private-user-select НЕ обрабатываем здесь.
             *
             * Этим занимается private_chat.js.
             *
             * Раньше здесь был:
             *
             * event.stopPropagation();
             *
             * из-за чего private_chat.js
             * не получал клик.
             *
             * Теперь username свободно передаёт событие
             * в private_chat.js.
             */


            /*
             * -------------------------------------------------
             * USER MENU TRIGGER
             * -------------------------------------------------
             */

            var target =
                event.target;


            while (
                target &&
                target !== document
            ) {

                if (
                    target.className &&
                    String(
                        target.className
                    ).indexOf(
                        'user-menu-trigger'
                    ) !== -1
                ) {

                    break;
                }


                target =
                    target.parentNode;
            }


            /*
             * -------------------------------------------------
             * НАЙДЕНА ИКОНКА ПОЛЬЗОВАТЕЛЯ
             * -------------------------------------------------
             */

            if (
                target &&
                target.className &&
                String(
                    target.className
                ).indexOf(
                    'user-menu-trigger'
                ) !== -1
            ) {

                event.preventDefault();
                event.stopPropagation();


                selectedUserId =
                    parseInt(
                        target.getAttribute(
                            'data-user-id'
                        ),
                        10
                    );


                if (
                    !selectedUserId ||
                    selectedUserId <= 0
                ) {

                    return;
                }


                selectedUsername =
                    target.getAttribute(
                        'data-username'
                    ) || '';


                menuName.textContent =
                    selectedUsername;


                profileLink.href =
                    'profile.php?user_id=' +
                    selectedUserId;


                messagesLink.href =
                    'private.php?user_id=' +
                    selectedUserId;


                friendsLink.href =
                    'friends.php?user_id=' +
                    selectedUserId;


                positionMenu(target);


                return;
            }


            /*
             * -------------------------------------------------
             * КЛИК ВНУТРИ USER MENU
             * -------------------------------------------------
             */

            if (
                menu.contains &&
                menu.contains(event.target)
            ) {

                return;
            }


            /*
             * -------------------------------------------------
             * КЛИК ВНЕ USER MENU
             * -------------------------------------------------
             */

            closeMenu();

        },
        false
    );


    /*
     * ---------------------------------------------------------
     * RESIZE
     * ---------------------------------------------------------
     */

    window.addEventListener(
        'resize',
        function () {

            closeMenu();

        },
        false
    );


    /*
     * ---------------------------------------------------------
     * BLOCK USER
     * ---------------------------------------------------------
     */

    if (blockButton) {

        blockButton.onclick =
            function () {

                if (!selectedUserId) {
                    return;
                }


                if (
                    !confirm(
                        'Заблокировать этого пользователя в чате?'
                    )
                ) {

                    return;
                }


                var xhr =
                    new XMLHttpRequest();


                xhr.open(
                    'POST',
                    'api/block_user.php',
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

                            closeMenu();


                            alert(
                                'Пользователь заблокирован в чате.'
                            );

                        } else {

                            alert(
                                'Не удалось выполнить блокировку.'
                            );
                        }
                    };


                xhr.send(
                    'user_id=' +
                    encodeURIComponent(
                        selectedUserId
                    )
                );
            };
    }


    /*
     * ---------------------------------------------------------
     * REPORT
     * ---------------------------------------------------------
     */

    if (reportButton) {

        reportButton.onclick =
            function () {

                if (!selectedUserId) {
                    return;
                }


                alert(
                    'Система жалоб будет добавлена следующим шагом.'
                );
            };
    }

})();
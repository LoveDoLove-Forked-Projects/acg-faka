!function () {

    function _Base() {
        const body = $('body');
        const shadeMobile = $('.site-mobile-shade');
        const closeMobileDrawer = () => {
            body.removeClass('site-mobile');
        };

        $(document).on('click', '.layui-nav-tree a', () => {
            $('.net-loading').show();

            if (util.isMobile()) {
                closeMobileDrawer();
            }
        });

        shadeMobile.on('click', function () {
            closeMobileDrawer();
        });

        $('.logout').click(function () {
            message.ask('您是否要注销登录？', function () {
                window.location.href = "/user/authentication/logout";
            });
        });

        if (util.isMobile()) {
            $('.fly-logo').attr('href', 'javascript:void(0)').on('click', function (event) {
                event.preventDefault();
                body.addClass('site-mobile');
            });
        }
    }

    function _Pjax() {

        $(document).pjax('a[target!=_blank]', '#pjax-container', {fragment: '#pjax-container', timeout: 8000});
        $(document).on('pjax:send', function () {
            Loading.show();
        });
        $(document).on('pjax:complete', function () {
            Loading.hide();
        });
    }

    _Base();
    _Pjax();
}();

(function () {
    'use strict';

    const redirectToHome = function () {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        const target = path + '#';

        if (window.location.href.indexOf('#') === -1 || window.location.hash !== '') {
            window.location.replace(target);
        } else {
            window.location.reload();
        }
    };

    const isUnauthorized = function (xhr) {
        return xhr && (xhr.status === 401 || xhr.status === 403);
    };

    if (window.jQuery) {
        window.jQuery(document).ajaxError(function (event, xhr) {
            if (!isUnauthorized(xhr)) {
                return;
            }

            redirectToHome();
        });
    }

    if (window.fetch) {
        const originalFetch = window.fetch.bind(window);

        window.fetch = function () {
            return originalFetch.apply(window, arguments).then(function (response) {
                if (response && (response.status === 401 || response.status === 403)) {
                    redirectToHome();
                }

                return response;
            });
        };
    }
})();

/**
 * Session guard — when the session has ended, sign the person out cleanly
 * instead of letting background requests (autosave, polling, notifications)
 * fail silently.
 *
 * Loaded in <head> of the shared layout, before any page script, so every
 * fetch() on the page goes through it. When the server answers a fetch with the
 * login page (Shield redirects unauthenticated requests there) or a 401, the
 * guard sends the browser to the login page, which explains what happened in a
 * modal and returns them to this page after they sign in again. The failing
 * call is rejected with a SessionExpiredError so callers stop retrying.
 */
(function () {
    'use strict';

    var script = document.currentScript;
    var loginUrl = script && script.dataset.loginUrl || '/login';
    var nativeFetch = window.fetch.bind(window);
    var leaving = false;

    function SessionExpiredError() {
        var err = new Error('Your session has ended.');
        err.name = 'SessionExpiredError';
        return err;
    }

    function isLoginResponse(res) {
        if (res.status === 401) return true;
        if (!res.redirected) return false;
        try { return /\/login\/?$/.test(new URL(res.url).pathname); } catch (e) { return false; }
    }

    function goToLogin() {
        if (leaving) return;
        leaving = true;
        window.SessionGuard.expired = true;

        // The session is gone, so there is nothing left to protect: don't let
        // the "unsaved changes" prompt stand in the way of signing out.
        if (window.UnsavedChanges && window.UnsavedChanges.markClean) window.UnsavedChanges.markClean();

        var sep = loginUrl.indexOf('?') === -1 ? '?' : '&';
        location.replace(loginUrl + sep + 'expired=1&return=' + encodeURIComponent(location.href));
    }

    window.SessionGuard = {
        expired: false,
        // The page is about to navigate to the login, so this never resolves.
        whenRestored: function () { return new Promise(function () {}); },
    };

    window.fetch = function () {
        return nativeFetch.apply(null, arguments).then(function (res) {
            if (isLoginResponse(res)) {
                goToLogin();
                throw SessionExpiredError();
            }
            return res;
        });
    };
})();

/**
 * Connection guard — shows a persistent banner in the shared toast rail
 * when the browser loses its network connection (e.g. the person switches
 * tabs/apps while something drops their wifi, then comes back), and clears
 * it the moment the connection returns.
 *
 * Unlike the flash-message toasts rendered inline in the layout, this one
 * isn't timed — it stays up for as long as navigator.onLine says the
 * connection is down, however long that is, and is re-synced on
 * visibilitychange so coming back to a backgrounded tab reflects the
 * current state even if an online/offline event was somehow missed.
 */
(function () {
    'use strict';

    var container = document.getElementById('acToastContainer');
    if (!container) return;

    var banner = null;

    function show() {
        if (banner) return;
        banner = document.createElement('div');
        banner.className = 'ac-toast ac-toast--offline';
        banner.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">wifi_off</span><span class="ac-toast__text">You’re offline. Changes won’t be saved until your connection comes back.</span>';
        container.appendChild(banner);
    }

    function hide() {
        if (!banner) return;
        banner.remove();
        banner = null;
    }

    function sync() {
        if (navigator.onLine) hide(); else show();
    }

    window.addEventListener('offline', show);
    window.addEventListener('online', hide);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) sync();
    });

    sync();
})();

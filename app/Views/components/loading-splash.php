<?php
/**
 * Branded full-page loading splash — shown at the very top of <body>, once
 * per browser session (guarded by sessionStorage in the script below).
 * Currently included by auth/layout.php only.
 *
 * Deliberately self-contained: all CSS/markup/JS needed to paint and then
 * dismiss itself lives in this one file, inlined (not in an external
 * stylesheet/script), so it paints on the very first frame with no flash
 * of unstyled content and no dependency on other assets finishing first.
 *
 * Timing is intentionally shorter than a typical splash — this plays on
 * EVERY navigation in a data-dense SIS used all day, so the full ~3.4s
 * shimmer sweep from the original design would feel sluggish. It holds
 * only for the ~1.2s logo draw-in, then fades into the page underneath.
 */
?>
<div id="acSplash" aria-hidden="true">
    <svg class="ac-splash__logo" height="100%" viewBox="0 0 1060 203" width="100%" xmlns="http://www.w3.org/2000/svg">
        <g id="ac-splash-icon">
            <path class="ac-splash-architrave" d="M 8 18 L 160 18" fill="none" stroke="#0037b0" stroke-linecap="round" stroke-width="12"></path>
            <g class="ac-splash-scroll-left">
                <circle cx="28" cy="46" fill="#0037b0" r="20"></circle>
                <circle cx="28" cy="46" fill="#ffffff" r="9"></circle>
            </g>
            <g class="ac-splash-scroll-right">
                <circle cx="140" cy="46" fill="#0037b0" r="20"></circle>
                <circle cx="140" cy="46" fill="#ffffff" r="9"></circle>
            </g>
            <path class="ac-splash-frieze" d="M 38 34 L 130 34 L 130 54 L 38 54 Z" fill="#0037b0"></path>
            <path class="ac-splash-pediment" d="M 46 54 L 122 54 L 84 66 Z" fill="#0037b0" opacity="0.9"></path>
            <g class="ac-splash-col-1">
                <rect fill="#0037b0" height="24" rx="2" width="18" x="42" y="74"></rect>
                <rect fill="#0037b0" height="24" rx="2" width="18" x="42" y="104"></rect>
                <rect fill="#0037b0" height="28" rx="2" width="18" x="42" y="134"></rect>
            </g>
            <g class="ac-splash-col-2">
                <rect fill="#0037b0" height="24" rx="2" width="18" x="66" y="74"></rect>
                <rect fill="#0037b0" height="24" rx="2" width="18" x="66" y="104"></rect>
                <rect fill="#0037b0" height="28" rx="2" width="18" x="66" y="134"></rect>
            </g>
            <g class="ac-splash-col-gold">
                <rect fill="#F5A623" height="24" rx="2" width="18" x="90" y="74"></rect>
                <rect fill="#F5A623" height="58" rx="2" width="18" x="90" y="104"></rect>
                <rect fill="#F5A623" height="70" rx="2" width="16" x="114" y="92"></rect>
            </g>
        </g>
        <g class="ac-splash-wordmark">
            <text fill="#0037b0" font-family="Inter, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif" font-size="138" font-weight="800" letter-spacing="1px" x="175" y="152">AKADEMUS</text>
        </g>
    </svg>
</div>

<style>
    #acSplash {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px;
        background: rgba(245, 245, 247, .78);
        backdrop-filter: blur(24px) saturate(160%);
        -webkit-backdrop-filter: blur(24px) saturate(160%);
        opacity: 1;
        transition: opacity 260ms cubic-bezier(0.4, 0, 0.2, 1);
    }

    #acSplash.ac-splash--out {
        opacity: 0;
        pointer-events: none;
    }

    .ac-splash__logo { width: 100%; max-width: 360px; height: auto; }

    :root { --ac-splash-ease: cubic-bezier(0.22, 1, 0.36, 1); }

    .ac-splash-architrave {
        stroke-dasharray: 180;
        stroke-dashoffset: 180;
        animation: acSplashDraw .48s var(--ac-splash-ease) .05s forwards;
    }
    @keyframes acSplashDraw { to { stroke-dashoffset: 0; } }

    .ac-splash-scroll-left, .ac-splash-scroll-right {
        opacity: 0;
        transform-origin: center;
        animation: acSplashPop .45s var(--ac-splash-ease) .15s forwards;
    }
    @keyframes acSplashPop { 0% { opacity: 0; transform: scale(.65); } 100% { opacity: 1; transform: scale(1); } }

    .ac-splash-frieze {
        opacity: 0;
        transform: scaleX(.7);
        transform-origin: center;
        animation: acSplashExpand .42s var(--ac-splash-ease) .22s forwards;
    }
    @keyframes acSplashExpand { to { opacity: 1; transform: scaleX(1); } }

    .ac-splash-pediment {
        opacity: 0;
        transform: translateY(-4px);
        animation: acSplashDrop .4s var(--ac-splash-ease) .28s forwards;
    }
    @keyframes acSplashDrop { to { opacity: 1; transform: translateY(0); } }

    .ac-splash-col-1 { opacity: 0; transform: translateY(18px); animation: acSplashRise .52s var(--ac-splash-ease) .32s forwards; }
    .ac-splash-col-2 { opacity: 0; transform: translateY(18px); animation: acSplashRise .52s var(--ac-splash-ease) .40s forwards; }
    .ac-splash-col-gold { opacity: 0; transform: translateY(22px); animation: acSplashRise .56s var(--ac-splash-ease) .48s forwards; }
    @keyframes acSplashRise { 0% { opacity: 0; transform: translateY(18px); } 100% { opacity: 1; transform: translateY(0); } }

    .ac-splash-wordmark {
        opacity: 0;
        transform: translateX(12px);
        animation: acSplashSettle .5s var(--ac-splash-ease) .58s forwards;
    }
    @keyframes acSplashSettle { 0% { opacity: 0; transform: translateX(12px); } 100% { opacity: 1; transform: translateX(0); } }

    @media (prefers-reduced-motion: reduce) {
        #acSplash { transition-duration: 80ms; }
        .ac-splash-architrave, .ac-splash-scroll-left, .ac-splash-scroll-right, .ac-splash-frieze,
        .ac-splash-pediment, .ac-splash-col-1, .ac-splash-col-2, .ac-splash-col-gold, .ac-splash-wordmark {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
            stroke-dashoffset: 0 !important;
        }
    }
</style>

<script>
    (function () {
        var el = document.getElementById('acSplash');
        if (!el) return;

        // Play once per browser session. Later loads (e.g. a failed login
        // reloading the page) skip it so they don't cover the shake feedback.
        try {
            if (sessionStorage.getItem('acSplashSeen') === '1') { el.remove(); return; }
            sessionStorage.setItem('acSplashSeen', '1');
        } catch (e) { /* storage blocked: just play it */ }

        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        // Reduced motion: dismiss almost immediately instead of holding for
        // an animation that isn't playing. Otherwise hold just long enough
        // for the logo draw-in to finish (~1.15s), not the full design's
        // trailing shimmer sweep — this plays on every navigation, so it
        // has to stay brief.
        var holdMs = reduceMotion ? 60 : 1150;

        var dismissed = false;
        function dismiss() {
            if (dismissed) return;
            dismissed = true;
            el.classList.add('ac-splash--out');
            el.addEventListener('transitionend', function () { el.remove(); }, { once: true });
            // Safety net in case transitionend never fires (e.g. reduced
            // motion shortens the transition enough to race the listener).
            setTimeout(function () { if (el.parentNode) el.remove(); }, 400);
        }

        // Dismiss once BOTH conditions are true: the page has finished
        // loading, and the minimum hold time has passed (so the animation
        // never looks like it's flashing away mid-draw on a fast load, but
        // a slow page load also never leaves the splash hanging past when
        // the page is actually ready).
        var pageReady = document.readyState === 'complete';
        var holdElapsed = false;

        function maybeDismiss() { if (pageReady && holdElapsed) dismiss(); }

        if (!pageReady) {
            window.addEventListener('load', function () { pageReady = true; maybeDismiss(); }, { once: true });
        }
        setTimeout(function () { holdElapsed = true; maybeDismiss(); }, holdMs);

        // Back-forward cache restores: make sure a stale splash never gets
        // stuck visible over a page the browser just resurrected from
        // memory without re-running this script's timers.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted && el.parentNode) el.remove();
        });
    })();
</script>

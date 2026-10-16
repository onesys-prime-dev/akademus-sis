<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    // PHP's actual default timezone (what every raw date()/time()/
    // strtotime() call in this app uses — Config\App::$appTimezone only
    // feeds the app_timezone() helper, it does NOT set this) was silently
    // following the server/php.ini default (Europe/Berlin on this box),
    // six-plus hours behind the Asia/Manila time every date picker in the
    // UI and every "now" comparison (enlistment/registration/grading
    // window checks, timestamps) implicitly assumes. That's why a
    // chairperson could set an enlistment window that had already started
    // by PHT and still see it reported as "not yet open" — the server
    // thought it was still hours before the opens_at time. Set once, here,
    // for every request (HTTP and CLI alike, since pre_system fires for
    // both) rather than patching every individual date() call site.
    date_default_timezone_set('Asia/Manila');

    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

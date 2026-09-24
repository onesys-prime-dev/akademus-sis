<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Brute-force / abuse throttle for the unauthenticated POST endpoints
 * (login, forgot-password). Only POSTs are counted — showing the form is
 * never limited. Two token buckets per request:
 *
 *  - per IP:            30 attempts / minute (stops one host spraying accounts)
 *  - per IP + identity:  5 attempts / minute (stops guessing one account)
 *
 * Buckets refill continuously (CI4 Throttler), so a locked-out user is
 * only waiting for the next token, not for a fixed lockout window. The
 * rejection is a redirect back with a flash 'error', which the auth views
 * already render (and which triggers the login field shake).
 */
class AuthThrottleFilter implements FilterInterface
{
    private const IP_CAPACITY       = 30;
    private const IDENTITY_CAPACITY = 5;
    private const WINDOW_SECONDS    = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return;
        }

        $throttler = service('throttler');
        $ip        = $request->getIPAddress();
        $identity  = strtolower(trim((string) $request->getPost('email')));

        $allowed = $throttler->check('auth-ip-' . md5($ip), self::IP_CAPACITY, self::WINDOW_SECONDS);

        // Only spend from the identity bucket when the IP bucket passed, so a
        // blocked host doesn't also drain the real user's allowance.
        if ($allowed && $identity !== '') {
            $allowed = $throttler->check(
                'auth-id-' . md5($ip . '|' . $identity),
                self::IDENTITY_CAPACITY,
                self::WINDOW_SECONDS
            );
        }

        if ($allowed) {
            return;
        }

        $wait = max(1, (int) $throttler->getTokenTime());

        return redirect()->back()
            ->withInput()
            ->with('error', "Too many attempts. Please wait {$wait} seconds and try again.");
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after.
    }
}

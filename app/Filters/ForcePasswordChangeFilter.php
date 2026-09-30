<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Server-side backstop for the temp-password modal in
 * layouts/admin_console.php. The modal alone only stops a cooperating
 * browser — anyone could otherwise call another endpoint directly (curl,
 * devtools, a saved bookmark) while still flagged users.must_change_password
 * and simply never see it. This blocks every state-changing request from
 * such an account before it reaches its controller.
 *
 * GET/HEAD/OPTIONS are always allowed through so pages (and the modal
 * itself) still render; only mutating requests are blocked, and only for
 * an account that is both signed in and still flagged.
 */
class ForcePasswordChangeFilter implements FilterInterface
{
    /** Left reachable while flagged: the endpoint that clears the flag, and logging out. */
    private const ALLOWED_PATHS = [
        'account/set-temp-password',
        'logout',
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if (! auth()->loggedIn()) {
            return;
        }

        $path = trim(method_exists($request, 'getPath') ? $request->getPath() : $request->getUri()->getPath(), '/');

        if (in_array($path, self::ALLOWED_PATHS, true)) {
            return;
        }

        $flagged = (bool) (db_connect()->table('users')
            ->select('must_change_password')
            ->where('id', auth()->id())
            ->get()
            ->getRowArray()['must_change_password'] ?? 0);

        if (! $flagged) {
            return;
        }

        $message = 'Please set a new password before continuing.';

        if ($request->isAJAX() || str_contains((string) $request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON(['success' => false, 'message' => $message]);
        }

        return redirect()->back()->with('errors', [$message]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after.
    }
}

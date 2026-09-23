<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route-level auth for server-to-server API endpoints (currently just
 * api/students/promote — the Admission -> SIS handoff). Moved out of the
 * controller and onto the route ('filter' => 'apikey' in Routes.php) so the
 * check runs before the controller is even instantiated, matching how every
 * other role group (admin/chairperson/faculty/student) is protected by a
 * route-level filter rather than an inline check. Uses hash_equals() for a
 * constant-time comparison — a plain !== leaks timing information about how
 * many leading bytes of the key matched.
 */
class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $expected = env('API_KEY');
        $provided = $request->getHeaderLine('X-API-KEY');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, (string) $provided)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized',
                ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after.
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login for the operator pages opened directly in a browser (/logs, /seed-product).
 * The log file can contain tokens and customer data, and the seeder writes to the database.
 */
class LogViewerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) config('logviewer.user');
        $password = (string) config('logviewer.password');

        // Fail closed: an unconfigured server exposes nothing
        abort_if($password === '', 404);

        $userMatches = hash_equals($user, (string) $request->getUser());
        $passwordMatches = hash_equals($password, (string) $request->getPassword());

        if (!$userMatches || !$passwordMatches) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Log Viewer"']);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}

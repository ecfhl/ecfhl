<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateAccountPages
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->is('login', 'register', 'account', 'account/*', 'notifications', 'auth/*', 'messages', 'api/messages/*', 'api/notifications/*', 'api/communication/*')) {
            // Account forms contain a session-specific CSRF token and private owner details.
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }
        return $response;
    }
}

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/jobs.php', __DIR__.'/../routes/ai-tips-db.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn()=>\Illuminate\Support\Facades\Route::group([], __DIR__.'/../routes/images.php'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectUsersTo('/account');
        $middleware->encryptCookies(except: ['ecfhl-season-type']);
        $middleware->web(append: [\App\Http\Middleware\AdminAccess::class, \App\Http\Middleware\SeasonSelection::class, \App\Http\Middleware\PrivateAccountPages::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $exception, \Illuminate\Http\Request $request) {
            if ($exception->getStatusCode() !== 419) return null;
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session has expired. Reload this page and try again.'], 419);
            }
            if ($request->isMethod('post') && $request->is('login', 'register')) {
                // A prior submission may already have succeeded and rotated the session token.
                if ($request->user()) return redirect('/account')->header('Cache-Control', 'private, no-store, max-age=0');
                return redirect('/'.$request->path())
                    ->withInput($request->only(['name', 'email', 'team_id', 'remember']))
                    ->withErrors(['session' => 'This form expired. Please enter your password again and submit once.'])
                    ->header('Cache-Control', 'private, no-store, max-age=0');
            }
            return null;
        });
    })->create();

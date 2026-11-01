<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Vercel's proxy: trust forwarded headers (client IP, https).
        if (filter_var(env('LIBRARY_TRUST_PROXIES', false), FILTER_VALIDATE_BOOL)) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Setup mistakes get a readable message instead of a blank "500".
        $setup = fn (string $msg) => response(
            "Setup problem\n\n{$msg}\n\nSee VERCEL-SETUP.md (open Vercel > your project > Logs for technical details).",
            500,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

        // Logged-in ADMINS see the real error on screen (visitors still only get the generic page).
        // Handy on Vercel where you cannot look at files; JSON requests (uploads) get it as {"message": ...}.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface || $e instanceof ValidationException || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException || $e instanceof TokenMismatchException || $e instanceof ModelNotFoundException) {
                return null;
            }
            try {
                $isAdmin = (bool) optional($request->user())->is_admin;
            } catch (\Throwable) {
                $isAdmin = false;
            }
            if (! $isAdmin) {
                return null;
            }

            $where = str_replace(base_path() . '/', '', $e->getFile()) . ':' . $e->getLine();
            $text = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 1500) . "\n at " . $where;

            return $request->expectsJson()
                ? response()->json(['message' => 'Server error: ' . $text], 500)
                : response("Error (visible to admins only)\n\n{$text}\n\nCopy this and send it to whoever is fixing the app.", 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        });

        $exceptions->render(function (MissingAppKeyException $e) use ($setup) {
            return $setup('APP_KEY is missing. Add it under Vercel > Settings > Environment Variables, then redeploy.');
        });

        $exceptions->render(function (QueryException $e) use ($setup) {
            if (config('app.debug')) {
                return null;
            }
            $m = $e->getMessage();
            $tables = str_contains($m, 'does not exist') || str_contains($m, 'no such table') || str_contains($m, "doesn't exist");

            return $setup($tables
                ? 'The database is connected but its tables do not exist yet. Set LIBRARY_AUTO_SETUP=true, redeploy, and open the site again.'
                : 'Cannot use the database. Check DB_URL (or DATABASE_URL) in the Vercel environment variables.');
        });

        $exceptions->render(function (\RuntimeException $e) use ($setup) {
            if (str_contains($e->getMessage(), 'Unsupported cipher')) {
                return $setup('APP_KEY is not valid. It must look like base64:... (32 random bytes). Copy it exactly from .env.vercel.');
            }
            if (str_contains($e->getMessage(), 'LIBRARY_MASTER_KEY')) {
                return $setup('LIBRARY_MASTER_KEY is missing or invalid. Copy it exactly from .env.vercel.');
            }

            return null;
        });
    })->create();

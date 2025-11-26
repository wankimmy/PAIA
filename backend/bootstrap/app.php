<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global security headers
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\EnsureAuthorizedEmail::class, // Restrict to authorized email only
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/auth/request-otp',
            'api/auth/verify-otp',
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
        
        // Rate limiting for API routes
        $middleware->throttleApi('60,1'); // 60 requests per minute
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Ensure API errors return JSON without data leakage
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                // Log the full error for debugging (server-side only)
                \Log::error('API Error', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                ]);
                
                // Return generic error message to client (no sensitive data)
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                
                return response()->json([
                    'error' => 'An error occurred',
                    'message' => config('app.debug') ? $e->getMessage() : 'Please try again later.',
                ], $statusCode);
            }
        });
    })->create();


<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
<<<<<<< HEAD
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
=======
>>>>>>> 08ed6007b014c43be8e6804161c672aea09a4410
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
<<<<<<< HEAD
        // Don't use statefulApi for mobile apps - use pure token authentication
        // Ensure API routes don't get web middleware (CSRF, sessions, cookies)
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Register Spatie Permission middleware aliases
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle authentication exceptions for API routes
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });
        
        // Handle route not found exceptions (when Laravel tries to redirect to 'login' route)
        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, $request) {
            if ($request->is('api/*') && str_contains($e->getMessage(), 'login')) {
                return response()->json([
                    'message' => 'Unauthenticated.'
                ], 401);
            }
        });
=======
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
>>>>>>> 08ed6007b014c43be8e6804161c672aea09a4410
    })->create();

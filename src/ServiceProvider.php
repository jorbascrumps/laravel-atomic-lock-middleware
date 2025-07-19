<?php

use Illuminate\Routing\Router;
use Jorbascrumps\AtomicLockMiddleware\Http\Middleware\AtomicLockMiddleware;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/atomic-lock-middleware.php' => config_path('atomic-lock-middleware.php'),
        ]);

        $router->aliasMiddleware(AtomicLockMiddleware::ALIAS, AtomicLockMiddleware::class);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/atomic-lock-middleware.php', 'atomic-lock-middleware');
    }
}

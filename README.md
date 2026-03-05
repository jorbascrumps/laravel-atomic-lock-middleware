# Laravel Atomic Lock Middleware
A Laravel middleware for preventing concurrent execution of routes using distributed atomic locks. Protect critical operations like payment processing, inventory updates, and resource modifications from race conditions.

> [!WARNING]
> This package is in **early development** and should be considered **experimental**. Expect frequent changes, incomplete features, and breaking updates.
>
> Contributions and feedback are welcome, but use in production is **not recommended** until a stable version is tagged.
>
> Track progress and open issues here: [GitHub Issues](https://github.com/jorbascrumps/laravel-atomic-lock-middleware/issues)

## Installation

```bash
composer require jorbascrumps/laravel-atomic-lock-middleware
```
> [!CAUTION]
> This middleware requires a cache driver that supports atomic locks (redis, memcached, dynamodb, or database). File and array cache drivers will not work correctly in distributed environments.

## Why This Package?

Laravel's built-in `->block()` method locks routes per-session (preventing the same user from double-submitting). This package enables **resource-level locking across all users**, preventing race conditions when multiple users interact with the same resource simultaneously.

**Use Cases:**
- Prevent multiple admins from editing the same record
- Ensure inventory updates process one at a time
- Lock payment processing for specific orders
- Protect critical sections in stateless APIs
- Global locks for system-wide operations

## Basic Usage

### Apply to Routes

The simplest usage applies the middleware with default settings:

```php
use Illuminate\Support\Facades\Route;

Route::put('/orders/{order}/process', ProcessOrderController::class)
    ->middleware('lock');
```

By default, this creates a lock key based on the route and its method (e.g., `route:{hash}` where hash is an md5 hash of `REQUEST_METHOD:REQUEST_PATH`), preventing concurrent processing of the same order.

### Customize Timeout

Specify how long to wait for the lock (in seconds):

```php
Route::post('/inventory/{product}/update', UpdateInventoryController::class)
    ->middleware('lock:30'); // Wait up to 30 seconds for lock
```

### Custom Lock Keys

For more control, provide a custom lock key:

```php
Route::post('/subscriptions', CreateSubscriptionController::class)
    ->middleware('lock:10,subscription:create');
```

### Route Groups

Apply to multiple routes at once:

```php
Route::middleware(['auth', 'lock:15'])->group(function () {
    Route::put('/settings/profile', UpdateProfileController::class);
    Route::put('/settings/password', UpdatePasswordController::class);
    Route::delete('/settings/account', DeleteAccountController::class);
});
```

> [!TIP]
> For resource-specific locking in groups, use dynamic lock keys (see [Advanced Usage](#advanced-usage) below).

## Advanced Usage

### Dynamic Lock Key Resolution

In cases where you need more granular control over the lock key you may provide a custom resolver.

```php
use Illuminate\Http\Request;
use Jorbascrumps\AtomicLockMiddleware\Http\Middleware\AtomicLockMiddleware;

Route::post('/orders/{order}/payments', ProcessPaymentController::class)
    ->middleware(
        AtomicLockMiddleware::resolveLockKeyUsing(function (Request $request) {
            return 'payment:order:' . $request->route('order');
        })
    );
```

The callback will be resolved via the container so you can inject the authenticated user or any other dependency you may need.

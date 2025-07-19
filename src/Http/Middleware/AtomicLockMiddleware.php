<?php

namespace Jorbascrumps\AtomicLockMiddleware\Http\Middleware;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Conditionable;
use Laravel\SerializableClosure\SerializableClosure;
use Stringable;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static self lockKey(string $key)
 * @method static self resolveLockKeyUsing(callable $callback)
 * @method static self timeout(int $timeout)
 */
class AtomicLockMiddleware implements Stringable
{
    use Conditionable;

    public const ALIAS = 'lock';

    protected ?Lock $lock;

    /**
     * The callback that is responsible for resolving the lock key.
     * @var callable|null
     */
    protected $lockKeyResolver;

    protected int $timeout = 10;

    protected ?string $lockKey = null;

    /**
     * Handle an incoming request.
     *
     * @throws LockTimeoutException
     */
    public function handle(Request $request, Closure $next, int $timeout = null, string $key = null): Response
    {
        $timeout ??= $this->timeout;

        $key = $this->parseLockKey($key);

        $this->lock = Cache::lock($key, $timeout);

        try {
            $this->lock->block($timeout / 2);
        } catch (LockTimeoutException $e) {
            throw new LockTimeoutException('This action cannot be processed at this time. Please try again later.', previous: $e);
        }

        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->lock?->release();
    }

    protected function parseLockKey(string $key = null): string
    {
        $key = rescue(static fn() => unserialize($key, [
            'allowed_classes' => [
                SerializableClosure::class,
            ]
        ]), $key);

        if (is_a($key, SerializableClosure::class)) {
            $key = app()->call($key->getClosure());
        }

        return $key ?: request()->fingerprint();
    }

    protected function resolveLockKeyUsing(callable $resolver): self
    {
        $this->lockKeyResolver = $resolver;

        return $this;
    }

    protected function setParameter(string $name, mixed $value): self
    {
        $this->$name = $value;

        return $this;
    }

    public function __call(string $name, array $arguments): self
    {
        if ($name === 'resolveLockKeyUsing') {
            return $this->resolveLockKeyUsing($arguments[0]);
        }

        if (in_array($name, ['lockKey', 'timeout'])) {
            return $this->setParameter($name, $arguments[0]);
        }

        throw new BadMethodCallException(sprintf('Invalid parameter provided: %s.', $name));
    }

    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'resolveLockKeyUsing') {
            return (new static)->resolveLockKeyUsing($arguments[0]);
        }

        if (in_array($name, ['lockKey', 'timeout'])) {
            return (new static)->setParameter($name, $arguments[0]);
        }

        throw new BadMethodCallException(sprintf('Invalid parameter provided: %s.', $name));
    }

    public function toString(): string
    {
        return (string) $this;
    }

    public function __toString(): string
    {
        $params = collect([
            $this->timeout,
            $this->lockKeyResolver ?
                serialize(new SerializableClosure($this->lockKeyResolver)) :
                $this->lockKey,
        ])
            ->map(fn ($value) => $value ?? '')
            ->implode(',');

        return self::ALIAS . ':' . $params;
    }
}

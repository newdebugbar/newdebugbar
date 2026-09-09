<?php

namespace NewDebugBar\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Throwable;

/** Builds privacy-safe authentication and session context for a request. */
final class RequestContext
{
    public function __construct(private readonly int $maxKeys = 100) {}

    /** @param list<string|Closure> $middleware @return array<string, mixed> */
    public function authentication(Request $request, array $middleware): array
    {
        $guard = $this->guard($middleware);

        try {
            $user = $request->user($guard);
        } catch (Throwable) {
            $user = null;
        }

        return [
            'guard' => $guard,
            'authenticated' => $user instanceof Authenticatable,
            'model' => $user instanceof Authenticatable ? $user::class : null,
            'identifier' => $user instanceof Authenticatable
                ? $this->identifier($user)
                : null,
            'identifier_policy' => 'keyed_hash',
        ];
    }

    /** @return array<string, mixed> */
    public function session(Request $request): array
    {
        try {
            if (! $request->hasSession() || ! $request->session()->isStarted()) {
                return $this->emptySession();
            }

            $session = $request->session();
            $keys = array_values(array_map('strval', array_keys($session->all())));
            $flash = $session->get('_flash', []);
            $flashKeys = array_values(array_unique(array_map('strval', [
                ...(array) ($flash['old'] ?? []),
                ...(array) ($flash['new'] ?? []),
            ])));
            $errors = $session->get('errors');
            $errorBags = $errors instanceof ViewErrorBag ? array_keys($errors->getBags()) : [];

            return [
                'present' => true,
                'driver' => (string) config('session.driver', 'unknown'),
                'key_count' => count($keys),
                'keys' => array_slice($keys, 0, $this->maxKeys),
                'keys_dropped' => max(0, count($keys) - $this->maxKeys),
                'flash_keys' => array_slice($flashKeys, 0, $this->maxKeys),
                'error_bag_present' => $errors instanceof ViewErrorBag,
                'error_bags' => array_slice(array_values(array_map('strval', $errorBags)), 0, $this->maxKeys),
            ];
        } catch (Throwable) {
            return $this->emptySession();
        }
    }

    /** @param list<string|Closure> $middleware */
    private function guard(array $middleware): string
    {
        foreach ($middleware as $entry) {
            if (! is_string($entry) || ! str_starts_with($entry, 'auth:')) {
                continue;
            }

            $guard = str($entry)->after('auth:')->before(',')->toString();

            if ($guard !== '') {
                return $guard;
            }
        }

        return (string) config('auth.defaults.guard', 'web');
    }

    private function identifier(Authenticatable $user): string
    {
        $value = (string) $user->getAuthIdentifier();
        $key = (string) config('app.key', 'newdebugbar-local');

        return 'hmac:'.substr(hash_hmac('sha256', $user::class."\0".$value, $key), 0, 16);
    }

    /** @return array<string, mixed> */
    private function emptySession(): array
    {
        return [
            'present' => false,
            'driver' => (string) config('session.driver', 'unknown'),
            'key_count' => 0,
            'keys' => [],
            'keys_dropped' => 0,
            'flash_keys' => [],
            'error_bag_present' => false,
            'error_bags' => [],
        ];
    }
}

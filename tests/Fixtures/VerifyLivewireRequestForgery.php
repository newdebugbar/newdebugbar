<?php

namespace NewDebugBar\Tests\Fixtures;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

/** Makes Laravel perform its real token check for the armed browser fixture. */
final class VerifyLivewireRequestForgery extends VerifyCsrfToken
{
    public const COOKIE = 'ndb_verify_forgery';

    public function handle($request, Closure $next)
    {
        if (! $request->cookies->has(self::COOKIE) || ! $this->isLivewireUpdate($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /** Laravel normally skips this check while its test runner is active. */
    protected function runningUnitTests(): bool
    {
        return false;
    }

    /** Keep the fixture meaningful on Laravel versions that trust same-origin requests. */
    protected function hasValidOrigin($request): bool
    {
        return false;
    }

    private function isLivewireUpdate($request): bool
    {
        return $request->isMethod('POST') && $request->hasHeader('X-Livewire');
    }
}

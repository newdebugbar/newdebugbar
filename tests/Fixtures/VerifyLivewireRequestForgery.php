<?php

namespace NewDebugBar\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Performs the forgery check Laravel skips while the app is testing.
 *
 * Without it no test can rotate a session token and watch the stale one come
 * back 419, which is the whole failure this suite needs to reproduce. It runs
 * only for pages that armed it, so the rest of the browser suite is untouched.
 */
final class VerifyLivewireRequestForgery
{
    public const COOKIE = 'ndb_verify_forgery';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->cookies->has(self::COOKIE) || ! $this->isLivewireUpdate($request)) {
            return $next($request);
        }

        $presented = $request->header('X-CSRF-TOKEN') ?? $request->input('_token');

        if (is_string($presented) && hash_equals($request->session()->token(), $presented)) {
            return $next($request);
        }

        return response('', 419);
    }

    private function isLivewireUpdate(Request $request): bool
    {
        return $request->isMethod('POST') && $request->hasHeader('X-Livewire');
    }
}

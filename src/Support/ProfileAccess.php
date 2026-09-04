<?php

namespace NewDebugBar\Support;

use Illuminate\Http\Request;
use Throwable;

/** Checks current package state and optional HTTP access before exposing saved profiles. */
final class ProfileAccess
{
    public function enabled(): bool
    {
        $environments = config('newdebugbar.environments', ['local']);

        return (bool) config('newdebugbar.enabled', true)
            && is_array($environments)
            && app()->environment($environments);
    }

    public function allows(Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $callback = config('newdebugbar.access');

        if ($callback === null) {
            return true;
        }

        try {
            if (is_string($callback) && class_exists($callback)) {
                $callback = app($callback);
            }

            return is_callable($callback) && $callback($request) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function authorize(Request $request): void
    {
        abort_unless($this->allows($request), 404);
    }
}

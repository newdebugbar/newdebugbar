<?php

namespace NewDebugBar\Support;

use Illuminate\Http\Request;
use Throwable;

/** Decides whether an application request can produce a safe stored profile. */
final class RequestEligibility
{
    public function allows(Request $request): bool
    {
        if (! app(ProfileAccess::class)->enabled()) {
            return false;
        }

        $excluded = config('newdebugbar.except', []);

        foreach (is_array($excluded) ? $excluded : [] as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->is(ltrim($pattern, '/') ?: '/')) {
                return false;
            }
        }

        if ($request->is('__newdebugbar/*') || $this->isLivewireAsset($request)) {
            return false;
        }

        if ($request->headers->has('X-Livewire')) {
            return $this->isHostLivewireRequest($request);
        }

        return true;
    }

    private function isHostLivewireRequest(Request $request): bool
    {
        $messages = $request->input('components');

        if (! is_array($messages) || $messages === []) {
            return false;
        }

        $hostMessage = false;

        foreach ($messages as $message) {
            if (! is_array($message) || ! is_string($message['snapshot'] ?? null)) {
                return false;
            }

            try {
                $snapshot = json_decode($message['snapshot'], true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return false;
            }

            $name = is_array($snapshot) ? data_get($snapshot, 'memo.name') : null;

            if (! is_string($name) || $name === '') {
                return false;
            }

            if ($name !== 'newdebugbar.toolbar') {
                $hostMessage = true;
            }
        }

        return $hostMessage;
    }

    private function isLivewireAsset(Request $request): bool
    {
        return $request->isMethod('GET')
            && preg_match('#\Alivewire-[0-9a-f]{8}/livewire(?:\.min)?\.js\z#i', $request->path()) === 1;
    }
}

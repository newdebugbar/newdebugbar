<?php

namespace NewDebugBar\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hands the toolbar the session's current CSRF token.
 *
 * A host request can rotate the token mid-page — logging in regenerates the
 * session — which leaves the token embedded in the page, and therefore every
 * later toolbar request, stale.
 */
final class CsrfTokenController
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse(['token' => $request->session()->token()]);
    }
}

<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

/**
 * The bar notices a profile by calling its toolbar over Livewire, and that call
 * is triggered by the host request that just finished. When that request rotated
 * the session — a login does — the token the page holds is already dead, so the
 * call would come back 419 and Livewire's stock handling would put "This page has
 * expired" in front of a host app that is working perfectly well.
 *
 * The fixture middleware stands in for the server that rotated the token: Laravel
 * skips request-forgery verification while the app environment is "testing", so
 * no test can regenerate a session and watch a real 419 come back.
 */
it('never shows the host app an expired-page dialog of its own making', function () {
    $page = visit('/profiled-livewire-session-rotation?expire=1')
        ->assertPresent('[data-testid="host-page"]');

    DebugBarBrowser::waitForVisibleElement($page, '#newdebugbar');

    $page->script(<<<'JS'
        window.__ndbDialogs = [];
        window.confirm = (message) => {
            window.__ndbDialogs.push(String(message));

            return false;
        };
    JS);

    $page->click('[data-testid="rotate-session"]');
    $page->script('new Promise((resolve) => setTimeout(resolve, 2000))');

    $page
        ->assertScript('document.querySelector("[data-testid=host-session-rotated]").textContent.trim()', 'rotated')
        ->assertScript('window.__ndbDialogs.filter((message) => message.includes("expired")).length', 0);
});

it('uses the live token and keeps answering its own Livewire calls', function () {
    $page = visit('/profiled-livewire-session-rotation?expire=1')
        ->assertPresent('[data-testid="host-page"]');

    DebugBarBrowser::waitForVisibleElement($page, '#newdebugbar');

    $page->script(<<<'JS'
        window.__newdebugbarCsrfRecoveryRequests = 0;
        const originalFetch = window.fetch;
        window.fetch = function (input) {
            try {
                const rawUrl = typeof input === 'string' ? input : input?.url;
                const url = new URL(rawUrl, window.location.href);
                if (url.pathname === '/__newdebugbar/csrf') window.__newdebugbarCsrfRecoveryRequests++;
            } catch {
                // The test counter must not change request behavior.
            }

            return originalFetch.apply(this, arguments);
        };
        undefined;
    JS);

    $page->click('[data-testid="rotate-session"]');
    $page->script('new Promise((resolve) => setTimeout(resolve, 2000))');

    $page
        ->assertScript('window.__newdebugbarCsrfRecoveryRequests', 0)
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));

                return state.laterRequestCount === 1
                    && state.recentProfiles.some((profile) => /^\/livewire-[0-9a-f]{8}\/update$/i.test(profile.path));
            })()
            JS)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="queries"]');

    DebugBarBrowser::waitForDetails($page);

    $page->assertPresent('[data-ndb-inspector-content]');
});

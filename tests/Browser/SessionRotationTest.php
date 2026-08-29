<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

/**
 * The bar notices a profile by calling its toolbar over Livewire, and that call
 * is triggered by the host request that just finished. When that request rotated
 * the session — a login does — the token the page holds is already dead, so the
 * call comes back 419 and Livewire's stock handling puts "This page has expired"
 * in front of a host app that is working perfectly well.
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

it('recovers the live token and keeps answering its own Livewire calls', function () {
    $page = visit('/profiled-livewire-session-rotation?expire=1')
        ->assertPresent('[data-testid="host-page"]');

    DebugBarBrowser::waitForVisibleElement($page, '#newdebugbar');

    $page->click('[data-testid="rotate-session"]');
    $page->script('new Promise((resolve) => setTimeout(resolve, 2000))');

    $page
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="queries"]');

    DebugBarBrowser::waitForDetails($page);

    $page->assertPresent('[data-ndb-inspector-content]');
});

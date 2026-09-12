<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps inspector details working when the host page runs its own Alpine instance', function () {
    $page = visit('/host-alpine')->resize(1280, 900);

    $page
        ->assertScript("getComputedStyle(document.querySelector('[data-testid=\"host-page\"]')).display", 'block')
        ->assertScript("!! document.getElementById('newdebugbar').__livewire")
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-inspector="queries"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertSee('host_alpine_value')
        ->assertDontSee('Collector details could not be loaded.')
        ->assertNoJavaScriptErrors();
});

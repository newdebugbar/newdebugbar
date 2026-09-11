<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps favoriting active and repeatable after Livewire navigation', function () {
    $page = visit('/profiled')
        ->click('[data-testid="host-navigation"]')
        ->waitForText('Second request')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-inspector="models"]');

    DebugBarBrowser::assertInspectorSelected($page, 'models');

    $favorite = '[data-ndb-toggle-favorite="models"]';
    $row = '[data-ndb-inspector="models"]';

    $page
        ->assertCount($row, 1)
        ->assertAttribute($favorite, 'aria-pressed', 'false')
        ->click($favorite)
        ->assertAttribute($favorite, 'aria-pressed', 'true')
        ->assertAttribute($row, 'data-ndb-favorite', 'true');

    DebugBarBrowser::assertInspectorSelected($page, 'models');

    $page
        ->click($favorite)
        ->assertAttribute($favorite, 'aria-pressed', 'false')
        ->assertAttribute($row, 'data-ndb-favorite', 'false');

    DebugBarBrowser::assertInspectorSelected($page, 'models');

    $page
        ->click($favorite)
        ->assertAttribute($favorite, 'aria-pressed', 'true')
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->click('[data-testid="host-navigation"]')
        ->waitForText('First request')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertAttribute($favorite, 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

it('reorders favorites with the keyboard and drag and drop', function () {
    $page = visit('/profiled')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    foreach (['request', 'logs', 'queries'] as $inspector) {
        $page->click("[data-ndb-toggle-favorite=\"{$inspector}\"]");
    }

    DebugBarBrowser::assertFavoriteOrder($page, 'request,logs,queries');

    $page
        ->assertScript('Array.from(document.querySelectorAll("[data-ndb-favorites-heading]")).filter((heading) => heading.offsetParent !== null).length', 1)
        ->assertScript('Array.from(document.querySelectorAll("[data-ndb-inspectors-heading]")).filter((heading) => heading.offsetParent !== null).length', 1)
        ->assertScript(<<<'JS'
            (() => {
                const heading = document.querySelector('[data-ndb-favorites-heading]');
                const firstFavorite = document.querySelector('[data-ndb-inspector][data-ndb-favorite="true"]');

                return (heading.compareDocumentPosition(firstFavorite) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
            })()
            JS);

    $page->keys('[data-ndb-select-inspector="logs"]', 'Shift+ArrowUp');
    DebugBarBrowser::assertFavoriteOrder($page, 'logs,request,queries');

    $page->drag('[data-ndb-inspector="queries"]', '[data-ndb-inspector="logs"]');
    DebugBarBrowser::assertFavoriteOrder($page, 'queries,logs,request');

    $page
        ->refresh()
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::assertFavoriteOrder($page, 'queries,logs,request');

    $page->assertNoJavaScriptErrors();
});

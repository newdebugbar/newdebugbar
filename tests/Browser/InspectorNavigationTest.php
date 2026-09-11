<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('switches every inspector after Livewire navigation with one active state', function () {
    $page = visit('/profiled')
        ->click('[data-testid="host-navigation"]')
        ->waitForText('Second request')
        ->assertPathIs('/profiled-next')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    foreach (['request', 'timeline', 'queries', 'models', 'cache', 'views', 'events', 'logs', 'exceptions', 'models'] as $inspector) {
        DebugBarBrowser::selectInspectorViaPalette($page, $inspector);

        DebugBarBrowser::assertInspectorSelected($page, $inspector);
    }

    $page->assertNoJavaScriptErrors();
});

it('keeps inspector content stable while delayed loading feedback takes over', function () {
    $page = visit('/profiled-rich')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertAttribute('[data-ndb-inspector-stage]', 'aria-busy', 'false')
        ->assertAttribute('[data-ndb-inspector-loading]', 'role', 'status')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-body]');
                const loading = document.querySelector('[data-ndb-inspector-loading]');

                return getComputedStyle(content).transitionProperty.includes('opacity')
                    && loading.closest('[data-ndb-inspector-stage]') !== null;
            })()
            JS);

    $page->script(<<<'JS'
        (() => {
            const root = document.getElementById('newdebugbar');
            const state = Alpine.$data(root);

            state.selected = 'queries';
            state.inspectorLoading = true;
            state.inspectorLoadingIndicator = false;
            state.inspectorTransitioning = true;
            state.syncInspectorPanels();
        })()
        JS);

    $page
        ->assertAttribute('[data-ndb-inspector-stage]', 'aria-busy', 'true')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-inspector-panel="request"]').hidden === false
            JS)
        ->assertScript(<<<'JS'
            getComputedStyle(document.querySelector('[data-ndb-inspector-loading]')).display === 'none'
            JS)
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-inspector-body]').classList.contains('ndb:opacity-0')
            JS);

    $page->script("Alpine.\$data(document.getElementById('newdebugbar')).inspectorLoadingIndicator = true");

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-inspector-loading]');

    $page->assertVisible('[data-ndb-inspector-loading]');

    $page->script(<<<'JS'
        (() => {
            const state = Alpine.$data(document.getElementById('newdebugbar'));

            state.selected = 'request';
            state.inspectorLoading = false;
            state.inspectorLoadingIndicator = false;
            state.inspectorTransitioning = false;
            state.syncInspectorPanels();
        })()
        JS);

    $page
        ->assertAttribute('[data-ndb-inspector-stage]', 'aria-busy', 'false')
        ->assertNoJavaScriptErrors();
});

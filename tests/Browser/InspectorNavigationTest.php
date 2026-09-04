<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('renders a selected section when a background refresh starts in the same turn', function (bool $refreshFirst) {
    $page = visit('/hostile-styles')
        ->click('[data-ndb-toolbar="request"]');

    DebugBarBrowser::waitForDetails($page);
    $refreshFirst = json_encode($refreshFirst, JSON_THROW_ON_ERROR);

    $page->script(<<<JS
        (() => {
            const state = Alpine.\$data(document.getElementById('newdebugbar'));

            if ({$refreshFirst}) {
                state.refreshBackgroundActivity(true);
                state.selectSection('mail');
            } else {
                state.selectSection('mail');
                state.refreshBackgroundActivity(true);
            }
        })()
        JS);

    DebugBarBrowser::waitForDetails($page);
    DebugBarBrowser::assertSectionSelected($page, 'mail');

    $page
        ->assertVisible('[data-ndb-mail-item="1"]')
        ->assertAttribute('[data-ndb-section-stage]', 'aria-busy', 'false')
        ->assertNoJavaScriptErrors();
})->with(['refresh before selection' => true, 'selection before refresh' => false]);

it('switches every section after Livewire navigation with one active state', function () {
    $page = visit('/profiled')
        ->click('[data-testid="host-navigation"]')
        ->waitForText('Second request')
        ->assertPathIs('/profiled-next')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    foreach (['request', 'timeline', 'queries', 'models', 'cache', 'views', 'events', 'logs', 'exceptions', 'models'] as $section) {
        DebugBarBrowser::selectSectionViaPalette($page, $section);

        DebugBarBrowser::assertSectionSelected($page, $section);
    }

    $page->assertNoJavaScriptErrors();
});

it('keeps section content stable while delayed loading feedback takes over', function () {
    $page = visit('/profiled-rich')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertAttribute('[data-ndb-section-stage]', 'aria-busy', 'false')
        ->assertAttribute('[data-ndb-section-loading]', 'role', 'status')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-section-content]');
                const loading = document.querySelector('[data-ndb-section-loading]');

                return getComputedStyle(content).transitionProperty.includes('opacity')
                    && loading.closest('[data-ndb-section-stage]') !== null;
            })()
            JS);

    $page->script(<<<'JS'
        (() => {
            const root = document.getElementById('newdebugbar');
            const state = Alpine.$data(root);

            state.selected = 'queries';
            state.sectionLoading = true;
            state.sectionLoadingIndicator = false;
            state.sectionTransitioning = true;
            state.syncSectionPanels();
        })()
        JS);

    $page
        ->assertAttribute('[data-ndb-section-stage]', 'aria-busy', 'true')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-section-panel="request"]').hidden === false
            JS)
        ->assertScript(<<<'JS'
            getComputedStyle(document.querySelector('[data-ndb-section-loading]')).display === 'none'
            JS)
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-section-content]').classList.contains('ndb:opacity-0')
            JS);

    $page->script("Alpine.\$data(document.getElementById('newdebugbar')).sectionLoadingIndicator = true");

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-section-loading]');

    $page->assertVisible('[data-ndb-section-loading]');

    $page->script(<<<'JS'
        (() => {
            const state = Alpine.$data(document.getElementById('newdebugbar'));

            state.selected = 'request';
            state.sectionLoading = false;
            state.sectionLoadingIndicator = false;
            state.sectionTransitioning = false;
            state.syncSectionPanels();
        })()
        JS);

    $page
        ->assertAttribute('[data-ndb-section-stage]', 'aria-busy', 'false')
        ->assertNoJavaScriptErrors();
});

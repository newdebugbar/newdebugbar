<?php

it('keeps package asset updates inside Livewire navigation', function () {
    $page = visit('/profiled');

    $page->script(<<<'JS'
        window.__newDebugBarNavigationSentinel = true;
        const stylesheet = document.querySelector('link[href*="/__newdebugbar/assets/newdebugbar.css"]');
        stylesheet.href = stylesheet.href.replace(/id=[^&]+/, 'id=stale-test-build');
        JS);

    $page
        ->click('[data-testid="host-navigation"]')
        ->waitForText('Second request')
        ->assertScript('window.__newDebugBarNavigationSentinel === true')
        ->assertCount('#newdebugbar', 1)
        ->assertNoJavaScriptErrors();
});

it('keeps the Livewire inspector available after host updates and request inspection', function () {
    $page = visit('/profiled-livewire')
        ->assertSeeIn('[data-testid="host-counter-value"]', '0')
        ->click('[data-testid="host-counter"] button')
        ->assertSeeIn('[data-testid="host-counter-value"]', '1')
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));

                return state.summary.path === '/profiled-livewire'
                    && state.laterRequestCount === 1
                    && state.recentProfiles.some((profile) =>
                        /^\/livewire-[0-9a-f]{8}\/update$/i.test(profile.path)
                    );
            })()
            JS)
        ->assertSeeIn('[data-ndb-request-badge="toolbar"]', '1')
        ->click('[data-ndb-request-picker-trigger="toolbar"]')
        ->assertVisible('#newdebugbar-request-list-toolbar')
        ->assertScript(<<<'JS'
            (() => {
                const option = Array.from(document.querySelectorAll(
                    '#newdebugbar-request-list-toolbar [data-ndb-request-option]',
                )).find((candidate) => /\/livewire-[0-9a-f]{8}\/update/i.test(candidate.textContent));

                option?.click();

                return option !== undefined;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));

                return /^\/livewire-[0-9a-f]{8}\/update$/i.test(state.summary.path)
                    && state.inspectorOpen === true
                    && state.selected === 'request';
            })()
            JS)
        ->assertVisible('[data-ndb-inspector-panel="request"]')
        ->assertVisible('[data-ndb-select-inspector="livewire"]')
        ->click('[data-ndb-select-inspector="livewire"]')
        ->assertVisible('[data-ndb-inspector-panel="livewire"]')
        ->assertNoJavaScriptErrors();
});

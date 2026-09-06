<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps property shortcuts local to textarea controls', function (string $shortcut) {
    $page = visit('/profiled-livewire')
        ->resize(1024, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::selectSectionViaPalette($page, 'livewire');

    $page
        ->click('[data-ndb-livewire-tab="components"]')
        ->click('[data-ndb-livewire-edit-key$=":count"]')
        ->assertVisible('[data-ndb-livewire-property-popover]')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-livewire-property-popover]')
                .querySelector('kbd, [aria-keyshortcuts]') === null
            JS)
        ->type('input[data-ndb-livewire-edit-control]', '5')
        ->keys('input[data-ndb-livewire-edit-control]', $shortcut)
        ->assertVisible('[data-ndb-livewire-property-popover]')
        ->assertScript(<<<'JS'
            (() => {
                const host = document.querySelector('[data-testid="host-counter"]');

                return window.Livewire.find(host.getAttribute('wire:id')).count === 0;
            })()
            JS)
        ->keys('input[data-ndb-livewire-edit-control]', 'Escape')
        ->assertMissing('[data-ndb-livewire-property-popover]');

    DebugBarBrowser::waitForStableElement($page, '[data-ndb-livewire-property-path="settings"]');

    $page->click('[data-ndb-livewire-property-path="settings"] button[aria-label^="Expand"]');

    DebugBarBrowser::waitForStableElement($page, '[data-ndb-livewire-edit-key$=":settings.enabled"]');

    $page
        ->click('[data-ndb-livewire-edit-key$=":settings.enabled"]')
        ->assertVisible('[data-ndb-livewire-property-popover]')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-livewire-property-popover]')
                .querySelector('kbd, [aria-keyshortcuts]') === null
            JS)
        ->click('[data-ndb-livewire-edit-control][role="switch"]')
        ->keys('[data-ndb-livewire-edit-control][role="switch"]', $shortcut)
        ->assertVisible('[data-ndb-livewire-property-popover]')
        ->assertScript(<<<'JS'
            (() => {
                const host = document.querySelector('[data-testid="host-counter"]');

                return window.Livewire.find(host.getAttribute('wire:id')).settings.enabled === true;
            })()
            JS)
        ->keys('[data-ndb-livewire-edit-control][role="switch"]', 'Escape')
        ->assertMissing('[data-ndb-livewire-property-popover]');

    $page->script(<<<'JS'
        (async () => {
            const host = document.querySelector('[data-testid="host-counter"]');
            const component = window.Livewire.find(host.getAttribute('wire:id'));

            await component.$set('settings.step', null);
        })()
        JS);

    DebugBarBrowser::waitForStableElement($page, '[data-ndb-livewire-edit-key$=":settings.step"]');

    $page
        ->click('[data-ndb-livewire-edit-key$=":settings.step"]')
        ->assertVisible('select[data-ndb-livewire-edit-control]')
        ->assertVisible('textarea[data-ndb-livewire-edit-control]')
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ndb-livewire-property-popover]');
                const textarea = popover.querySelector('textarea');
                const shortcuts = [...popover.querySelectorAll('[aria-keyshortcuts]')];
                const hints = [...popover.querySelectorAll('kbd')];

                return shortcuts.length === 1
                    && shortcuts[0] === textarea
                    && hints.length === 1
                    && textarea.parentElement.contains(hints[0]);
            })()
            JS)
        ->keys('select[data-ndb-livewire-edit-control]', $shortcut)
        ->assertVisible('[data-ndb-livewire-property-popover]')
        ->assertScript(<<<'JS'
            (() => {
                const host = document.querySelector('[data-testid="host-counter"]');

                return window.Livewire.find(host.getAttribute('wire:id')).settings.step === null;
            })()
            JS)
        ->select('select[data-ndb-livewire-edit-control]', 'Integer')
        ->assertVisible('input[data-ndb-livewire-edit-control]')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-livewire-property-popover]')
                .querySelector('kbd, [aria-keyshortcuts]') === null
            JS)
        ->type('input[data-ndb-livewire-edit-control]', '3')
        ->keys('select[data-ndb-livewire-edit-control]', $shortcut)
        ->assertVisible('[data-ndb-livewire-property-popover]')
        ->assertScript(<<<'JS'
            (() => {
                const host = document.querySelector('[data-testid="host-counter"]');

                return window.Livewire.find(host.getAttribute('wire:id')).settings.step === null;
            })()
            JS)
        ->keys('input[data-ndb-livewire-edit-control]', 'Escape')
        ->assertMissing('[data-ndb-livewire-property-popover]')
        ->assertNoJavaScriptErrors();
})->with([
    'Command Enter' => 'Meta+Enter',
    'Control Enter' => 'Control+Enter',
]);

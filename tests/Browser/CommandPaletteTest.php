<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('pins and shrinks an expanded inspector from the command palette', function () {
    visit('/profiled')
        ->resize(1280, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-inspector-action="palette"]')
        ->click('[data-ndb-command="toolbar:bottom-right"]')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom-right')
        ->assertVisible('[data-ndb-corner-request]')
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                return ! state.inspectorOpen && ! state.paletteOpen && state.barVisible;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('uses the command palette, theme preference, and escape layers', function () {
    $page = visit('/profiled')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->click('[data-ndb-toolbar="palette"]')
        ->assertVisible('[role="dialog"][aria-label="Command palette"]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-palette-search]")')
        ->type('[data-ndb-palette-search]', 'pin to top')
        ->keys('[data-ndb-palette-search]', 'Enter')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->refresh()
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->click('[data-ndb-toolbar="palette"]')
        ->type('[data-ndb-palette-search]', 'models')
        ->keys('[data-ndb-palette-search]', 'Enter');

    DebugBarBrowser::assertSectionSelected($page, 'models');

    $page
        ->click('[data-ndb-inspector-action="palette"]')
        ->type('[data-ndb-palette-search]', 'dark theme')
        ->keys('[data-ndb-palette-search]', 'Enter')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->refresh()
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->keys('[data-ndb-inspector-action="palette"]', 'Meta+Shift+P')
        ->assertVisible('[role="dialog"][aria-label="Command palette"]')
        ->keys('[data-ndb-palette-search]', 'Escape')
        ->assertScript('getComputedStyle(document.querySelector("[role=dialog][aria-label=\\"Command palette\\"]")).display === "none"')
        ->assertVisible('[role="dialog"][aria-label="Request inspector"]')
        ->keys('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]', 'Escape')
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->assertNoJavaScriptErrors();
});

it('keeps keyboard focus inside the command palette', function () {
    visit('/profiled')
        ->click('[data-ndb-toolbar="palette"]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-palette-search]")')
        ->keys('[data-ndb-palette-search]', 'Shift+Tab')
        ->assertScript('document.activeElement?.dataset.ndbCommand === "collectors:show"')
        ->keys('[data-ndb-command="collectors:show"]', 'Tab')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-palette-search]")')
        ->assertNoJavaScriptErrors();
});

it('uses translucent command palette hover colors in :dataset mode', function (string $theme) {
    $preferences = json_encode([
        'theme' => $theme,
        'favorites' => [],
    ], JSON_THROW_ON_ERROR);

    visit('/profiled-rich')
        ->assertScript(<<<JS
            (() => {
                localStorage.setItem('newdebugbar.preferences.v1', '{$preferences}');

                return true;
            })()
            JS)
        ->refresh()
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', $theme)
        ->click('[data-ndb-toolbar="palette"]')
        ->hover('[data-ndb-command="section:request"]')
        ->assertScript(<<<'JS'
            (() => {
                const command = document.querySelector('[data-ndb-command="section:request"]');
                const background = getComputedStyle(command).backgroundColor;
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                const alpha = Number(
                    background.match(/\/\s*([\d.]+)\s*\)$/)?.[1]
                        ?? background.match(/,\s*([\d.]+)\s*\)$/)?.[1]
                        ?? 1
                );

                return state.filteredCommands[state.paletteIndex]?.id === 'section:request'
                    && alpha > 0
                    && alpha < 1;
            })()
            JS)
        ->assertNoJavaScriptErrors();
})->with(['light', 'dark']);

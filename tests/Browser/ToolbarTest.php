<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('opens every actionable compact toolbar destination and leaves display facts inert', function () {
    $page = visit('/profiled')
        ->assertPresent('[data-testid="host-page"]')
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->assertMissing('[data-ndb-toolbar-status-meaning]')
        ->assertVisible('[data-ndb-toolbar-action="theme"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->click('[data-ndb-toolbar-action="theme"]')
        ->assertVisible('[data-ndb-theme-menu="toolbar"]')
        ->assertAttribute('[data-ndb-theme-menu="toolbar"] [data-ndb-theme-option="system"]', 'aria-checked', 'true')
        ->click('[data-ndb-theme-menu="toolbar"] [data-ndb-theme-option="dark"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->click('[data-ndb-toolbar-action="theme"]')
        ->assertAttribute('[data-ndb-theme-menu="toolbar"] [data-ndb-theme-option="dark"]', 'aria-checked', 'true')
        ->click('[data-ndb-theme-menu="toolbar"] [data-ndb-theme-option="light"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->assertScript(<<<'JS'
            (() => {
                const theme = document.querySelector('[data-ndb-toolbar-action="theme"]');
                const icon = theme?.querySelector('span:not([style*="display: none"]) svg');

                if (! theme || ! icon) return false;

                const center = (element) => {
                    const bounds = element.getBoundingClientRect();

                    return bounds.top + bounds.height / 2;
                };

                return Math.abs(center(theme) - center(icon)) <= 0.5;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const environment = document.querySelector('[data-ndb-toolbar="environment"]');
                const peak = document.querySelector('[data-ndb-toolbar="memory"]');
                const duration = document.querySelector('[data-ndb-toolbar="duration"]');
                const queries = document.querySelector('[data-ndb-toolbar="queries"]');

                return environment.tagName === 'DIV'
                    && peak.tagName === 'DIV'
                    && environment.closest('button') === null
                    && peak.closest('button') === null
                    && duration.tagName === 'BUTTON'
                    && queries.tagName === 'BUTTON';
            })()
            JS);

    foreach ([
        'expand' => 'request',
        'request' => 'request',
        'duration' => 'request',
        'queries' => 'queries',
    ] as $toolbar => $inspector) {
        $selector = $toolbar === 'expand'
            ? '[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]'
            : "[data-ndb-toolbar=\"{$toolbar}\"]";

        $page
            ->click($selector);

        DebugBarBrowser::assertInspectorSelected($page, $inspector);

        if ($toolbar === 'expand') {
            $page
                ->assertScript('document.querySelector("[data-ndb-header-memory]").textContent.includes("MB")')
                ->assertMissing('[data-ndb-header-status-meaning]')
                ->assertScript(<<<'JS'
                    (() => {
                        const environment = document.querySelector('[data-ndb-header-fact="environment"]');
                        const peak = document.querySelector('[data-ndb-header-fact="memory"]');
                        const duration = document.querySelector('[data-ndb-header-fact="duration"]');
                        const queries = document.querySelector('[data-ndb-header-fact="queries"]');

                        return environment.tagName === 'DIV'
                            && peak.tagName === 'DIV'
                            && environment.closest('button') === null
                            && peak.closest('button') === null
                            && duration.tagName === 'BUTTON'
                            && queries.tagName === 'BUTTON';
                    })()
                    JS)
                ->assertScript(<<<'JS'
                    (() => {
                        const center = (element) => {
                            const bounds = element.getBoundingClientRect();

                            return bounds.top + bounds.height / 2;
                        };
                        const inspector = document.querySelector('[data-ndb-inspector="queries"]');
                        const favorite = inspector.querySelector('[data-ndb-toggle-favorite]');
                        const count = inspector.querySelector('.ndb-inspector-count');
                        const theme = document.querySelector('[data-ndb-inspector-action="theme"]');
                        const themeIcon = theme.querySelector('span:not([style*="display: none"]) svg');

                        return Math.abs(center(favorite) - center(favorite.querySelector('svg'))) <= 0.5
                            && Math.abs(center(favorite) - center(count)) <= 0.5
                            && Math.abs(center(theme) - center(themeIcon)) <= 0.5;
                    })()
                    JS)
                ->assertScript('/^\\d+(?:\\.\\d{2})? (?:B|KB|MB)$/.test(document.querySelector("[data-ndb-header-response-size]").textContent.trim())');
        }

        $page
            ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
            ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]');
    }

    $page->assertNoJavaScriptErrors();
});

it('provides stateful window controls and closes until reload', function () {
    $page = visit('/profiled')
        ->resize(1440, 900)
        ->assertVisible('[data-ndb-window-controls="compact"]')
        ->assertScript(<<<'JS'
            (() => {
                const controls = document.querySelector('[data-ndb-window-controls="compact"]');
                const expand = controls.querySelector('[data-ndb-window-action="expand"]');
                const shrink = controls.querySelector('[data-ndb-window-action="shrink"]');
                const close = controls.querySelector('[data-ndb-window-action="close"]');
                const utility = document.querySelector('[data-ndb-toolbar-utility-actions]');
                const separator = document.querySelector('[data-ndb-toolbar-actions] [data-ndb-window-controls-separator]');

                return expand.disabled === false
                    && shrink.disabled === true
                    && close.disabled === false
                    && Number.parseFloat(getComputedStyle(shrink).opacity) < Number.parseFloat(getComputedStyle(expand).opacity)
                    && utility.getAttribute('aria-label') === 'Tools'
                    && controls.getAttribute('aria-label') === 'Window controls'
                    && separator === null
                    && Number.parseFloat(getComputedStyle(utility.parentElement).columnGap) > 0;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                window.ndbWindowControlColor = getComputedStyle(
                    document.querySelector('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]'),
                ).color;

                return true;
            })()
            JS)
        ->hover('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertScript(<<<'JS'
            (() => {
                const control = document.querySelector('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');
                const style = getComputedStyle(control);

                return style.backgroundColor === 'rgba(0, 0, 0, 0)'
                    && style.color !== window.ndbWindowControlColor;
            })()
            JS)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertVisible('[data-ndb-window-controls="expanded"]')
        ->assertScript('document.querySelector(\'[data-ndb-window-controls="expanded"] [data-ndb-window-action="expand"]\').disabled === true')
        ->assertScript('document.querySelector(\'[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]\').disabled === false')
        ->assertScript('document.querySelector(\'[data-ndb-window-controls="expanded"] [data-ndb-window-action="close"]\').disabled === false')
        ->assertScript(<<<'JS'
            (() => {
                const controls = document.querySelector('[data-ndb-window-controls="expanded"]');
                const expand = controls.querySelector('[data-ndb-window-action="expand"]');
                const shrink = controls.querySelector('[data-ndb-window-action="shrink"]');

                return Number.parseFloat(getComputedStyle(expand).opacity)
                    < Number.parseFloat(getComputedStyle(shrink).opacity);
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const controls = document.querySelector('[data-ndb-window-controls="expanded"]');
                const utility = document.querySelector('[data-ndb-inspector-utility-actions]');
                const separator = document.querySelector('[data-ndb-inspector-actions] [data-ndb-window-controls-separator]');
                const utilityBox = utility.getBoundingClientRect();
                const controlsBox = controls.getBoundingClientRect();

                return utility.getAttribute('aria-label') === 'Tools'
                    && controls.getAttribute('aria-label') === 'Window controls'
                    && utilityBox.right < controlsBox.left
                    && separator === null
                    && Number.parseFloat(getComputedStyle(utility.parentElement).columnGap) > 0;
            })()
            JS)
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="close"]')
        ->assertScript(<<<'JS'
            (() => {
                window.dispatchEvent(new KeyboardEvent('keydown', {
                    key: 'P',
                    metaKey: true,
                    shiftKey: true,
                }));

                return getComputedStyle(document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]')).display === 'none'
                    && getComputedStyle(document.querySelector('[role="dialog"][aria-label="Request inspector"]')).display === 'none'
                    && getComputedStyle(document.querySelector('[role="dialog"][aria-label="Command palette"]')).display === 'none'
                    && document.querySelector('[data-testid="host-page"]').inert === false
                    && document.body.style.overflow === '';
            })()
            JS)
        ->refresh()
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="close"]')
        ->assertScript('getComputedStyle(document.querySelector(\'[role="toolbar"][aria-label="Debug toolbar"]\')).display === "none"')
        ->refresh()
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->assertNoJavaScriptErrors();
});

it('uses one metric color and balanced glass toolbar spacing', function () {
    visit('/profiled')
        ->assertScript(<<<'JS'
            getComputedStyle(document.getElementById('newdebugbar')).fontFamily.includes('Outfit Variable')
            JS)
        ->assertScript(<<<'JS'
            getComputedStyle(document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]')).borderRadius
            JS, '18px')
        ->assertScript(<<<'JS'
            getComputedStyle(document.querySelector('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')).borderRadius
            JS, '8px')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const filter = getComputedStyle(toolbar).backdropFilter;

                return filter.includes('brightness(1.1)') && filter.includes('saturate(1.25)');
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const close = document.querySelector('[data-ndb-window-controls="compact"] [data-ndb-window-action="close"]');
                const toolbarBox = toolbar.getBoundingClientRect();
                const closeBox = close.getBoundingClientRect();
                const right = toolbarBox.right - closeBox.right;
                const top = closeBox.top - toolbarBox.top;
                const bottom = toolbarBox.bottom - closeBox.bottom;

                return Math.abs(right - top) <= 2
                    && Math.abs(top - bottom) <= 1;
            })()
            JS)
        ->assertScript('document.querySelectorAll(\'[role="toolbar"] > span\').length', 0)
        ->assertScript(<<<'JS'
            (() => {
                const metricColors = ['duration', 'memory', 'queries'].map((name) =>
                    getComputedStyle(document.querySelector(`[data-ndb-toolbar="${name}"] svg`)).color
                );
                const utilityColor = getComputedStyle(document.querySelector('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"] svg')).color;

                return new Set(metricColors).size === 1 && metricColors[0] !== utilityColor;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('uses a darker compact surface without exaggerated backdrop color', function () {
    visit('/profiled')
        ->click('[data-ndb-toolbar="palette"]')
        ->type('[data-ndb-palette-search]', 'dark theme')
        ->keys('[data-ndb-palette-search]', 'Enter')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const style = getComputedStyle(toolbar);
                const alpha = Number(style.backgroundColor.match(/[\d.]+(?=\))$/)?.[0] ?? 1);

                return alpha >= 0.9
                    && style.backdropFilter.includes('brightness(0.75)')
                    && style.backdropFilter.includes('saturate(1)');
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

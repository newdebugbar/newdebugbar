<?php

use NewDebugBar\Support\DurationFormatter;
use NewDebugBar\Tests\Support\DebugBarBrowser;

it('navigates and saves section ordering inside either mobile menu', function (string $theme, int $width, int $height) {
    $page = visit('/profiled')->resize($width, $height);
    $preferences = json_encode([
        'theme' => $theme,
        'favorites' => ['queries', 'request'],
        'sectionOrder' => ['models', 'logs'],
    ], JSON_THROW_ON_ERROR);
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({$preferences}))");
    $page->refresh()
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->assertScript(<<<'JS'
            (() => {
                const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="actions"]');
                const items = menu.querySelector('[data-ndb-mobile-toolbar-popover-items]');
                const box = menu.getBoundingClientRect();
                const controls = [...menu.querySelectorAll('[data-ndb-mobile-toolbar-action]')];
                return controls.map((control) => control.dataset.ndbMobileToolbarAction).join(',') === 'palette,inspector,dismiss,theme'
                    && box.top >= 0 && box.bottom <= window.innerHeight
                    && box.left >= 0 && box.right <= window.innerWidth
                    && items.scrollHeight >= items.clientHeight
                    && items.scrollWidth <= items.clientWidth;
            })()
            JS);

    DebugBarBrowser::dragSection($page, 'request', 'queries');

    DebugBarBrowser::assertFavoriteOrder($page, 'request,queries');

    DebugBarBrowser::dragSection($page, 'logs', 'models');

    $page->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-ndb-section][data-ndb-favorite="false"]')]
                .filter((row) => row.getClientRects().length > 0)
                .slice(0, 2).map((row) => row.dataset.ndbSection).join(',')
            JS, 'logs,models')
        ->click('[data-ndb-select-section="logs"]');

    DebugBarBrowser::assertSectionSelected($page, 'logs');

    $page->assertScript('document.activeElement === document.querySelector("[data-ndb-section-heading]")')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->assertScript(<<<'JS'
            (() => {
                const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="header-actions"]');
                const bounds = menu.getBoundingClientRect();
                const dialog = menu.closest('[role="dialog"]').getBoundingClientRect();
                return bounds.bottom <= dialog.bottom && bounds.top >= dialog.top
                    && bounds.left >= 0 && bounds.right <= window.innerWidth;
            })()
            JS)
        ->click('[data-ndb-toggle-favorite="models"]');

    DebugBarBrowser::assertFavoriteOrder($page, 'request,queries,models');

    DebugBarBrowser::dragSection($page, 'models', 'queries');

    DebugBarBrowser::assertFavoriteOrder($page, 'request,models,queries');

    $page->keys('[data-ndb-select-section="models"]', 'Escape')
        ->assertScript('document.activeElement.dataset.ndbHeaderMobileTrigger === "actions"')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="shrink"]')
        ->assertScript('document.activeElement.dataset.ndbMobileToolbarTrigger === "actions"')
        ->refresh()
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]');

    DebugBarBrowser::assertFavoriteOrder($page, 'request,models,queries');

    $page->click('[data-ndb-select-section="queries"]')
        ->resize(1440, 900)
        ->assertVisible('#newdebugbar-section-navigation');

    DebugBarBrowser::assertFavoriteOrder($page, 'request,models,queries');

    $page->assertScript(<<<'JS'
        [...document.querySelectorAll('[data-ndb-section][data-ndb-favorite="false"]')]
            .filter((row) => row.getClientRects().length > 0)[0].dataset.ndbSection
        JS, 'logs')
        ->assertNoJavaScriptErrors();
})->with([
    'short light phone' => ['light', 320, 568],
    'dark phone' => ['dark', 390, 844],
]);

it('keeps mobile metric values readable across duration formats', function (int $width) {
    $page = visit('/profiled')
        ->resize($width, 844)
        ->assertVisible('[data-ndb-mobile-request-metrics="toolbar"]');

    foreach (['toolbar', 'header'] as $scope) {
        if ($scope === 'header') {
            $page->click('[data-ndb-mobile-toolbar-metric-scope="toolbar"][data-ndb-mobile-toolbar-metric="duration"]')
                ->assertVisible('[data-ndb-mobile-request-metrics="header"]');
        }

        foreach ([0, 0.0005, 0.5, 27.63, 99.99, 999.99, 1_453.51, 10_000] as $duration) {
            $label = DurationFormatter::format($duration);
            $summary = json_encode(['duration_label' => $label, 'query_count' => 999, 'peak_memory_mb' => 128.25], JSON_THROW_ON_ERROR);

            $page->script("Object.assign(Alpine.\$data(document.getElementById('newdebugbar')).summary, {$summary})");

            $page->assertScript("document.querySelector('[data-ndb-mobile-request-metrics=\"{$scope}\"] [data-ndb-mobile-toolbar-summary=\"duration\"]').textContent", $label);

            $page->assertScript(<<<JS
                (() => {
                    const metrics = document.querySelector('[data-ndb-mobile-request-metrics="{$scope}"]');
                    return Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-summary]'))
                        .filter((value) => value.clientWidth === 0 || value.scrollWidth > value.clientWidth)
                        .map((value) => ({ metric: value.dataset.ndbMobileToolbarSummary, text: value.textContent, available: value.clientWidth, required: value.scrollWidth }));
                })()
                JS, []);
        }
    }
})->with([320, 390]);

it('makes mobile metrics direct actions and preserves drag pinning', function () {
    $page = visit('/profiled')
        ->resize(390, 844)
        ->assertVisible('[data-ndb-mobile-request-metrics="toolbar"]')
        ->assertCount('[data-ndb-mobile-toolbar-metric-scope="toolbar"]', 3)
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const metrics = document.querySelector('[data-ndb-mobile-request-metrics="toolbar"]');
                const metricItems = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric]'));
                const buttons = Array.from(metrics.querySelectorAll('button[data-ndb-mobile-toolbar-metric]'));
                const peak = metrics.querySelector('[data-ndb-mobile-toolbar-metric="memory"]');
                const values = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-summary]'));
                const labels = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric-label]'));
                const request = document.querySelector('[data-ndb-toolbar="request"]');
                const actions = document.querySelector('[data-ndb-mobile-toolbar-trigger="actions"]');
                const widths = metricItems.map((item) => Math.round(item.getBoundingClientRect().width));

                return toolbar.scrollWidth <= toolbar.clientWidth
                    && metrics.getAttribute('role') === 'group'
                    && metrics.getAttribute('aria-label') === 'Request metrics'
                    && getComputedStyle(metrics).gridTemplateColumns.split(' ').length === 3
                    && widths[1] > widths[0] && widths[0] === widths[2]
                    && request.getBoundingClientRect().width > actions.getBoundingClientRect().width
                    && metricItems.length === 3
                    && metricItems.every((item) => item.getBoundingClientRect().height >= 44)
                    && metricItems.every((item) => item.querySelector('svg') === null)
                    && metricItems.every((item) => item.querySelector('[aria-hidden="true"]') === null)
                    && buttons.length === 2
                    && buttons.every((button) => button.getAttribute('aria-label')?.startsWith('Open '))
                    && peak.tagName === 'DIV'
                    && peak.getAttribute('aria-label') === null
                    && values.every((value) => value.getBoundingClientRect().width > 0 && value.scrollWidth <= value.clientWidth)
                    && values[0].textContent.trim() !== ''
                    && labels[0].textContent.includes('QRY')
                    && labels[1].textContent.includes('Time')
                    && labels[2].textContent.includes('MB');
            })()
            JS)
        ->click('[data-ndb-mobile-toolbar-metric-scope="toolbar"][data-ndb-mobile-toolbar-metric="queries"]')
        ->assertVisible('[role="dialog"][aria-label="Request inspector"]')
        ->assertVisible('[data-ndb-section-panel="queries"]')
        ->assertScript('document.querySelector("[data-ndb-section-heading]").textContent.trim() === "Queries"')
        ->click('[data-ndb-mobile-toolbar-metric-scope="header"][data-ndb-mobile-toolbar-metric="duration"]')
        ->assertVisible('[data-ndb-section-panel="request"]')
        ->assertScript('document.querySelector("[data-ndb-section-heading]").textContent.trim() === "Requests"')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="shrink"]')
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->assertVisible('[data-ndb-mobile-toolbar-menu="actions"]')
        ->assertVisible('[data-ndb-mobile-toolbar-action="theme"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->click('[data-ndb-mobile-toolbar-action="theme"] [data-ndb-mobile-theme-option="dark"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-mobile-toolbar-menu=\"actions\"]")).display === "none"')
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->assertVisible('[data-ndb-mobile-toolbar-popover-arrow="actions"]')
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ndb-mobile-toolbar-trigger="actions"]');
                const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="actions"]');
                const surface = menu.querySelector('[data-ndb-mobile-toolbar-popover-surface]');
                const items = menu.querySelector('[data-ndb-mobile-toolbar-popover-items]');
                const arrow = menu.querySelector('[data-ndb-mobile-toolbar-popover-arrow="actions"]');
                const triggerBox = trigger.getBoundingClientRect();
                const surfaceBox = surface.getBoundingClientRect();
                const arrowBox = arrow.getBoundingClientRect();
                const paths = arrow.querySelectorAll('path');

                return arrowBox.top < surfaceBox.bottom
                    && arrowBox.bottom > surfaceBox.bottom
                    && Math.abs(arrowBox.width - 16) <= 0.5
                    && Math.abs(arrowBox.height - 8) <= 0.5
                    && Math.abs((arrowBox.left + arrowBox.width / 2) - (triggerBox.left + triggerBox.width / 2)) <= 1
                    && paths.length === 2
                    && getComputedStyle(paths[0]).fill !== 'none'
                    && getComputedStyle(paths[1]).stroke !== 'none'
                    && parseFloat(getComputedStyle(items).rowGap) > 0
                    && Array.from(items.children).every((item) => parseFloat(getComputedStyle(item).borderTopWidth) === 0);
            })()
            JS)
        ->keys('[data-ndb-mobile-toolbar-action="palette"]', 'Escape')
        ->assertScript(<<<'JS'
            (() => {
                const target = document.createElement('div');
                target.dataset.testid = 'mobile-toolbar-top-drop-target';
                Object.assign(target.style, {
                    position: 'fixed',
                    top: '0',
                    left: '50%',
                    width: '48px',
                    height: '48px',
                    zIndex: '1',
                });
                document.body.append(target);

                return true;
            })()
            JS)
        ->drag('[data-ndb-toolbar-shell]', '[data-testid="mobile-toolbar-top-drop-target"]')
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'top')
        ->assertScript('document.querySelector("[data-ndb-toolbar-shell]").getBoundingClientRect().top <= 13')
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ndb-mobile-toolbar-trigger="actions"]');
                const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="actions"]');
                const surface = menu.querySelector('[data-ndb-mobile-toolbar-popover-surface]');
                const arrow = menu.querySelector('[data-ndb-mobile-toolbar-popover-arrow="actions"]');
                const triggerBox = trigger.getBoundingClientRect();
                const surfaceBox = surface.getBoundingClientRect();
                const arrowBox = arrow.getBoundingClientRect();

                return surfaceBox.top > triggerBox.bottom
                    && arrowBox.top < surfaceBox.top
                    && arrowBox.bottom > surfaceBox.top;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('stays compact below sm and returns to the full toolbar at sm', function () {
    $page = visit('/profiled-reported-exception');

    foreach ([320, 360, 430, 639] as $width) {
        $page
            ->resize($width, 844)
            ->assertVisible('[data-ndb-mobile-request-metrics="toolbar"]')
            ->assertScript(<<<'JS'
                (() => {
                    const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                    const request = document.querySelector('[data-ndb-toolbar="request"]');
                    const metrics = document.querySelector('[data-ndb-mobile-request-metrics="toolbar"]');
                    const buttons = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric]'));
                    const values = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-summary]'));
                    const labels = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric-label]'));
                    const actions = document.querySelector('[data-ndb-mobile-toolbar-trigger="actions"]');
                    const widths = buttons.map((button) => Math.round(button.getBoundingClientRect().width));

                    return toolbar.scrollWidth <= toolbar.clientWidth + 1
                        && request.scrollWidth <= request.clientWidth + 1
                        && values.every((value) => value.scrollWidth <= value.clientWidth + 1)
                        && labels.every((label) => label.scrollWidth <= label.clientWidth + 1)
                        && metrics.getBoundingClientRect().width <= 384
                        && widths[1] > widths[0] && widths[0] === widths[2]
                        && buttons.length === 3
                        && buttons.every((button) => button.getBoundingClientRect().height >= 44)
                        && buttons.every((button) => button.querySelector('svg') === null)
                        && buttons.every((button) => button.querySelector('[aria-hidden="true"]') === null)
                        && actions.getBoundingClientRect().width >= 44
                        && actions.getBoundingClientRect().height >= 44;
                })()
                JS);
    }

    $page
        ->resize(320, 844)
        ->assertScript(<<<'JS'
            (() => {
                const path = document.querySelector('[data-ndb-toolbar-request-path]');
                const metrics = document.querySelector('[data-ndb-mobile-request-metrics="toolbar"]');

                return path.scrollWidth > path.clientWidth
                    && path.title === path.textContent.trim()
                    && getComputedStyle(metrics).backgroundColor === 'rgba(0, 0, 0, 0)';
            })()
            JS)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->assertVisible('[data-ndb-mobile-toolbar-menu="actions"]')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="actions"]');
                const box = menu.getBoundingClientRect();

                return toolbar.scrollWidth <= toolbar.clientWidth + 1
                    && box.left >= 12
                    && box.right <= window.innerWidth - 12;
            })()
            JS)
        ->keys('[data-ndb-mobile-toolbar-action="palette"]', 'Escape');

    foreach ([640, 768, 1023, 1024] as $width) {
        $page
            ->resize($width, 844)
            ->assertScript(<<<'JS'
                (() => {
                    const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                    const mobileMetrics = document.querySelector('[data-ndb-mobile-request-metrics="toolbar"]');
                    const mobileActions = document.querySelector('[data-ndb-mobile-toolbar-control="actions"]');
                    const desktopFacts = document.querySelector('[data-ndb-toolbar-facts]');
                    const desktopActions = document.querySelector('[data-ndb-toolbar-actions]');

                    return toolbar.scrollWidth <= toolbar.clientWidth + 1
                        && getComputedStyle(mobileMetrics).display === 'none'
                        && getComputedStyle(mobileActions).display === 'none'
                        && getComputedStyle(desktopFacts).display !== 'none'
                        && getComputedStyle(desktopActions).display !== 'none';
                })()
                JS)
            ->assertNoJavaScriptErrors();
    }
});

it('uses the compact inspector header only below sm', function () {
    $page = visit('/profiled-reported-exception')
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]');

    foreach ([320, 360, 430, 639] as $width) {
        $page
            ->resize($width, 844)
            ->assertVisible('[data-ndb-header-mobile-toolbar]')
            ->assertScript(<<<'JS'
                (() => {
                    const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                    const toolbar = document.querySelector('[data-ndb-header-mobile-toolbar]');
                    const request = document.querySelector('[data-ndb-header-mobile-request]');
                    const metrics = document.querySelector('[data-ndb-mobile-request-metrics="header"]');
                    const buttons = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric]'));
                    const actions = document.querySelector('[data-ndb-header-mobile-trigger="actions"]');
                    const actionStyles = getComputedStyle(actions);

                    return dialog.scrollWidth <= dialog.clientWidth + 1
                        && toolbar.scrollWidth <= toolbar.clientWidth + 1
                        && request.scrollWidth <= request.clientWidth + 1
                        && buttons.length === 3
                        && buttons.every((button) => button.getBoundingClientRect().height >= 44)
                        && buttons.every((button) => button.querySelector('svg') === null)
                        && buttons.every((button) => button.querySelector('[aria-hidden="true"]') === null)
                        && actions.getBoundingClientRect().width >= 44
                        && actions.getBoundingClientRect().height >= 44
                        && actions.querySelectorAll('svg').length === 1
                        && Number.parseFloat(actionStyles.borderTopWidth) === 0
                        && actionStyles.boxShadow === 'none'
                        && actionStyles.backgroundColor === 'rgba(0, 0, 0, 0)';
                })()
                JS);
    }

    $page
        ->resize(320, 844)
        ->assertScript(<<<'JS'
            (() => {
                const path = document.querySelector('[data-ndb-header-mobile-request-path]');

                return path.scrollWidth > path.clientWidth
                    && path.title === path.textContent.trim();
            })()
            JS)
        ->click('[data-ndb-mobile-toolbar-metric-scope="header"][data-ndb-mobile-toolbar-metric="queries"]')
        ->assertVisible('[data-ndb-section-panel="queries"]')
        ->assertScript('document.querySelector("[data-ndb-section-heading]").textContent.trim() === "Queries"')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->assertVisible('[data-ndb-mobile-toolbar-menu="header-actions"]')
        ->assertVisible('[data-ndb-mobile-toolbar-popover-arrow="header-actions"]')
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ndb-header-mobile-trigger="actions"]');
                const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="header-actions"]');
                const surface = menu.querySelector('[data-ndb-mobile-toolbar-popover-surface]');
                const items = menu.querySelector('[data-ndb-mobile-toolbar-popover-items]');
                const arrow = menu.querySelector('[data-ndb-mobile-toolbar-popover-arrow="header-actions"]');
                    const visibleItems = Array.from(menu.querySelectorAll('[role="menuitem"], [role="menuitemradio"]'))
                    .filter((item) => item.getClientRects().length > 0);
                const triggerBox = trigger.getBoundingClientRect();
                const surfaceBox = surface.getBoundingClientRect();
                const arrowBox = arrow.getBoundingClientRect();

                return menu.querySelector('h1, h2, h3, [role="heading"]') === null
                    && !menu.textContent.includes('Inspector actions')
                    && surfaceBox.left >= 6
                    && surfaceBox.right <= window.innerWidth - 6
                    && surfaceBox.top > triggerBox.bottom
                    && arrowBox.top < surfaceBox.top
                    && arrowBox.bottom > surfaceBox.top
                    && Math.abs(arrowBox.width - 16) <= 0.5
                    && Math.abs(arrowBox.height - 8) <= 0.5
                    && Math.abs((arrowBox.left + arrowBox.width / 2) - (triggerBox.left + triggerBox.width / 2)) <= 1
                    && arrow.querySelectorAll('path').length === 2
                    && parseFloat(getComputedStyle(items).rowGap) > 0
                    && visibleItems.every((item) => parseFloat(getComputedStyle(item).borderTopWidth) === 0)
                    && visibleItems.length > 6
                    && visibleItems.every((item) => item.getBoundingClientRect().height >= 44)
                    && document.activeElement === visibleItems[0];
            })()
            JS)
        ->assertVisible('[data-ndb-mobile-toolbar-menu="header-actions"] [data-ndb-select-section="queries"]')
        ->keys('[data-ndb-mobile-toolbar-menu="header-actions"] [data-ndb-select-section="queries"]', 'Escape')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-header-mobile-trigger=\\"actions\\"]")')
        ->resize(640, 844)
        ->assertScript(<<<'JS'
            getComputedStyle(document.querySelector('[data-ndb-header-mobile-toolbar]')).display === 'none'
                && getComputedStyle(document.querySelector('[data-ndb-header-toolbar]')).display !== 'none'
            JS)
        ->assertNoJavaScriptErrors();
});

it('keeps the main interactions usable on a phone viewport', function () {
    $page = visit('/profiled')
        ->on()->iPhone14Pro()
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const box = toolbar.getBoundingClientRect();

                return Math.abs(box.width - (window.innerWidth - 24)) <= 1
                    && Math.abs(box.left - 12) <= 1
                    && Math.abs(window.innerWidth - box.right - 12) <= 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[role="toolbar"][aria-label="Debug toolbar"]');
                const request = document.querySelector('[data-ndb-toolbar="request"]');
                const metrics = document.querySelector('[data-ndb-mobile-request-metrics="toolbar"]');
                const actions = document.querySelector('[data-ndb-mobile-toolbar-trigger="actions"]');
                const toolbarBox = toolbar.getBoundingClientRect();
                const requestBox = request.getBoundingClientRect();
                const metricsBox = metrics.getBoundingClientRect();
                const actionsBox = actions.getBoundingClientRect();
                const actionStyles = getComputedStyle(actions);
                const metricButtons = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric]'));

                return requestBox.width >= 100
                    && requestBox.width < toolbarBox.width * 0.42
                    && metricsBox.width < toolbarBox.width / 2
                    && metricButtons.length === 3
                    && metricButtons.every((button) => button.getBoundingClientRect().height >= 44)
                    && metrics.querySelectorAll('svg').length === 0
                    && metrics.querySelectorAll('[data-ndb-mobile-toolbar-summary]').length === 3
                    && metrics.textContent.includes('QRY')
                    && metrics.textContent.includes('Time')
                    && metrics.textContent.includes('MB')
                    && /(?:<1|\d+(?:\.\d+)?) (?:µs|ms|s)/.test(metrics.textContent)
                    && actionsBox.width >= 44
                    && actionsBox.height >= 44
                    && actions.querySelectorAll('svg').length === 1
                    && Number.parseFloat(actionStyles.borderTopWidth) === 0
                    && actionStyles.boxShadow === 'none'
                    && actionStyles.backgroundColor === 'rgba(0, 0, 0, 0)'
                    && actionsBox.left >= metricsBox.right;
            })()
            JS)
        ->assertScript(<<<'JS'
            getComputedStyle(document.querySelector('[data-ndb-toolbar-facts]')).display === 'none'
                && getComputedStyle(document.querySelector('[data-ndb-toolbar-actions]')).display === 'none'
            JS)
        ->assertMissing('[data-ndb-toolbar-status-meaning]')
        ->assertScript("getComputedStyle(document.querySelector('[data-ndb-toolbar-response-size]')).display === 'none'")
        ->assertCount('[data-ndb-mobile-toolbar-metric-scope="toolbar"]', 3)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->assertAttribute('[data-ndb-mobile-toolbar-trigger="actions"]', 'aria-expanded', 'true')
        ->assertVisible('[data-ndb-mobile-toolbar-menu="actions"]')
        ->assertScript(<<<'JS'
                (() => {
                    const menu = document.querySelector('[data-ndb-mobile-toolbar-menu="actions"]');
                    const items = Array.from(menu.querySelectorAll('[role="menuitem"], [role="menuitemradio"]')).filter((item) => item.getClientRects().length > 0);

                    return menu.querySelector('h1, h2, h3, [role="heading"]') === null
                        && !menu.textContent.includes('Debug bar')
                        && items.length > 6
                        && menu.querySelector('[data-ndb-mobile-toolbar-action="placement"]') === null
                        && menu.querySelector('[data-ndb-mobile-toolbar-action="inspector"]').textContent.trim() === 'Open'
                        && menu.querySelectorAll('[data-ndb-mobile-theme-option]').length === 3
                        && items.every((item) => item.getBoundingClientRect().height >= 44)
                    && document.activeElement === items[0];
            })()
            JS)
        ->click('[data-ndb-mobile-toolbar-action="palette"]')
        ->waitForText('Go to Requests')
        ->assertVisible('[role="dialog"][aria-label="Command palette"]')
        ->keys('[data-ndb-palette-search]', 'Escape')
        ->assertScript('getComputedStyle(document.querySelector("[role=\"dialog\"][aria-label=\"Command palette\"]")).display === "none"')
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->assertVisible('[data-ndb-header-mobile-toolbar]')
        ->assertVisible('[data-ndb-mobile-request-metrics="header"]')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[data-ndb-header-mobile-toolbar]');
                const metrics = document.querySelector('[data-ndb-mobile-request-metrics="header"]');
                const actions = document.querySelector('[data-ndb-header-mobile-trigger="actions"]');
                const actionStyles = getComputedStyle(actions);
                const metricButtons = Array.from(metrics.querySelectorAll('[data-ndb-mobile-toolbar-metric]'));

                return toolbar.scrollWidth <= toolbar.clientWidth + 1
                    && metrics.querySelector('svg') === null
                    && metrics.querySelectorAll('[data-ndb-mobile-toolbar-summary]').length === 3
                    && metricButtons.length === 3
                    && metricButtons.every((button) => button.getBoundingClientRect().height >= 44)
                    && actions.getBoundingClientRect().width >= 44
                    && actions.getBoundingClientRect().height >= 44
                    && actions.querySelectorAll('svg').length === 1
                    && Number.parseFloat(actionStyles.borderTopWidth) === 0
                    && actionStyles.boxShadow === 'none'
                    && actionStyles.backgroundColor === 'rgba(0, 0, 0, 0)';
            })()
            JS)
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->assertAttribute('[data-ndb-header-mobile-trigger="actions"]', 'aria-expanded', 'true')
        ->click('[data-ndb-select-section="queries"]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-section-heading]")');

    DebugBarBrowser::assertSectionSelected($page, 'queries');

    $page
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-toggle-favorite="queries"]')
        ->assertAttribute('[data-ndb-toggle-favorite="queries"]', 'aria-checked', 'true')
        ->keys('[data-ndb-toggle-favorite="queries"]', 'Escape')
        ->assertVisible('[role="dialog"][aria-label="Request inspector"]')
        ->assertScript('document.activeElement.dataset.ndbHeaderMobileTrigger === "actions"')
        ->assertAttribute('[data-ndb-header-mobile-trigger="actions"]', 'aria-expanded', 'false')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-metric-scope="header"][data-ndb-mobile-toolbar-metric="memory"]')
        ->assertAttribute('[data-ndb-header-mobile-trigger="actions"]', 'aria-expanded', 'false')
        ->resize(1440, 900)
        ->assertVisible('#newdebugbar-section-navigation')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-header-mobile-toolbar]")).display === "none"')
        ->assertNoJavaScriptErrors();
});

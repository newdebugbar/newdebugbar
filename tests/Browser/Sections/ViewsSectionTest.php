<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps application views primary and lazily inspects one desktop render', function () {
    $page = visit('/profiled-views');
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: 'dark', favorites: []}))");

    $page
        ->refresh()
        ->resize(1280, 720)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="views"]');

    DebugBarBrowser::assertSectionSelected($page, 'views');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertPresent('[data-ndb-view-workspace]')
        ->assertValue('[data-ndb-view-filter]', 'application')
        ->assertMissing('[data-ndb-view-data]')
        ->assertDontSee('view-data-value')
        ->assertMissing('[data-ndb-view-sort]')
        ->assertMissing('[data-ndb-view-data-popover]')
        ->assertScript(<<<'JS'
            (() => {
                const controls = document.querySelector('[data-ndb-view-list-panel] [data-ndb-inspector-list-controls]');
                const search = document.querySelector('[data-ndb-view-search]').getBoundingClientRect();
                const filter = document.querySelector('[data-ndb-view-filter]').getBoundingClientRect();
                const workspace = document.querySelector('[data-ndb-view-workspace]');
                const visible = [...document.querySelectorAll('[data-ndb-view-group]:not([hidden])')];

                return search.left < filter.left
                    && search.right <= filter.left
                    && controls.scrollWidth <= controls.clientWidth
                    && workspace.getBoundingClientRect().height > 400
                    && workspace.scrollWidth <= workspace.clientWidth
                    && visible.length === 2
                    && visible.every((view) => view.dataset.ndbViewOrigin === 'application')
                    && visible.every((view) => ! view.textContent.includes('tests/Fixtures/views'));
            })()
            JS)
        ->click('[data-ndb-view-group="view-2"]')
        ->assertVisible('[data-ndb-view-detail]')
        ->assertSee('original-response')
        ->assertVisible('[data-ndb-view-render-select]')
        ->assertVisible('[data-ndb-view-detail-content]')
        ->assertVisible('[data-ndb-view-data-panel]')
        ->assertSee('tests/Fixtures/views/original-response.blade.php')
        ->assertDontSee('tests/Fixtures/views/original-response.blade.php:1')
        ->assertVisible('[data-ndb-view-detail-content] [data-ndb-inspector-source-link]')
        ->assertMissing('[data-ndb-view-detail-tab]')
        ->assertMissing('[data-ndb-view-composers]')
        ->assertMissing('[data-ndb-view-composer-count]')
        ->assertScript('document.querySelectorAll("[data-ndb-view-detail]").length === 1')
        ->assertScript(<<<'JS'
            (() => {
                const header = document.querySelector('[data-ndb-view-detail-header]');
                const primary = header.querySelector('[data-ndb-inspector-detail-header-primary]');
                const metadata = header.querySelector('[data-ndb-view-detail-metadata]');
                const select = header.querySelector('[data-ndb-view-render-select]');

                return getComputedStyle(primary).display === 'flex'
                    && getComputedStyle(metadata).display === 'flex'
                    && metadata.querySelectorAll(':scope > div').length === 2
                    && select !== null
                    && getComputedStyle(
                        document.querySelector('[data-ndb-view-detail-content] [data-ndb-inspector-source-link]'),
                    ).fontFamily === getComputedStyle(header).fontFamily
                    && document.querySelector('[data-ndb-view-detail-tab]') === null
                    && header.scrollWidth <= header.clientWidth;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                window.newdebugbarViewClipboard = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarViewClipboard.push(value),
                    },
                });

                return true;
            })()
            JS)
        ->click('[data-ndb-view-detail-content] [data-ndb-inspector-source-link]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            JSON.stringify(window.newdebugbarViewClipboard)
                === JSON.stringify(['tests/Fixtures/views/original-response.blade.php'])
            JS)
        ->waitForText('First response')
        ->assertVisible('[data-ndb-view-data]')
        ->select('[data-ndb-view-render-select]', '3')
        ->waitForText('Second response')
        ->assertScript(<<<'JS'
            (() => {
                const code = document.querySelector('[data-ndb-view-data] code[data-ndb-language="json"][data-highlighted]');

                return code !== null
                    && code.textContent.includes('"label": "Second response"')
                    && code.querySelector('.hljs-attr') !== null
                    && code.querySelector('.hljs-string') !== null;
            })()
            JS)
        ->assertVisible('[data-ndb-view-detail-content]')
        ->assertSee('tests/Fixtures/views/original-response.blade.php')
        ->select('[data-ndb-view-filter]', 'framework')
        ->assertScript('document.querySelectorAll("[data-ndb-view-group]:not([hidden])").length', 0)
        ->assertSee('No views match this origin and search.')
        ->assertVisible('[data-ndb-view-detail-empty]')
        ->select('[data-ndb-view-filter]', 'all')
        ->type('[data-ndb-view-search]', 'context')
        ->assertScript('document.querySelectorAll("[data-ndb-view-group]:not([hidden])").length', 1)
        ->assertNoJavaScriptErrors();
});

it('uses a bounded mobile list drill-in with a working Views back action', function () {
    $page = visit('/profiled-views')->resize(390, 844);
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: 'light', favorites: []}))");

    $page
        ->refresh()
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-select-section="views"]');

    DebugBarBrowser::assertSectionSelected($page, 'views');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->assertValue('[data-ndb-view-filter]', 'application')
        ->assertScript(<<<'JS'
            (() => {
                const stage = document.querySelector('[data-ndb-section-stage]').getBoundingClientRect();
                const workspace = document.querySelector('[data-ndb-view-workspace]');
                const workspaceBox = workspace.getBoundingClientRect();
                const [list, detail] = workspace.children;
                const rows = [...document.querySelectorAll('[data-ndb-view-group]:not([hidden])')];
                const summary = document.querySelector('[data-ndb-view-summary]');
                const primary = summary.querySelector('strong').getBoundingClientRect();
                const secondary = summary.querySelector(':scope > span').getBoundingClientRect();
                const near = (actual, expected) => Math.abs(actual - expected) <= 1;

                return near(workspaceBox.left, stage.left)
                    && near(workspaceBox.right, stage.right)
                    && workspace.scrollWidth <= workspace.clientWidth
                    && getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && rows.every((row) => row.scrollWidth <= row.clientWidth)
                    && secondary.top >= primary.bottom;
            })()
            JS)
        ->click('[data-ndb-view-group="view-2"]')
        ->assertVisible('[data-ndb-view-detail]')
        ->assertVisible('[data-ndb-view-detail-back]')
        ->assertVisible('[data-ndb-view-detail-content]')
        ->assertVisible('[data-ndb-view-data-panel]')
        ->assertMissing('[data-ndb-view-detail-tab]')
        ->assertMissing('[data-ndb-view-composers]')
        ->assertSee('tests/Fixtures/views/original-response.blade.php')
        ->assertDontSee('tests/Fixtures/views/original-response.blade.php:1')
        ->assertScript(<<<'JS'
            (() => {
                const stage = document.querySelector('[data-ndb-section-stage]').getBoundingClientRect();
                const workspace = document.querySelector('[data-ndb-view-workspace]');
                const workspaceBox = workspace.getBoundingClientRect();
                const [list, detail] = workspace.children;
                const content = document.querySelector('[data-ndb-view-detail-content]');
                const near = (actual, expected) => Math.abs(actual - expected) <= 1;

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && detail.getBoundingClientRect().width >= workspace.getBoundingClientRect().width - 2
                    && near(workspaceBox.left, stage.left)
                    && near(workspaceBox.right, stage.right)
                    && workspace.scrollWidth <= workspace.clientWidth
                    && detail.scrollWidth <= detail.clientWidth
                    && getComputedStyle(content).paddingLeft === '12px'
                    && getComputedStyle(content).paddingRight === '12px';
            })()
            JS)
        ->click('[data-ndb-view-detail-back]')
        ->assertVisible('[data-ndb-view-list]');

    DebugBarBrowser::waitForFocus($page, '[data-ndb-view-group][aria-pressed="true"]');

    $page->assertNoJavaScriptErrors();
});

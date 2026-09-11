<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('shows one exception as focused full-width evidence', function () {
    $page = visit('/profiled-reported-exception')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-inspector="exceptions"]');

    DebugBarBrowser::assertInspectorSelected($page, 'exceptions');

    $page
        ->assertAttribute('[data-ndb-exceptions]', 'data-ndb-exception-layout', 'focused')
        ->assertVisible('[data-ndb-exception-workspace]')
        ->assertVisible('[data-ndb-exception-focused-detail]')
        ->assertMissing('[data-ndb-exception-list]')
        ->assertMissing('[data-ndb-exception-item]')
        ->assertMissing('[data-ndb-exception-detail-back]')
        ->assertVisible('[data-ndb-exception-detail="0"]')
        ->assertAttribute('[data-ndb-exception-detail-tab="source"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-exception-detail-panel="source"]')
        ->assertSee('tests/Support/DefinesTestApplication.php')
        ->assertDontSee(base_path().'/tests/Support/DefinesTestApplication.php')
        ->assertPresent('[data-ndb-copy-exception-callsite="0"]')
        ->assertVisible('[data-ndb-exception-context-action]')
        ->assertSee('Open request')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-exception-detail="0"]');
                const tabs = detail?.querySelector('[data-ndb-filter-tabs]');
                const controls = tabs?.parentElement;
                const action = detail?.querySelector('[data-ndb-exception-context-action]');

                if (! controls || ! tabs || ! action) return false;

                const controlsBox = controls.getBoundingClientRect();
                const tabsBox = tabs.getBoundingClientRect();
                const actionBox = action.getBoundingClientRect();

                return Math.abs(
                    (tabsBox.left + (tabsBox.width / 2)) - (controlsBox.left + (controlsBox.width / 2)),
                ) <= 1
                    && actionBox.right <= controlsBox.right + 1
                    && actionBox.height < 40
                    && controls.scrollWidth <= controls.clientWidth + 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-exception-focused-workspace]');
                const body = workspace?.querySelector('[data-ndb-inspector-stream-body]');
                const detail = document.querySelector('[data-ndb-exception-focused-detail]');

                if (!workspace || !body || !detail) return false;

                const workspaceBox = workspace.getBoundingClientRect();
                const bodyBox = body.getBoundingClientRect();
                const detailBox = detail.getBoundingClientRect();

                return getComputedStyle(workspace).display === 'flex'
                    && ['auto', 'scroll'].includes(getComputedStyle(body).overflowY)
                    && Math.abs(bodyBox.width - workspaceBox.width) <= 1
                    && Math.abs(detailBox.width - bodyBox.width) <= 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const primary = document.querySelector('[data-ndb-exception-detail="0"] [data-ndb-inspector-detail-header-primary]');
                const copy = primary?.querySelector('[data-ndb-exception-header-copy]');
                const type = copy?.querySelector('h3');
                const message = copy?.querySelector('p');
                const source = primary?.querySelector('[data-ndb-copy-exception-callsite="0"]');

                if (!primary || !copy || !type || !message || !source) return false;

                const primaryBox = primary.getBoundingClientRect();
                const copyBox = copy.getBoundingClientRect();
                const typeBox = type.getBoundingClientRect();
                const messageBox = message.getBoundingClientRect();
                const sourceBox = source.getBoundingClientRect();

                return primary.children.length === 2
                    && copy.parentElement === primary
                    && source.parentElement === primary
                    && Math.abs(typeBox.left - messageBox.left) <= 1
                    && messageBox.top >= typeBox.bottom
                    && sourceBox.left >= copyBox.right
                    && Math.abs(sourceBox.top - copyBox.top) <= 1
                    && primary.scrollWidth <= primary.clientWidth + 1
                    && copyBox.left >= primaryBox.left
                    && sourceBox.right <= primaryBox.right + 1;
            })()
            JS)
        ->assertScript('document.querySelectorAll("#newdebugbar code[data-ndb-language=php][data-highlighted]").length > 0')
        ->assertScript(<<<'JS'
            (() => {
                const lines = document.querySelector('[data-ndb-exception-detail-panel="source"] code')
                    .textContent.split('\n').filter((line) => line.trim().length > 0);

                return lines.every((line) => !line.startsWith(' '));
            })()
            JS)
        ->click('[data-ndb-exception-detail-tab="causes"]')
        ->assertAttribute('[data-ndb-exception-detail-tab="causes"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-exception-detail-panel="causes"]')
        ->assertVisible('[data-ndb-exception-cause="0"]')
        ->assertSee('Earlier itinerary failure.')
        ->assertSee('LogicException')
        ->assertPresent('[data-ndb-exception-cause="0"] [data-ndb-inspector-source-link]')
        ->click('[data-ndb-exception-detail-tab="stack"]')
        ->assertAttribute('[data-ndb-exception-detail-tab="stack"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-exception-detail-panel="stack"]')
        ->assertSee('Application stack')
        ->assertSee('Vendor stack')
        ->assertNoJavaScriptErrors();

    $page
        ->click('[data-ndb-exception-context-action]')
        ->assertVisible('[data-ndb-inspector-panel="request"]');

    DebugBarBrowser::assertInspectorSelected($page, 'request');
});

it('uses a list-detail workspace for multiple exceptions with mobile drill in', function () {
    $page = visit('/profiled-reported-exceptions')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-inspector="exceptions"]');

    DebugBarBrowser::assertInspectorSelected($page, 'exceptions');

    $page
        ->assertAttribute('[data-ndb-exceptions]', 'data-ndb-exception-layout', 'split')
        ->assertCount('[data-ndb-exception-item]', 2)
        ->assertVisible('[data-ndb-exception-list-panel]')
        ->assertVisible('[data-ndb-exception-split-detail]')
        ->assertVisible('[data-ndb-exception-detail="0"]')
        ->assertMissing('[data-ndb-exception-detail="1"]')
        ->click('[data-ndb-exception-item="1"]')
        ->assertAttribute('[data-ndb-exception-item="1"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-exception-detail="1"]')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-exception-detail="1"] [data-ndb-exception-header-copy] p')
                ?.textContent.trim() === 'Second reported failure.'
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-exception-workspace]');
                const list = document.querySelector('[data-ndb-exception-list-panel]');
                const detail = document.querySelector('[data-ndb-exception-split-detail]');

                return getComputedStyle(workspace).display === 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'flex'
                    && workspace.scrollWidth <= workspace.clientWidth + 1;
            })()
            JS)
        ->assertNoJavaScriptErrors();

    $page = visit('/profiled-reported-exceptions')
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-select-inspector="exceptions"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-exception-list]');

    $page
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-exception-workspace]');
                const list = document.querySelector('[data-ndb-exception-list-panel]');
                const detail = document.querySelector('[data-ndb-exception-split-detail]');

                return getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && workspace.scrollWidth <= workspace.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-exception-item="1"]')
        ->assertVisible('[data-ndb-exception-detail="1"]')
        ->assertVisible('[data-ndb-exception-detail-back]')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const list = document.querySelector('[data-ndb-exception-list-panel]');
                const detail = document.querySelector('[data-ndb-exception-split-detail]');
                const primary = detail.querySelector('[data-ndb-inspector-detail-header-primary]');
                const copy = primary?.querySelector('[data-ndb-exception-header-copy]');
                const type = copy?.querySelector('code');
                const source = primary?.querySelector('[data-ndb-inspector-source-link]');

                if (! primary || ! copy || ! type || ! source) return false;

                const copyBox = copy.getBoundingClientRect();
                const typeBox = type.getBoundingClientRect();
                const sourceBox = source.getBoundingClientRect();

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && getComputedStyle(detail).overflowY === 'visible'
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && ['auto', 'scroll'].includes(getComputedStyle(content).overflowY)
                    && getComputedStyle(primary).gridTemplateColumns.split(' ').length === 2
                    && sourceBox.left >= copyBox.right
                    && Math.abs(sourceBox.top - typeBox.top) <= 2
                    && type.scrollWidth <= type.clientWidth + 1
                    && document.activeElement === detail;
            })()
            JS)
        ->click('[data-ndb-exception-detail-back]')
        ->assertVisible('[data-ndb-exception-list]');

    DebugBarBrowser::waitForFocus($page, '[data-ndb-exception-item="1"]');

    $page
        ->assertAttribute('[data-ndb-exception-item="1"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

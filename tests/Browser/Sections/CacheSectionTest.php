<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('filters selects and inspects rich cache diagnostics', function () {
    $preferences = json_encode(['theme' => 'light', 'favorites' => []], JSON_THROW_ON_ERROR);
    $page = visit('/profiled-cache-rich')->resize(1440, 900);

    $page
        ->assertScript(<<<JS
            (() => {
                localStorage.setItem('newdebugbar.preferences.v1', '{$preferences}');

                return true;
            })()
            JS)
        ->refresh()
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="cache"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-cache-workspace]');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->assertSee('Cache needs attention')
        ->assertSee('17')
        ->assertSee('40.0%')
        ->assertScript('!document.querySelector("[data-ndb-cache-attention]").textContent.includes("miss rate")')
        ->assertValue('[data-ndb-cache-filter]', 'all')
        ->assertAttribute('[data-ndb-cache-detail-tab="overview"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-cache-detail-panel="raw"]')
        ->assertMissing('[data-ndb-cache-detail-panel="source"]')
        ->assertScript('document.querySelector("[data-ndb-cache-detail-panel=overview] dd pre").textContent.trim()', 'stale option')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-item]:not([hidden])").length', 17)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-cache-workspace]');
                const loadedSection = document.querySelector('[data-ndb-loaded-section="cache"]');
                const stage = document.querySelector('[data-ndb-section-stage]');
                const [list, detail] = workspace.children;
                const content = document.querySelector('[data-ndb-inspector-content]');
                const rows = [...document.querySelectorAll('[data-ndb-cache-item]')];
                const selected = document.querySelector('[data-ndb-cache-item][aria-pressed="true"]');
                const detailTabs = [...document.querySelectorAll('[data-ndb-cache-detail-tab]')];
                const detailTabGroup = detailTabs[0].closest('[data-ndb-filter-tabs]');
                const header = document.querySelector('[data-ndb-cache-header]');
                const headerPrimary = header.querySelector('[data-ndb-inspector-detail-header-primary]');
                const headerLine = header.querySelector('h3');
                const headerOperation = header.querySelector('[data-ndb-cache-detail-operation]');
                const headerKey = header.querySelector('[data-ndb-cache-detail-key]');
                const metadata = document.querySelector('[data-ndb-cache-metadata]');
                const metadataFacts = [...metadata.querySelectorAll(':scope > div')].filter(
                    (fact) => fact.getClientRects().length > 0,
                );
                const metadataLabels = metadataFacts.map((fact) => fact.querySelector('dt').textContent.trim());
                const metadataValues = metadataFacts.map((fact) => fact.querySelector('dd').textContent.trim());
                const sourceLink = metadata.querySelector('button');
                const search = document.querySelector('[data-ndb-cache-search]');
                const searchIcon = search.parentElement.querySelector('svg');
                const filter = document.querySelector('[data-ndb-cache-filter]');
                const operationBadges = rows.map((row) => row.querySelector('[data-ndb-cache-operation]'));
                const keys = rows.map((row) => row.querySelector('[data-ndb-cache-key]'));
                const results = rows.map((row) => row.querySelector('[data-ndb-cache-result]'));
                const durations = rows.map((row) => row.querySelector('[data-ndb-cache-list-duration]'));
                const stores = rows.map((row) => row.children[3].textContent.trim());
                const keyOffsets = rows.map((row) => row.querySelector('[data-ndb-cache-key]').getBoundingClientRect().left);
                const rightTrackWidths = rows.map((row) => Number.parseFloat(getComputedStyle(row).gridTemplateColumns.split(' ').at(-1)));
                const resultRightEdges = results.map((result) => Math.round(result.getBoundingClientRect().right));
                const durationRightEdges = durations.map((duration) => Math.round(duration.getBoundingClientRect().right));

                return getComputedStyle(workspace).display === 'grid'
                    && workspace.getBoundingClientRect().height > 320
                    && workspace.getBoundingClientRect().bottom <= content.getBoundingClientRect().bottom + 1
                    && getComputedStyle(loadedSection).paddingLeft === '0px'
                    && getComputedStyle(loadedSection).paddingRight === '0px'
                    && Math.abs(workspace.getBoundingClientRect().left - stage.getBoundingClientRect().left) <= 1
                    && Math.abs(workspace.getBoundingClientRect().right - stage.getBoundingClientRect().right) <= 1
                    && getComputedStyle(workspace).borderTopWidth === '1px'
                    && getComputedStyle(workspace).borderRightWidth === '0px'
                    && getComputedStyle(workspace).borderBottomWidth === '0px'
                    && getComputedStyle(workspace).borderLeftWidth === '0px'
                    && getComputedStyle(workspace).borderRadius === '0px'
                    && Math.abs(list.getBoundingClientRect().right - detail.getBoundingClientRect().left) <= 1
                    && detail.getBoundingClientRect().width > list.getBoundingClientRect().width * 1.6
                    && selected.dataset.ndbCacheItem === '1'
                    && detailTabs.length === 3
                    && detailTabs.every((tab) => tab.matches('[data-ndb-filter-tab]'))
                    && detailTabs.map((tab) => tab.getAttribute('aria-label')).join('|') === 'Overview|Raw|Source'
                    && detailTabs.every((tab) => tab.dataset.ndbFilterTabVariant === 'segmented')
                    && detailTabGroup.dataset.ndbFilterTabsVariant === 'segmented'
                    && Math.abs(
                        detailTabGroup.getBoundingClientRect().left + detailTabGroup.getBoundingClientRect().width / 2
                        - detail.getBoundingClientRect().left - detail.getBoundingClientRect().width / 2
                    ) <= 1
                    && header.children.length === 1
                    && header.children[0] === headerPrimary
                    && headerLine.children.length === 2
                    && headerLine.children[0] === headerOperation
                    && headerLine.children[1] === headerKey
                    && getComputedStyle(headerLine).display === 'flex'
                    && getComputedStyle(headerLine).flexWrap === 'nowrap'
                    && Math.abs(
                        headerOperation.getBoundingClientRect().top + headerOperation.getBoundingClientRect().height / 2
                        - headerKey.getBoundingClientRect().top - headerKey.getBoundingClientRect().height / 2
                    ) <= 1
                    && headerKey.getBoundingClientRect().left > headerOperation.getBoundingClientRect().right
                    && Math.round(headerOperation.getBoundingClientRect().width) === 64
                    && Number.parseFloat(getComputedStyle(headerOperation).paddingLeft) === 8
                    && Number.parseFloat(getComputedStyle(headerOperation).paddingRight) === 8
                    && !getComputedStyle(headerKey).fontFamily.toLowerCase().includes('mono')
                    && header.querySelector('button, dl') === null
                    && document.querySelector('[data-ndb-cache-copy-key]') === null
                    && document.querySelector('[data-ndb-cache-copy-raw]') === null
                    && document.querySelector('[data-ndb-cache-sort]') === null
                    && document.querySelectorAll('[data-ndb-cache] select').length === 1
                    && filter.tagName === 'SELECT'
                    && filter.options[0].value === 'all'
                    && filter.options[0].textContent.trim().startsWith('All (')
                    && Math.abs(filter.getBoundingClientRect().top - search.getBoundingClientRect().top) <= 1
                    && filter.getBoundingClientRect().left > search.getBoundingClientRect().right
                    && searchIcon.getBoundingClientRect().left - search.getBoundingClientRect().left >= 9
                    && searchIcon.getBoundingClientRect().left - search.getBoundingClientRect().left <= 11
                    && Math.round(searchIcon.getBoundingClientRect().width) === 16
                    && getComputedStyle(metadata).display === 'grid'
                    && metadataLabels.join('|') === 'Result|Runtime|Store|Driver|Source'
                    && metadataValues[0] === 'Stored'
                    && /^(?:<1|\d+(?:\.\d+)?) (?:µs|ms|s)$/.test(metadataValues[1])
                    && metadataValues[2] === 'array'
                    && metadataValues[3] === 'array'
                    && sourceLink?.textContent.trim().includes('.php:')
                    && sourceLink.matches('[data-ndb-inspector-source-link]')
                    && !getComputedStyle(sourceLink).fontFamily.includes('JetBrains Mono')
                    && sourceLink.querySelector('svg') === null
                    && getComputedStyle(sourceLink).paddingLeft === '0px'
                    && getComputedStyle(sourceLink).paddingRight === '0px'
                    && getComputedStyle(sourceLink).textDecorationLine.includes('underline')
                    && rows.every((row) => !row.textContent.includes(`#${row.dataset.ndbCacheExecution}`))
                    && operationBadges.every((badge) => Math.round(badge.getBoundingClientRect().width) === 64)
                    && operationBadges.every((badge) => getComputedStyle(badge).paddingLeft === '8px')
                    && operationBadges.every((badge) => getComputedStyle(badge).borderRadius !== '0px')
                    && operationBadges.every((badge) => badge.getBoundingClientRect().bottom > badge.nextElementSibling.getBoundingClientRect().top)
                    && new Set(keyOffsets.map(Math.round)).size === 1
                    && keys.every((key) => key.clientWidth <= key.parentElement.getBoundingClientRect().width)
                    && keys.every((key) => getComputedStyle(key).textOverflow === 'ellipsis')
                    && keys.every((key) => !getComputedStyle(key).fontFamily.toLowerCase().includes('mono'))
                    && results.every((result) => getComputedStyle(result).backgroundColor === 'rgba(0, 0, 0, 0)')
                    && results.every((result) => getComputedStyle(result).textAlign === 'right')
                    && new Set(rightTrackWidths.map(Math.round)).size === 1
                    && rightTrackWidths.every((width) => width >= 72)
                    && new Set(resultRightEdges).size === 1
                    && new Set(durationRightEdges).size === 1
                    && resultRightEdges.every((right, index) => right === durationRightEdges[index])
                    && stores.every((store) => !store.endsWith(' store'))
                    && getComputedStyle(list.querySelector('[data-ndb-cache-list]')).overflowY === 'auto'
                    && getComputedStyle(detail).overflowY === 'auto'
                    && detail.tabIndex === 0
                    && content.scrollHeight <= content.clientHeight + 2
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && !document.querySelector('[data-ndb-cache]').textContent.includes('•')
                    && !document.querySelector('[data-ndb-cache]').textContent.includes('·');
            })()
            JS)
        ->click('[data-ndb-cache-item="3"]')
        ->assertScript(<<<'JS'
            (() => {
                const metadata = document.querySelector('[data-ndb-cache-metadata]');
                const followingDetails = metadata.nextElementSibling;

                return getComputedStyle(metadata).borderBottomWidth === '0px'
                    && getComputedStyle(metadata).paddingBottom === '0px'
                    && followingDetails.getClientRects().length === 0;
            })()
            JS)
        ->click('[data-ndb-cache-item="5"]')
        ->assertScript(<<<'JS'
            (() => {
                const metadata = document.querySelector('[data-ndb-cache-metadata]');
                const followingDetails = metadata.nextElementSibling;

                return getComputedStyle(metadata).borderBottomWidth === '0px'
                    && getComputedStyle(followingDetails).borderTopWidth === '1px'
                    && followingDetails.getClientRects().length === 1;
            })()
            JS)
        ->assertAttribute('[data-ndb-cache-item="5"]', 'aria-pressed', 'true')
        ->assertDontSee('Related uses')
        ->assertScript('document.querySelectorAll(\'[aria-label^="Open cache execution "]\').length', 0)
        ->select('[data-ndb-cache-filter]', 'failed')
        ->assertValue('[data-ndb-cache-filter]', 'failed')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-item]:not([hidden])").length', 3)
        ->keys('[data-ndb-cache-item="15"]', 'Enter')
        ->assertAttribute('[data-ndb-cache-item="15"]', 'aria-pressed', 'true')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-cache-item=\\"15\\"]")')
        ->assertSee('Failed')
        ->assertScript(
            'document.querySelector("[data-ndb-cache-item=\\"15\\"] [data-ndb-cache-list-duration]").textContent.trim()',
            '—',
        )
        ->assertScript(
            'document.querySelectorAll("[data-ndb-cache-metadata] dd")[1].textContent.trim()',
            '—',
        )
        ->assertScript('document.querySelector("[data-ndb-cache-detail-panel=overview] dd pre").textContent.trim()', 'not retained')
        ->assertDontSee('What happened')
        ->assertDontSee('Check next')
        ->click('[data-ndb-cache-metadata] button')
        ->assertAttribute('[data-ndb-cache-detail-tab="source"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-cache-detail-panel="source"]')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-cache-detail-panel="overview"]')
        ->assertMissing('[data-ndb-cache-detail-panel="raw"]')
        ->assertSee('tests/Support/DefinesTestApplication.php')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-cache-detail-panel="source"]');
                const fact = panel.querySelector('[data-ndb-inspector-source-fact]');
                const stack = panel.querySelector('[data-ndb-inspector-stack]');
                const source = fact.querySelector('dd > span');
                const functionCall = stack.querySelector('li code');
                const stackPath = stack.querySelector('[data-ndb-inspector-source-link] > span');

                return fact !== null
                    && stack !== null
                    && fact.querySelector('svg') === null
                    && !getComputedStyle(source).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFeatureSettings.includes('"calt"')
                    && !getComputedStyle(stackPath).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(source).color !== 'rgb(79, 70, 229)';
            })()
            JS)
        ->click('[data-ndb-cache-detail-tab="raw"]')
        ->assertVisible('[data-ndb-cache-detail-panel="raw"]')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-cache-detail-panel="overview"]')
        ->assertMissing('[data-ndb-cache-detail-panel="source"]')
        ->assertSee('Captured collector fields only')
        ->assertSee('Values are bounded and sensitive fields are redacted')
        ->assertSee('not retained')
        ->assertScript(<<<'JS'
            (() => {
                const code = document.querySelector('[data-ndb-cache-detail-panel="raw"] code[data-ndb-language="json"][data-highlighted]');
                const raw = code?.textContent ?? '';

                return raw.includes('"operation": "write_failed"')
                    && raw.includes('"value": "not retained"')
                    && code.querySelector('.hljs-attr, .hljs-string') !== null;
            })()
            JS)
        ->select('[data-ndb-cache-filter]', 'all')
        ->type('[data-ndb-cache-search]', 'missing-note')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-item]:not([hidden])").length', 1)
        ->assertSee('trip:kyoto:missing-note')
        ->type('[data-ndb-cache-search]', ' no operation matches')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-item]:not([hidden])").length', 0)
        ->assertSee('No cache operations match these controls.')
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-cache-workspace]');

    $page
        ->assertValue('[data-ndb-cache-search]', ' no operation matches')
        ->assertSee('No cache operations match these controls.')
        ->refresh()
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="cache"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-cache-workspace]');

    $page
        ->assertValue('[data-ndb-cache-filter]', 'all')
        ->assertValue('[data-ndb-cache-search]', '')
        ->assertScript('document.querySelector("[data-ndb-cache-sort]") === null')
        ->assertScript('document.querySelectorAll("[data-ndb-cache-item]:not([hidden])").length', 17)
        ->assertNoJavaScriptErrors();
});

it('drills into cache detail on mobile in dark mode', function () {
    $preferences = json_encode(['theme' => 'dark', 'favorites' => []], JSON_THROW_ON_ERROR);
    $page = visit('/profiled-cache-rich')->resize(390, 844);

    $page
        ->assertScript(<<<JS
            (() => {
                localStorage.setItem('newdebugbar.preferences.v1', '{$preferences}');

                return true;
            })()
            JS)
        ->refresh()
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="sections"]')
        ->click('[data-ndb-select-section="cache"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-cache-workspace]');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-cache-workspace]');
                const [list, detail] = workspace.children;
                const rows = [...document.querySelectorAll('[data-ndb-cache-item]')];

                return dialog.scrollWidth <= dialog.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && rows.every((row) => row.scrollWidth <= row.clientWidth + 1)
                    && document.querySelector('[data-ndb-cache-summary]').getBoundingClientRect().width <= workspace.getBoundingClientRect().width + 1;
            })()
            JS)
        ->select('[data-ndb-cache-filter]', 'failed')
        ->click('[data-ndb-cache-item="15"]')
        ->assertAttribute('[data-ndb-cache-item="15"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-cache-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-cache-workspace]');
                const [list, detail] = workspace.children;
                const content = document.querySelector('[data-ndb-inspector-content]');
                const back = document.querySelector('[data-ndb-cache-detail-back]');
                const tabs = [...document.querySelectorAll('[data-ndb-cache-detail-tab]')];
                const labels = tabs.map((tab) => tab.querySelector('span'));

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && detail.getBoundingClientRect().width >= workspace.getBoundingClientRect().width - 2
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && detail.scrollTop === 0
                    && content.scrollTop === 0
                    && content.scrollWidth <= content.clientWidth + 1
                    && back.getClientRects().length > 0
                    && back.textContent.trim() === 'Operations'
                    && tabs.length === 3
                    && tabs.map((tab) => tab.getAttribute('aria-label')).join('|') === 'Overview|Raw|Source'
                    && labels.every((label) => getComputedStyle(label).display === 'none')
                    && document.querySelector('[data-ndb-cache-copy-key]') === null
                    && document.querySelector('[data-ndb-cache-copy-raw]') === null;
            })()
            JS)
        ->click('[data-ndb-cache-detail-tab="raw"]')
        ->assertVisible('[data-ndb-cache-detail-panel="raw"]')
        ->assertScript('document.querySelector("[data-ndb-cache-detail-panel=raw]").scrollWidth <= document.querySelector("[data-ndb-cache-detail]").clientWidth + 1')
        ->click('[data-ndb-cache-detail-back]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-cache-workspace]');
                const [list, detail] = workspace.children;
                const selected = document.querySelector('[data-ndb-cache-item="15"]');

                return getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && selected.getAttribute('aria-pressed') === 'true';
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('shows a clear empty Cache state', function () {
    $page = visit('/profiled-cache-empty')->resize(1180, 720);

    $page
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::selectSectionViaPalette($page, 'cache');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-cache-empty]');

    $page
        ->assertSee('No cache operations were captured for this request.')
        ->assertSee('Reads, writes, deletes, and store flushes will appear here')
        ->assertScript('document.querySelector("[data-ndb-cache-workspace]") === null')
        ->assertScript('document.querySelector("[data-ndb-cache-empty]").scrollWidth <= document.querySelector("[data-ndb-inspector-content]").clientWidth + 1')
        ->assertNoJavaScriptErrors();
});

<?php

it('presents repeated queries as one shared list detail record', function () {
    visit('/profiled')
        ->resize(1440, 900)
        ->click('[data-ndb-toolbar="queries"]')
        ->waitForText('Repeated query pattern')
        ->assertVisible('[data-ndb-query-workspace]')
        ->assertVisible('[data-ndb-query-list]')
        ->assertVisible('[data-ndb-query-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ndb-queries]');
                const rows = [...root.querySelectorAll('[data-ndb-query-item]')];
                const repeated = rows.filter((row) => row.dataset.ndbRepeated === 'true');
                const typeBadge = repeated[0]?.querySelector('[data-ndb-query-type-badge]');
                const query = repeated[0]?.querySelector('[data-ndb-query-list-sql]');
                const queryHeading = root.querySelector('[data-ndb-query-list-heading] > :nth-child(2)');
                const driver = repeated[0]?.querySelector('[data-ndb-query-list-driver]');
                const duration = repeated[0]?.querySelector('[data-ndb-query-list-duration]');
                const sql = root.querySelector('[data-ndb-query-sql][data-highlighted]');
                const list = root.querySelector('[data-ndb-query-list]');
                const detail = root.querySelector('[data-ndb-query-detail]');
                const detailHeader = root.querySelector('[data-ndb-query-detail-header]');
                const sourcePanel = root.querySelector('[data-ndb-query-detail-panel="overview"] [data-ndb-inspector-source-panel]');
                const sourceDisclosure = sourcePanel?.querySelector('[data-ndb-inspector-disclosure]');
                const executionSelect = root.querySelector('[data-ndb-query-execution-select]');
                const repeatedRunsLabel = executionSelect?.parentElement?.previousElementSibling;
                const search = root.querySelector('[data-ndb-query-search]');
                const searchIcon = search?.parentElement.querySelector('svg');

                return rows.length === 1
                    && repeated.length === 1
                    && Number(repeated[0].dataset.ndbQueryExecutionCount) === 3
                    && repeated[0].getAttribute('aria-pressed') === 'true'
                    && typeBadge?.textContent.trim() === 'read'
                    && Math.abs(typeBadge.getBoundingClientRect().width - 56) <= 1
                    && Math.abs(typeBadge.getBoundingClientRect().height - 18) <= 1
                    && root.querySelector('[data-ndb-query-attention-badge]') === null
                    && getComputedStyle(repeated[0]).backgroundColor !== 'rgba(0, 0, 0, 0)'
                    && driver?.textContent.trim() === 'sqlite'
                    && driver.getBoundingClientRect().bottom <= duration.getBoundingClientRect().top + 1
                    && Math.abs(query.getBoundingClientRect().left - queryHeading.getBoundingClientRect().left) <= 1
                    && Math.abs(query.getBoundingClientRect().left - typeBadge.getBoundingClientRect().right - 12) <= 1
                    && sql?.querySelector('.hljs-keyword') !== null
                    && root.querySelectorAll('[data-ndb-query-detail-panel]').length === 1
                    && root.querySelector('[data-ndb-query-detail-panel="overview"]') !== null
                    && root.querySelector('[data-ndb-query-detail-panel="overview"] [data-ndb-inspector-source-panel]') !== null
                    && root.querySelector('[data-ndb-query-detail-tab="source"]') === null
                    && root.querySelector('[data-ndb-query-sort]') === null
                    && root.querySelector('[data-ndb-query-list-source]') === null
                    && detailHeader?.querySelector('dl') === null
                    && detailHeader?.textContent.trim() === 'Repeated query pattern'
                    && detailHeader?.getBoundingClientRect().height <= 54
                    && sourceDisclosure?.tagName === 'DETAILS'
                    && sourceDisclosure.open === false
                    && sourcePanel.querySelector('[data-ndb-inspector-stack]') === null
                    && root.querySelector('details:not([data-ndb-inspector-disclosure])') === null
                    && getComputedStyle(list).overflowY === 'auto'
                    && getComputedStyle(detail).overflowY === 'auto'
                    && searchIcon?.getBoundingClientRect().left < search.getBoundingClientRect().left + 32
                    && executionSelect?.previousElementSibling?.textContent.trim() === 'Choose a repeated run'
                    && repeatedRunsLabel?.textContent.trim() === 'Repeated runs (3)'
                    && ! root.textContent.includes('Why these executions are grouped')
                    && ! root.textContent.includes('•')
                    && ! root.textContent.includes('·');
            })()
            JS)
        ->assertScript('document.querySelector("[data-ndb-query-execution-select]").options.length', 3)
        ->select('[data-ndb-query-execution-select]', '2')
        ->assertValue('[data-ndb-query-execution-select]', '2')
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ndb-queries]');
                const query = root.querySelector('[data-ndb-query-sql][data-highlighted]');

                return query?.textContent.trim() === 'select 2 as number'
                    && root.querySelector('[data-ndb-query-detail-tab="bindings"]') === null
                    && root.querySelector('[data-ndb-query-detail-panel="bindings"]') === null
                    && root.querySelectorAll('[data-ndb-query-detail-panel]').length === 1
                    && root.querySelector('[data-ndb-query-detail-panel="overview"]') !== null
                    && root.querySelector('[data-ndb-query-detail-panel="overview"] [data-ndb-inspector-source-panel]') !== null;
            })()
            JS)
        ->assertSee('DefinesTestApplication.php')
        ->assertScript('document.querySelectorAll("[data-ndb-query-detail-panel]").length', 1)
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->click('[data-ndb-toolbar="queries"]')
        ->assertVisible('[data-ndb-query-detail-panel="overview"]')
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.querySelector('[data-ndb-queries]'));

                return state.querySelectedExecution === 2
                    && state.queryDetailTab === 'overview'
                    && state.queryRecords[0].executions.length === 3;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('filters searches and sorts a varied query profile', function () {
    visit('/profiled-queries-rich')
        ->resize(1440, 760)
        ->click('[data-ndb-toolbar="queries"]')
        ->waitForText('queries')
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ndb-queries]');
                const state = Alpine.$data(root);
                const rows = [...root.querySelectorAll('[data-ndb-query-item]')];
                const retained = rows.reduce((count, row) => count + Number(row.dataset.ndbQueryExecutionCount), 0);
                const repeated = state.queryRecords.find((record) => record.repeated && record.count === 8);
                const repeatedRow = rows.find((row) => row.dataset.ndbRepeated === 'true' && row.dataset.ndbSlow === 'false');
                const slowRow = rows.find((row) => row.dataset.ndbSlow === 'true' && row.dataset.ndbRepeated === 'false');
                const microsecondRow = rows.find((row) => Number(row.dataset.ndbDuration) > 0 && Number(row.dataset.ndbDuration) < 1);
                const connections = new Set(
                    state.queryRecords.flatMap((record) => record.executions.map((query) => query.connection)),
                );

                return retained >= 16
                    && state.visibleQueryCount === retained
                    && rows.length >= 8
                    && repeated?.executions.length === 8
                    && new Set(repeated.executions.map((query) => query.display_sql)).size === 8
                    && repeated.executions.every((query) => ! Object.hasOwn(query, 'bindings'))
                    && connections.has('testing')
                    && connections.has('query_replica')
                    && rows.every((row) => row.querySelector('[data-ndb-query-type-badge]') !== null)
                    && rows.every((row) => row.querySelector('[data-ndb-query-attention-badge]') === null)
                    && rows.every((row) => row.querySelector('[data-ndb-query-list-driver]')?.textContent.trim() === 'sqlite')
                    && repeatedRow.classList.contains('ndb:bg-amber-50/70')
                    && repeatedRow.classList.contains('ndb:dark:bg-amber-950/25')
                    && slowRow.classList.contains('ndb:bg-red-50/70')
                    && slowRow.classList.contains('ndb:dark:bg-red-950/25')
                    && getComputedStyle(repeatedRow).backgroundColor !== 'rgba(0, 0, 0, 0)'
                    && getComputedStyle(slowRow).backgroundColor !== 'rgba(0, 0, 0, 0)'
                    && getComputedStyle(repeatedRow).backgroundColor !== getComputedStyle(slowRow).backgroundColor
                    && microsecondRow.querySelector('[data-ndb-query-list-duration]').textContent.trim().endsWith('µs')
                    && state.queryRecords.some((record) => record.sql.length > 150);
            })()
            JS)
        ->select('[data-ndb-query-filter]', 'attention')
        ->assertValue('[data-ndb-query-filter]', 'attention')
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-ndb-query-item]:not([hidden])')]
                .every((row) => row.dataset.ndbAttention === 'true')
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const heading = document.querySelector('[data-ndb-query-sort-heading="duration"]');
                const label = [...heading.children].find((child) => child.getAttribute('aria-hidden') === 'true' && ! child.matches('[data-ndb-sort-indicator]'));
                window.__newdebugbarQueryTimeLabelLeft = label?.getBoundingClientRect().left;

                return document.querySelector('[data-ndb-query-sort]') === null
                    && heading?.getAttribute('aria-pressed') === 'false'
                    && Number.isFinite(window.__newdebugbarQueryTimeLabelLeft);
            })()
            JS)
        ->click('[data-ndb-query-sort-heading="duration"]')
        ->assertAttribute('[data-ndb-query-sort-heading="duration"]', 'aria-pressed', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-query-item]:not([hidden])')];
                const durations = visible.map((row) => Number(row.dataset.ndbDuration));
                const heading = document.querySelector('[data-ndb-query-sort-heading="duration"]');
                const indicator = heading.querySelector('[data-ndb-sort-indicator]');

                return durations.every((duration, index) => index === 0 || durations[index - 1] >= duration)
                    && getComputedStyle(heading).color !== getComputedStyle(heading.parentElement).color
                    && indicator.getBoundingClientRect().width > 0;
            })()
            JS)
        ->click('[data-ndb-query-sort-heading="duration"]')
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-query-item]:not([hidden])')];
                const durations = visible.map((row) => Number(row.dataset.ndbDuration));

                return durations.every((duration, index) => index === 0 || durations[index - 1] <= duration);
            })()
            JS)
        ->click('[data-ndb-query-sort-heading="duration"]')
        ->assertAttribute('[data-ndb-query-sort-heading="duration"]', 'aria-pressed', 'false')
        ->assertScript(<<<'JS'
            (() => {
                const heading = document.querySelector('[data-ndb-query-sort-heading="duration"]');
                const label = [...heading.children].find((child) => child.getAttribute('aria-hidden') === 'true' && ! child.matches('[data-ndb-sort-indicator]'));
                const indicatorIcon = heading.querySelector('[data-ndb-sort-indicator] svg');
                const executions = [...document.querySelectorAll('[data-ndb-query-item]:not([hidden])')]
                    .map((row) => Number(row.dataset.ndbExecution));

                return Math.abs(label.getBoundingClientRect().left - window.__newdebugbarQueryTimeLabelLeft) <= 1
                    && getComputedStyle(indicatorIcon).display === 'none'
                    && executions.every((execution, index) => index === 0 || executions[index - 1] <= execution);
            })()
            JS)
        ->select('[data-ndb-query-filter]', 'write')
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-query-item]:not([hidden])')];

                return visible.length >= 4 && visible.every((row) => row.dataset.ndbQueryType === 'write');
            })()
            JS)
        ->select('[data-ndb-query-filter]', 'all')
        ->fill('[data-ndb-query-search]', 'connection_probe')
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-ndb-query-item]:not([hidden])')].length === 1
                && Alpine.$data(document.querySelector('[data-ndb-queries]')).visibleQueryCount === 1
            JS)
        ->assertSee('query_replica')
        ->fill('[data-ndb-query-search]', 'nothing can match this query')
        ->waitForText('No queries match these controls.')
        ->assertScript('document.querySelectorAll("[data-ndb-query-item]:not([hidden])").length', 0)
        ->assertMissing('[data-ndb-query-active-detail]')
        ->assertNoJavaScriptErrors();
});

it('runs EXPLAIN for the selected repeated execution', function () {
    visit('/profiled')
        ->resize(1100, 620)
        ->click('[data-ndb-toolbar="queries"]')
        ->waitForText('Repeated query pattern')
        ->select('[data-ndb-query-execution-select]', '2')
        ->click('[data-ndb-query-detail-tab="explain"]')
        ->waitForText('EXPLAIN QUERY PLAN')
        ->assertVisible('[data-ndb-query-explain-result]')
        ->assertDontSee('What to check in this plan')
        ->assertMissing('[data-ndb-query-explain-action]')
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.querySelector('[data-ndb-queries]'));

                return state.querySelectedExecution === 2
                    && state.queryExplainExecution === 2
                    && state.queryExplainLoading === false
                    && Array.isArray(state.queryExplain?.rows)
                    && document.querySelector('[data-ndb-query-explain-plan][data-highlighted]') !== null;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('keeps an EXPLAIN failure visible', function () {
    visit('/profiled-queries-rich')
        ->resize(1200, 760)
        ->click('[data-ndb-toolbar="queries"]')
        ->fill('[data-ndb-query-search]', 'slow_probe')
        ->waitForText('1 shown')
        ->click('[data-ndb-query-detail-tab="explain"]')
        ->waitForText('SQLite cannot find a function used by this query')
        ->assertVisible('[data-ndb-query-explain-error]')
        ->assertSee('Check its name or register it on the query connection, then reload.')
        ->assertAttribute('[data-ndb-query-detail-panel="explain"] [role="alert"]', 'role', 'alert')
        ->assertScript(<<<'JS'
            (() => {
                const error = document.querySelector('[data-ndb-query-explain-error]')?.textContent ?? '';

                return ! error.includes('SQLSTATE') && ! error.includes('Database:');
            })()
            JS)
        ->assertMissing('[data-ndb-query-explain-action]')
        ->assertNoJavaScriptErrors();
});

it('moves from the query list to one focused mobile detail', function () {
    visit('/profiled-queries-rich')
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-metric-scope="toolbar"][data-ndb-mobile-toolbar-metric="queries"]')
        ->assertVisible('[data-ndb-query-search]')
        ->assertVisible('[data-ndb-query-list]')
        ->assertVisible('[data-ndb-query-list-heading]')
        ->assertVisible('[data-ndb-query-sort-heading="duration"]')
        ->assertScript(<<<'JS'
            (() => {
                const root = document.getElementById('newdebugbar');
                const workspace = document.querySelector('[data-ndb-query-workspace]');
                const detail = document.querySelector('[data-ndb-query-detail]');

                return root.scrollWidth <= root.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && getComputedStyle(detail).display === 'none';
            })()
            JS)
        ->click('[data-ndb-query-item][data-ndb-repeated="true"]')
        ->assertVisible('[data-ndb-query-detail]')
        ->assertVisible('[data-ndb-query-detail-back]')
        ->assertSeeIn('[data-ndb-query-detail-tab="overview"]', 'Overview')
        ->assertMissing('[data-ndb-query-detail-tab="bindings"]')
        ->assertMissing('[data-ndb-query-detail-tab="source"]')
        ->assertSeeIn('[data-ndb-query-detail-tab="explain"]', 'EXPLAIN')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-query-detail]');
                const list = document.querySelector('[data-ndb-query-list]');

                return detail.getBoundingClientRect().width <= 390
                    && getComputedStyle(list.parentElement).display === 'none'
                    && document.querySelector('[data-ndb-query-detail-panel="overview"] [data-ndb-inspector-source-panel]') !== null
                    && document.querySelectorAll('[data-ndb-query-detail-panel]').length === 1;
            })()
            JS)
        ->click('[data-ndb-query-detail-back]')
        ->assertVisible('[data-ndb-query-list]')
        ->wait(0.1)
        ->assertScript('document.activeElement.matches("[data-ndb-query-item][data-ndb-repeated=true]")')
        ->assertNoJavaScriptErrors();
});

it('renders highlighted query evidence and attention tints in each product theme', function (string $theme) {
    $preferences = json_encode(['theme' => $theme, 'favorites' => []], JSON_THROW_ON_ERROR);

    visit('/profiled-queries-rich')
        ->assertScript(<<<JS
            (() => {
                localStorage.setItem('newdebugbar.preferences.v1', '{$preferences}');

                return true;
            })()
            JS)
        ->refresh()
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', $theme)
        ->click('[data-ndb-toolbar="queries"]')
        ->waitForText('queries')
        ->assertScript(<<<'JS'
            (() => {
                const keyword = document.querySelector('[data-ndb-query-sql][data-highlighted] .hljs-keyword');
                const detail = document.querySelector('[data-ndb-query-detail]');
                const repeated = document.querySelector('[data-ndb-query-item][data-ndb-repeated="true"][data-ndb-slow="false"]');
                const slow = document.querySelector('[data-ndb-query-item][data-ndb-slow="true"][data-ndb-repeated="false"]');

                return keyword !== null
                    && detail !== null
                    && getComputedStyle(keyword).color !== 'rgb(0, 0, 0)'
                    && repeated.classList.contains('ndb:bg-amber-50/70')
                    && repeated.classList.contains('ndb:dark:bg-amber-950/25')
                    && slow.classList.contains('ndb:bg-red-50/70')
                    && slow.classList.contains('ndb:dark:bg-red-950/25')
                    && getComputedStyle(repeated).backgroundColor !== 'rgba(0, 0, 0, 0)'
                    && getComputedStyle(slow).backgroundColor !== 'rgba(0, 0, 0, 0)'
                    && getComputedStyle(repeated).backgroundColor !== getComputedStyle(slow).backgroundColor;
            })()
            JS)
        ->assertNoJavaScriptErrors();
})->with(['light', 'dark']);

it('shows a clear empty query state when no SQL was captured', function () {
    $preferences = json_encode(['theme' => 'light', 'favorites' => ['queries']], JSON_THROW_ON_ERROR);

    visit('/profiled-queries-empty')
        ->assertScript(<<<JS
            (() => {
                localStorage.setItem('newdebugbar.preferences.v1', '{$preferences}');

                return true;
            })()
            JS)
        ->refresh()
        ->click('[data-ndb-toolbar="request"]')
        ->click('[data-ndb-section="queries"]')
        ->waitForText('No database queries were captured for this request.')
        ->assertMissing('[data-ndb-query-workspace]')
        ->assertNoJavaScriptErrors();
});

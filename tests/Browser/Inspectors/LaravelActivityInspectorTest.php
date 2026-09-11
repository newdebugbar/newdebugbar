<?php

use NewDebugBar\Tests\Fixtures\Events\ProfiledApplicationListener;
use NewDebugBar\Tests\Fixtures\Events\ProfiledQueuedApplicationListener;
use NewDebugBar\Tests\Support\DebugBarBrowser;

it('switches from Cache diagnostics to current Events evidence', function () {
    $page = visit('/profiled')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-inspector="cache"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertSee('Hit rate')
        ->assertScript(<<<'JS'
            (() => {
                const results = Array.from(document.querySelectorAll('[data-ndb-cache-result]'))
                    .map((result) => result.textContent.trim());

                return results.includes('Hit') && results.includes('Miss');
            })()
            JS)
        ->click('[data-ndb-select-inspector="events"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertValue('[data-ndb-event-source-control]', 'application')
        ->assertNoJavaScriptErrors();

    DebugBarBrowser::assertInspectorSelected($page, 'events');
});

it('groups noisy Laravel events around application evidence', function () {
    $page = visit('/profiled-events')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-inspector="events"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertScript(<<<'JS'
            (() => {
                const source = document.querySelector('[data-ndb-event-source-control]');
                const options = Array.from(source.options);
                const visible = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];

                return options.map((option) => option.value).join('|') === 'all|application|framework'
                    && source.value === 'application'
                    && visible.length > 0
                    && visible.every((item) => item.dataset.ndbEventSourceValue === 'application')
                    && document.querySelector('[data-ndb-event-sort]') === null
                    && document.querySelector('[data-ndb-event-list]').getAttribute('aria-label') === 'Laravel events';
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const items = [...document.querySelectorAll('[data-ndb-event-item]')];

                return ['application', 'all', 'framework'].every((source) => {
                    const expected = items
                        .filter((item) => source === 'all' || item.dataset.ndbEventSourceValue === source)
                        .reduce((count, item) => count + Number(item.dataset.ndbEventOccurrenceCount), 0);
                    const count = document.querySelector(`[data-ndb-event-source-count="${source}"]`);

                    return count && count.textContent.trim().endsWith(`(${expected})`);
                });
            })()
            JS)
        ->assertScript('document.querySelector("[data-ndb-event-source-control]").getBoundingClientRect().height === 36')
        ->select('[data-ndb-event-source-control]', 'all')
        ->assertValue('[data-ndb-event-source-control]', 'all')
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];
                const all = [...document.querySelectorAll('[data-ndb-event-item]')];

                return visible.length === all.length
                    && new Set(visible.map((item) => item.dataset.ndbEventSourceValue)).size === 2;
            })()
            JS)
        ->select('[data-ndb-event-source-control]', 'application')
        ->assertValue('[data-ndb-event-source-control]', 'application')
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];
                const selected = document.querySelector('[data-ndb-event-item][aria-pressed="true"]');

                return visible.length === 4
                    && visible.every((item) => item.dataset.ndbEventSourceValue === 'application')
                    && visible.reduce((count, item) => count + Number(item.dataset.ndbEventOccurrenceCount), 0) === 12
                    && selected?.dataset.ndbEventSourceValue === 'application'
                    && document.querySelector('[data-ndb-event-visible-summary]').textContent.trim() === '4 events, 12 dispatches';
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const rows = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];
                const tracks = rows.map((row) => getComputedStyle(row).gridTemplateColumns);
                const attention = [...document.querySelectorAll('[data-ndb-event-item] span')]
                    .find((element) => element.textContent.trim() === 'Duplicate registration');
                const attentionRow = attention?.closest('[data-ndb-event-item]');
                const rightEdges = rows.flatMap((row) => [row.children[1], row.children[3]])
                    .map((element) => Math.round(element.getBoundingClientRect().right));

                return rows.length > 1
                    && new Set(tracks).size === 1
                    && rows.every((row) => row.children.length === 4 && row.getBoundingClientRect().height <= 64)
                    && new Set(rightEdges).size === 1
                    && attention
                    && attention === attentionRow.children[3]
                    && attention.getBoundingClientRect().right <= attentionRow.getBoundingClientRect().right;
            })()
            JS)
        ->assertSee(ProfiledApplicationListener::class.'@handle')
        ->assertSee(ProfiledQueuedApplicationListener::class.'@handle')
        ->assertSee('2 registrations')
        ->assertSee('1 extra registration')
        ->assertSee('Listener handling')
        ->assertDontSee('What should I inspect if this event looks wrong?')
        ->assertMissing('private fixture value')
        ->assertScript(<<<'JS'
            (() => {
                const header = document.querySelector('[data-ndb-event-header]');
                const overview = document.querySelector('[data-ndb-event-detail-panel="overview"]');
                const facts = overview.querySelector('[data-ndb-event-facts]');

                return header.querySelector('[data-ndb-event-qualified-name]')
                    && ! header.querySelector('[data-ndb-event-facts]')
                    && facts
                    && facts.querySelectorAll('[data-ndb-event-fact]').length === 4
                    && ! document.querySelector('[data-ndb-event-copy-name]')
                    && ! overview.querySelector('[data-ndb-event-copy-listener-source]');
            })()
            JS)
        ->assertAttribute('[data-ndb-event-detail-tab="overview"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-event-detail-panel="overview"]')
        ->click('[data-ndb-event-detail-tab="payload"]')
        ->assertAttribute('[data-ndb-event-detail-tab="payload"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-event-detail-panel="payload"]')
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-ndb-event-detail-panel="payload"] dl > div')]
                .every((row) => row.querySelector(':scope > dt') && row.querySelector(':scope > dd'))
            JS)
        ->assertSee('trip')
        ->assertSee('changes')
        ->assertMissing('[data-ndb-event-copy-payload-shape]')
        ->click('[data-ndb-event-detail-tab="source"]')
        ->assertAttribute('[data-ndb-event-detail-tab="source"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-event-detail-panel="source"]')
        ->assertCount('[data-ndb-event-copy-dispatch-source]', 2)
        ->assertScript(<<<'JS'
            (() => {
                const buttons = [...document.querySelectorAll('[data-ndb-event-copy-dispatch-source]')];

                window.newdebugbarEventClipboardWrites = [];
                window.newdebugbarExpectedEventClipboard = buttons[0].textContent.trim();
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarEventClipboardWrites.push(value),
                    },
                });

                return buttons.every((button) => button.getClientRects().length > 0);
            })()
            JS)
        ->click('[data-ndb-event-copy-dispatch-source-index="0"]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            window.newdebugbarEventClipboardWrites.length === 1
                && window.newdebugbarEventClipboardWrites[0] === window.newdebugbarExpectedEventClipboard
            JS)
        ->click('[data-ndb-event-detail-tab="overview"]')
        ->type('[data-ndb-event-search]', 'ItineraryRecalculation');

    DebugBarBrowser::waitForVisibleElement(
        $page,
        '[data-ndb-event-item][data-ndb-event-occurrence-count="8"]:not([hidden])',
    );

    $page
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];

                return visible.length === 1
                    && visible[0].dataset.ndbEventOccurrenceCount === '8'
                    && document.querySelector('[data-ndb-event-detail-title]').textContent.includes('ItineraryRecalculation');
            })()
            JS)
        ->type('[data-ndb-event-search]', 'event-that-does-not-exist');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-event-empty]');

    $page
        ->assertSee('No events match this source and search.')
        ->assertSee('No event is selected. Adjust the source filter or search.')
        ->assertScript('document.querySelectorAll("[data-ndb-event-item]:not([hidden])").length === 0')
        ->type('[data-ndb-event-search]', '')
        ->select('[data-ndb-event-source-control]', 'framework')
        ->assertValue('[data-ndb-event-source-control]', 'framework')
        ->assertScript(<<<'JS'
            (() => {
                const visible = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];
                return visible.length > 0
                    && visible.every((item) => item.dataset.ndbEventSourceValue === 'framework')
                    && document.querySelector('[data-ndb-event-detail-title]').textContent.trim().length > 0;
            })()
            JS)
        ->assertNoJavaScriptErrors();

    DebugBarBrowser::assertInspectorSelected($page, 'events');
});

it('keeps Events selection focused with one mobile scroll owner', function () {
    $page = visit('/profiled-events')
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-select-inspector="events"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertVisible('[data-ndb-event-item][aria-pressed="true"]')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-event-workspace]');
                const [list, detail] = workspace.children;
                const rows = [...document.querySelectorAll('[data-ndb-event-item]:not([hidden])')];
                const checks = {
                    dialogOverflow: dialog.scrollWidth <= dialog.clientWidth + 1,
                    workspaceOverflow: workspace.scrollWidth <= workspace.clientWidth + 1,
                    mobileWorkspace: getComputedStyle(workspace).display !== 'grid',
                    listVisible: getComputedStyle(list).display === 'flex',
                    detailHidden: getComputedStyle(detail).display === 'none',
                    compactRows: rows.every((row) => row.getBoundingClientRect().height <= 64),
                };
                const failures = Object.entries(checks).filter(([, passed]) => !passed).map(([name]) => name);

                if (failures.length > 0) {
                    throw new Error(
                        failures.join(', ') + '; row heights: ' + rows.map((row) => row.getBoundingClientRect().height).join(', '),
                    );
                }

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const selected = document.querySelector('[data-ndb-event-item][aria-pressed="true"]');
                selected.focus();

                return document.activeElement === selected;
            })()
            JS)
        ->keys('[data-ndb-event-item][aria-pressed="true"]', 'Enter');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-event-detail]');

    $page
        ->assertVisible('[data-ndb-event-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-event-workspace]');
                const [list, detail] = workspace.children;
                const tabs = [...document.querySelectorAll('[data-ndb-event-detail-tab]')];
                const detailTabGroup = tabs[0].closest('[data-ndb-filter-tabs]');
                const labels = tabs.map((tab) => tab.querySelector('span'));
                const icons = tabs.map((tab) => tab.querySelector('[data-ndb-event-detail-tab-icon]'));
                const candidates = [content, list, detail];
                const scrollOwners = candidates.filter((element) => {
                    const overflow = getComputedStyle(element).overflowY;

                    return element.scrollHeight > element.clientHeight + 1
                        && (overflow === 'auto' || overflow === 'scroll');
                });

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && document.activeElement === detail
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && tabs.length === 3
                    && tabs.every((tab) => tab.dataset.ndbFilterTabVariant === 'segmented')
                    && detailTabGroup.dataset.ndbFilterTabsVariant === 'segmented'
                    && labels.every((label) => getComputedStyle(label).display === 'none')
                    && icons.every((icon) => getComputedStyle(icon).display !== 'none')
                    && scrollOwners.length === 1
                    && scrollOwners[0] === content;
            })()
            JS)
        ->keys('[data-ndb-event-detail-tab="payload"]', 'Enter')
        ->assertAttribute('[data-ndb-event-detail-tab="payload"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-event-detail-panel="payload"]')
        ->keys('[data-ndb-event-detail-back]', 'Enter');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-event-item][aria-pressed="true"]');

    $page
        ->assertScript(<<<'JS'
            (() => {
                const selected = document.querySelector('[data-ndb-event-item][aria-pressed="true"]');
                const detail = document.querySelector('[data-ndb-event-detail]');

                return document.activeElement === selected
                    && getComputedStyle(detail).display === 'none';
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('presents Laravel decisions and source context without editor links', function () {
    $page = visit('/profiled-context')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertMissing('[data-ndb-findings]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->click('[data-ndb-select-inspector="authorization"]')
        ->assertScript('document.querySelector("[data-ndb-inspector-heading]").textContent.trim() === "Authorization"')
        ->assertAttribute('[data-ndb-select-inspector="authorization"]', 'aria-current', 'page')
        ->assertScript(<<<'JS'
            (() => {
                const authorization = document.querySelector('[data-ndb-authorization-filter-control]');

                return authorization.tagName === 'SELECT'
                    && authorization.closest('[data-ndb-inspector-list-controls]') !== null
                    && authorization.closest('label') !== null;
            })()
            JS)
        ->select('[data-ndb-authorization-filter-control]', 'denied')
        ->assertValue('[data-ndb-authorization-filter-control]', 'denied')
        ->assertScript('document.querySelectorAll("[data-ndb-authorization-item]:not([hidden])").length', 1)
        ->assertScript('document.querySelector("[data-ndb-authorization-item]:not([hidden])").dataset.ndbAuthorizationResult === "denied"')
        ->assertSee('delete-profile')
        ->select('[data-ndb-authorization-filter-control]', 'allowed')
        ->assertValue('[data-ndb-authorization-filter-control]', 'allowed')
        ->assertScript('document.querySelector("[data-ndb-authorization-item]:not([hidden])").dataset.ndbAuthorizationResult === "allowed"')
        ->assertSee('inspect-profile');

    $page->click('[data-ndb-select-inspector="events"]');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->click('[data-ndb-event-item][aria-pressed="true"]')
        ->assertSee(ProfiledApplicationListener::class.'@handle')
        ->assertSee(ProfiledQueuedApplicationListener::class.'@handle')
        ->assertSee('Completed and queued')
        ->assertSee('2 registrations')
        ->assertMissing('[data-ndb-event-copy-listener-source]')
        ->assertMissing('a[href^="vscode://file/"]')
        ->assertNoJavaScriptErrors();

    DebugBarBrowser::assertInspectorSelected($page, 'events');
});

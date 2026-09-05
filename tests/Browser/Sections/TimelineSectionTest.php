<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps the desktop waterfall useful inside the shared timeline workspace', function () {
    $page = visit('/profiled-timeline-long');
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: 'dark', favorites: []}))");

    $page
        ->refresh()
        ->resize(1280, 720)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="timeline"]');

    DebugBarBrowser::assertSectionSelected($page, 'timeline');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertPresent('[data-ndb-timeline-workspace]')
        ->assertVisible('[data-ndb-timeline-waterfall-header]')
        ->assertValue('[data-ndb-timeline-filter]', 'key')
        ->assertScript(<<<'JS'
            (() => {
                const sentinel = document.querySelector('[data-ndb-timeline-page-sentinel]');

                return sentinel?.getAttribute('role') === 'status'
                    && sentinel.getAttribute('aria-live') === 'polite'
                    && document.querySelector('[data-ndb-timeline-load-more]') === null;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const controls = document.querySelector('[data-ndb-timeline-list-panel] [data-ndb-inspector-list-controls]');
                const search = document.querySelector('[data-ndb-timeline-search-field]').getBoundingClientRect();
                const filter = document.querySelector('[data-ndb-timeline-filter]').getBoundingClientRect();
                const workspace = document.querySelector('[data-ndb-timeline-workspace]');

                return search.left < filter.left
                    && search.right <= filter.left
                    && controls.scrollWidth <= controls.clientWidth
                    && workspace.getBoundingClientRect().height > 400
                    && workspace.scrollWidth <= workspace.clientWidth;
            })()
            JS);

    $page->script("document.querySelector('[data-ndb-timeline-page-sentinel]').scrollIntoView({ block: 'end' })");

    $page
        ->assertScript('document.querySelectorAll("[data-ndb-timeline-item]").length >= 100')
        ->select('[data-ndb-timeline-filter]', 'queries')
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-timeline-item]:not([hidden])'))
                .every((item) => item.dataset.ndbTimelineSection === 'queries')
            JS)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-timeline-item]:not([hidden])'))
                .every((item) => {
                    const track = item.querySelector('[data-ndb-timeline-track]').getBoundingClientRect();
                    const mark = item.querySelector('[data-ndb-timeline-mark]').getBoundingClientRect();

                    return item.dataset.ndbTimelineKind === 'Duration'
                        && Number(item.dataset.ndbTimelineStart) < Number(item.dataset.ndbTimelineAt)
                        && Number(item.dataset.ndbTimelineDuration) > 0
                        && mark.width >= 3
                        && mark.left >= track.left
                        && mark.right <= track.right + 1;
                })
            JS)
        ->click('[data-ndb-timeline-item="queries-0"]')
        ->assertVisible('[data-ndb-timeline-detail-content]')
        ->assertVisible('[data-ndb-timeline-open-section]')
        ->assertVisible('[data-ndb-timeline-detail-content] [data-ndb-inspector-source-link]')
        ->assertSeeIn(
            '[data-ndb-timeline-detail-content] [data-ndb-inspector-source-link]',
            'tests/Support/DefinesTestApplication.php',
        )
        ->assertScript('document.querySelectorAll("[data-ndb-timeline-detail-content]").length === 1')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-inspector-focus-detail]');
                const title = detail.querySelector('[data-ndb-timeline-detail-label]');
                const category = title.previousElementSibling;
                const facts = detail.querySelector('[data-ndb-inspector-facts]');
                const checks = {
                    groupedIdentity: category.matches('p') && category.parentElement === title.parentElement,
                    leftAlignment: Math.abs(category.getBoundingClientRect().left - title.getBoundingClientRect().left) <= 1,
                    readingOrder: category.getBoundingClientRect().bottom <= title.getBoundingClientRect().top,
                    detailContainer: getComputedStyle(detail).containerType === 'inline-size',
                    detailFit: detail.scrollWidth <= detail.clientWidth + 1,
                    factsFit: facts.scrollWidth <= facts.clientWidth + 1
                        && [...facts.children].every((fact) => fact.scrollWidth <= fact.clientWidth + 1),
                };
                const failures = Object.entries(checks).filter(([, passed]) => ! passed).map(([name]) => name);

                if (failures.length > 0) throw new Error('Timeline detail layout failed: ' + failures.join(', '));

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-timeline-detail-content]');
                const source = detail.querySelector('[data-ndb-inspector-source-link]');

                window.newdebugbarTimelineClipboard = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarTimelineClipboard.push(value),
                    },
                });

                return getComputedStyle(source).fontFamily === getComputedStyle(detail).fontFamily;
            })()
            JS)
        ->click('[data-ndb-timeline-detail-content] [data-ndb-inspector-source-link]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            window.newdebugbarTimelineClipboard.length === 1
                && /^tests\/Support\/DefinesTestApplication\.php:\d+$/.test(window.newdebugbarTimelineClipboard[0])
            JS)
        ->click('[data-ndb-inspector-focus-back]')
        ->assertVisible('[data-ndb-timeline-list]');

    DebugBarBrowser::waitForFocus($page, '[data-ndb-timeline-item][aria-pressed="true"]');

    $page
        ->type('[data-ndb-timeline-search-field]', 'nothing can match this timeline activity')
        ->assertScript('document.querySelectorAll("[data-ndb-timeline-item]:not([hidden])").length', 0)
        ->assertSee('No timeline activity matches this search and filter.')
        ->assertNoJavaScriptErrors();
});

it('turns the timeline into a mobile chronological drill-in without horizontal overflow', function () {
    $page = visit('/profiled-timeline-long');
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: 'light', favorites: []}))");

    $page
        ->refresh()
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="sections"]')
        ->click('[data-ndb-select-section="timeline"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->assertMissing('[data-ndb-timeline-load-more]')
        ->assertScript(<<<'JS'
            (() => {
                const stage = document.querySelector('[data-ndb-section-stage]').getBoundingClientRect();
                const workspace = document.querySelector('[data-ndb-timeline-workspace]');
                const workspaceBox = workspace.getBoundingClientRect();
                const row = document.querySelector('[data-ndb-timeline-item]:not([hidden])');
                const track = row.querySelector('[data-ndb-timeline-track]');
                const near = (actual, expected) => Math.abs(actual - expected) <= 1;

                return getComputedStyle(track).display === 'none'
                    && near(workspaceBox.left, stage.left)
                    && near(workspaceBox.right, stage.right)
                    && workspace.scrollWidth <= workspace.clientWidth
                    && row.scrollWidth <= row.clientWidth;
            })()
            JS);

    $page->script("document.querySelector('[data-ndb-timeline-page-sentinel]').scrollIntoView({ block: 'end' })");

    $page
        ->assertScript('document.querySelectorAll("[data-ndb-timeline-item]").length >= 100')
        ->click('[data-ndb-timeline-item="request-start"]')
        ->assertVisible('[data-ndb-timeline-detail-content]')
        ->assertMissing('[data-ndb-timeline-detail-content] [data-ndb-inspector-source-link]')
        ->assertSeeIn('[data-ndb-timeline-detail-content]', 'Not captured for this activity.')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-timeline-list]").closest("[data-ndb-inspector-focus-list]")).display === "none"')
        ->assertScript(<<<'JS'
            (() => {
                const stage = document.querySelector('[data-ndb-section-stage]').getBoundingClientRect();
                const workspace = document.querySelector('[data-ndb-timeline-workspace]');
                const workspaceBox = workspace.getBoundingClientRect();
                const detail = document.querySelector('[data-ndb-inspector-focus-detail]');
                const title = detail.querySelector('[data-ndb-timeline-detail-label]');
                const category = title.previousElementSibling;
                const facts = detail.querySelector('[data-ndb-inspector-facts]');
                const near = (actual, expected) => Math.abs(actual - expected) <= 1;

                return near(workspaceBox.left, stage.left)
                    && near(workspaceBox.right, stage.right)
                    && workspace.scrollWidth <= workspace.clientWidth
                    && detail.scrollWidth <= detail.clientWidth
                    && getComputedStyle(detail).containerType === 'inline-size'
                    && category.matches('p')
                    && near(category.getBoundingClientRect().left, title.getBoundingClientRect().left)
                    && category.getBoundingClientRect().bottom <= title.getBoundingClientRect().top
                    && facts.scrollWidth <= facts.clientWidth + 1
                    && [...facts.children].every((fact) => fact.scrollWidth <= fact.clientWidth + 1)
                    && document.querySelectorAll('[data-ndb-timeline-detail-content]').length === 1;
            })()
            JS)
        ->click('[data-ndb-inspector-focus-back]')
        ->assertVisible('[data-ndb-timeline-list]')
        ->assertNoJavaScriptErrors();
});

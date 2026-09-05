<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('presents logs as a persistent two column evidence inspector', function () {
    $page = visit('/profiled-logs')
        ->resize(1280, 720)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="logs"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-log-workspace]');

    $page
        ->assertCount('[data-ndb-log-entry]', 24)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-log-entry]'))
                .reduce((count, entry) => count + Number(entry.dataset.ndbLogRecordCount), 0)
            JS, 26)
        ->assertScript(<<<'JS'
            ['debug', 'info', 'notice', 'warning', 'error', 'critical'].every((level) =>
                document.querySelector(`[data-ndb-log-entry][data-ndb-log-level="${level}"]`)
                    && Array.from(document.querySelector('[data-ndb-log-level-select]').options)
                        .some((option) => option.value === level)
            )
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-log-workspace]');
                const [listPanel, detail] = workspace.children;
                const list = document.querySelector('[data-ndb-log-list]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const stage = document.querySelector('[data-ndb-section-stage]');
                const loadedSection = document.querySelector('[data-ndb-loaded-section="logs"]');
                const entries = Array.from(document.querySelectorAll('[data-ndb-log-entry]'));
                const sequences = entries.map((entry) => Number(entry.dataset.ndbLogFirstSequence));
                const controls = document.querySelector('[data-ndb-log-controls]');
                const summary = document.querySelector('[data-ndb-log-visible-summary]').getBoundingClientRect();
                const search = document.querySelector('[data-ndb-log-search]').getBoundingClientRect();
                const level = document.querySelector('[data-ndb-log-level-select]').getBoundingClientRect();
                const channel = document.querySelector('[data-ndb-log-channel-select]').getBoundingClientRect();
                const controlsBox = document.querySelector('[data-ndb-inspector-list-controls]').getBoundingClientRect();
                const checks = {
                    chronological: sequences.every((sequence, index) => index === 0 || sequence > sequences[index - 1]),
                    split: getComputedStyle(workspace).display === 'grid'
                        && getComputedStyle(listPanel).display === 'flex'
                        && getComputedStyle(detail).display === 'flex',
                    edgeToEdge: Math.abs(workspace.getBoundingClientRect().left - stage.getBoundingClientRect().left) <= 1
                        && Math.abs(workspace.getBoundingClientRect().right - stage.getBoundingClientRect().right) <= 1,
                    frame: getComputedStyle(workspace).borderTopWidth === '1px'
                        && getComputedStyle(workspace).borderRightWidth === '0px'
                        && getComputedStyle(workspace).borderBottomWidth === '0px'
                        && getComputedStyle(workspace).borderLeftWidth === '0px'
                        && getComputedStyle(workspace).borderRadius === '0px',
                    height: Math.abs(
                        workspace.getBoundingClientRect().bottom
                        - loadedSection.getBoundingClientRect().bottom
                        + Number.parseFloat(getComputedStyle(loadedSection).paddingBottom)
                    ) <= 1,
                    scrollOwners: getComputedStyle(list).overflowY === 'auto'
                        && getComputedStyle(detail).overflowY === 'auto'
                        && content.scrollHeight <= content.clientHeight + 2,
                    wholeRows: entries.every((entry) => entry.matches('button') && entry.querySelector('button') === null),
                    compactControls: Math.abs(search.left - controlsBox.left) <= 1
                        && Math.abs(search.right - controlsBox.right) <= 1
                        && summary.bottom <= search.top
                        && search.bottom <= level.top
                        && Math.abs(level.top - channel.top) <= 1
                        && level.right <= channel.left,
                    noSelection: entries.every((entry) => entry.getAttribute('aria-pressed') === 'false'),
                    noLegacyControls: document.querySelector('[data-ndb-log-order], [data-ndb-log-details-trigger], [data-ndb-log-details-popover]') === null,
                    fit: workspace.scrollWidth <= workspace.clientWidth + 1
                        && entries.every((entry) => entry.scrollWidth <= entry.clientWidth + 1),
                    noDecorativeSeparators: ! ['•', '·'].some((separator) => controls.textContent.includes(separator)),
                };
                const failures = Object.entries(checks).filter(([, passed]) => ! passed).map(([name]) => name);

                if (failures.length > 0) throw new Error('Logs layout failed: ' + failures.join(', '));

                return true;
            })()
            JS)
        ->assertMissing('[data-ndb-log-details-title]')
        ->assertSeeIn('[data-ndb-log-detail]', 'Choose a log entry to inspect its evidence.')
        ->assertScript(<<<'JS'
            (() => {
                const repeated = document.querySelector('[data-ndb-log-entry][data-ndb-log-record-count="3"]');
                const notice = document.querySelector('[data-ndb-log-entry][data-ndb-log-level="notice"]');
                const message = notice.querySelector('[data-ndb-log-message]');
                const severity = notice.querySelector('[data-ndb-log-severity]');
                const metadata = notice.querySelector('[data-ndb-log-metadata]');
                const single = document.querySelector('[data-ndb-log-entry][data-ndb-log-record-count="1"]');
                const entries = [...document.querySelectorAll('[data-ndb-log-entry]')];

                return repeated?.dataset.ndbLogLevel === 'warning'
                    && repeated.querySelector('[data-ndb-log-repeat-label]').textContent.includes('3 records')
                    && single.querySelector('[data-ndb-log-repeat-label]') === null
                    && repeated.querySelector('[data-ndb-log-message]').textContent.includes('needs attention')
                    && message.textContent.includes('\n')
                    && getComputedStyle(message).whiteSpace === 'pre-wrap'
                    && getComputedStyle(notice).gridTemplateColumns.split(' ').length === 2
                    && getComputedStyle(notice).alignItems === 'baseline'
                    && getComputedStyle(severity).lineHeight === getComputedStyle(message).lineHeight
                    && metadata.querySelector('[data-ndb-log-channel-label]')
                    && metadata.querySelector('[data-ndb-log-request-time]')
                    && entries.every((entry) => ! entry.textContent.includes('#'));
            })()
            JS)
        ->click('[data-ndb-log-entry][data-ndb-log-record-count="3"]')
        ->assertAttribute('[data-ndb-log-entry][data-ndb-log-record-count="3"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-log-details-title]')
        ->assertMissing('[data-ndb-log-detail] [data-ndb-log-occurrences]')
        ->click('[data-ndb-log-capture-details] > summary')
        ->assertCount('[data-ndb-log-detail] [data-ndb-log-occurrences] li', 3)
        ->assertPresent('[data-ndb-log-detail] [data-ndb-log-context]')
        ->assertPresent('[data-ndb-log-detail] [data-ndb-log-source]')
        ->assertMissing('[data-ndb-log-detail] [data-ndb-inspector-stack]')
        ->assertMissing('[data-ndb-log-detail] [data-ndb-log-actions]')
        ->assertMissing('[data-ndb-log-detail] [data-ndb-log-raw]')
        ->assertScript(<<<'JS'
            (() => {
                const selected = document.querySelector('[data-ndb-log-entry][aria-pressed="true"]');
                const unselected = document.querySelector('[data-ndb-log-entry][aria-pressed="false"]');
                const selectedStyle = getComputedStyle(selected);

                return selectedStyle.backgroundColor !== getComputedStyle(unselected).backgroundColor
                    && selectedStyle.boxShadow === 'none'
                    && selectedStyle.borderLeftWidth === '0px'
                    && document.querySelectorAll('[data-ndb-log-entry][aria-pressed="true"]').length === 1;
            })()
            JS)
        ->click('[data-ndb-log-entry][data-ndb-log-level="error"]')
        ->assertAttribute('[data-ndb-log-entry][data-ndb-log-level="error"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-ndb-log-entry][data-ndb-log-record-count="3"]', 'aria-pressed', 'false')
        ->assertVisible('[data-ndb-log-detail] [data-ndb-log-related-exception]')
        ->assertVisible('[data-ndb-log-detail] [data-ndb-log-review-exception]')
        ->assertPresent('[data-ndb-log-detail] [data-ndb-inspector-source-link]')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-log-detail]');
                const exception = detail.querySelector('[data-ndb-log-related-exception]');
                const message = exception.querySelector('p');
                const stackRows = detail.querySelectorAll('[data-ndb-inspector-stack] li');
                const facts = [...detail.querySelectorAll('[data-ndb-inspector-fact] dt')]
                    .map((label) => label.textContent.trim());
                const groups = [...detail.querySelectorAll('[data-ndb-log-detail-group]')]
                    .map((group) => group.dataset.ndbLogDetailGroup);

                return message.textContent.trim() === 'The rail partner rejected reservation KYO-441.'
                    && document.querySelector('[data-ndb-log-details-title]').textContent.startsWith('Rail reservation refresh failed.')
                    && stackRows.length === 0
                    && facts.join('|') === 'Channel|From request start'
                    && detail.querySelector('[data-ndb-log-detail-severity]').textContent.trim() === 'Error'
                    && ! detail.querySelector('[data-ndb-log-capture-details]').open
                    && groups.join('|') === 'summary|related-exception|context|capture|source'
                    && detail.scrollWidth <= detail.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-log-entry][data-ndb-log-level="critical"]')
        ->assertMissing('[data-ndb-log-context-full-value]')
        ->click('[data-ndb-log-context-value] > summary')
        ->assertSeeIn('[data-ndb-log-context-full-value]', 'Final retained line.')
        ->assertScript(<<<'JS'
            document.querySelector('[data-ndb-log-context-full-value]').textContent
                === 'Retained diagnostic context. '.repeat(12) + '\nFinal retained line.'
            JS)
        ->click('[data-ndb-log-entry][data-ndb-log-level="error"]')
        ->select('[data-ndb-log-level-select]', 'attention')
        ->assertAttribute('[data-ndb-log-entry][data-ndb-log-level="error"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-log-details-title]')
        ->assertCount('[data-ndb-log-entry]:not([hidden])', 3)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-log-entry]:not([hidden])'))
                .reduce((count, entry) => count + Number(entry.dataset.ndbLogRecordCount), 0)
            JS, 5)
        ->select('[data-ndb-log-channel-select]', 'newdebugbar-audit')
        ->assertCount('[data-ndb-log-entry]:not([hidden])', 0)
        ->assertMissing('[data-ndb-log-details-title]')
        ->assertSeeIn('[data-ndb-log-detail]', 'Choose a log entry to inspect its evidence.')
        ->select('[data-ndb-log-level-select]', 'all')
        ->assertCount('[data-ndb-log-entry]:not([hidden])', 1)
        ->assertScript('document.querySelector("[data-ndb-log-entry]:not([hidden])").dataset.ndbLogLevel === "info"')
        ->select('[data-ndb-log-channel-select]', 'all')
        ->fill('[data-ndb-log-search]', 'KYO-441')
        ->assertCount('[data-ndb-log-entry]:not([hidden])', 1)
        ->assertScript('document.querySelector("[data-ndb-log-entry]:not([hidden])").dataset.ndbLogLevel === "error"')
        ->assertNoJavaScriptErrors();

    DebugBarBrowser::assertSectionSelected($page, 'logs');
});

it('adapts the log list and details into a mobile drill in flow', function () {
    $page = visit('/profiled-logs')
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="sections"]')
        ->click('[data-ndb-select-section="logs"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-log-list]');

    $page
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-log-workspace]');
                const [listPanel, detail] = workspace.children;
                const controls = document.querySelector('[data-ndb-log-controls]');
                const entries = [...document.querySelectorAll('[data-ndb-log-entry]')];

                return content.scrollWidth <= content.clientWidth + 1
                    && controls.scrollWidth <= controls.clientWidth + 1
                    && getComputedStyle(listPanel).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && entries.every((entry) => entry.scrollWidth <= entry.clientWidth + 1);
            })()
            JS)
        ->keys('[data-ndb-log-entry][data-ndb-log-level="notice"]', 'Enter');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-log-details-title]');

    $page
        ->assertAttribute('[data-ndb-log-entry][data-ndb-log-level="notice"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-log-detail-back]')
        ->assertMissing('[data-ndb-log-detail] [data-ndb-log-context]')
        ->assertPresent('[data-ndb-log-detail] [data-ndb-log-source]')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-log-workspace]');
                const [listPanel, detail] = workspace.children;

                return getComputedStyle(listPanel).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && getComputedStyle(detail).overflowY === 'visible'
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && ['auto', 'scroll'].includes(getComputedStyle(content).overflowY)
                    && document.activeElement === detail;
            })()
            JS)
        ->click('[data-ndb-log-detail-back]')
        ->assertVisible('[data-ndb-log-list]');

    DebugBarBrowser::waitForFocus($page, '[data-ndb-log-entry][data-ndb-log-level="notice"]');

    $page
        ->assertAttribute('[data-ndb-log-entry][data-ndb-log-level="notice"]', 'aria-pressed', 'true')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="theme"] [data-ndb-mobile-theme-option="dark"]')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->fill('[data-ndb-log-search]', 'no-record-can-match-this-search')
        ->assertCount('[data-ndb-log-entry]:not([hidden])', 0)
        ->assertVisible('[data-ndb-log-filter-empty]')
        ->assertMissing('[data-ndb-log-details-title]')
        ->fill('[data-ndb-log-search]', '')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="shrink"]')
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]');

    DebugBarBrowser::assertSectionSelected($page, 'request');

    $page
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="sections"]')
        ->click('[data-ndb-select-section="logs"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-log-list]');
    DebugBarBrowser::assertSectionSelected($page, 'logs');

    $page
        ->assertCount('[data-ndb-log-entry]', 24)
        ->assertScript('document.querySelector("[data-ndb-log-level-select]").value === "all"')
        ->assertScript('document.querySelector("[data-ndb-log-channel-select]").value === "all"')
        ->assertScript('document.querySelectorAll("[data-ndb-log-entry][aria-pressed=true]").length', 0)
        ->assertNoJavaScriptErrors();
});

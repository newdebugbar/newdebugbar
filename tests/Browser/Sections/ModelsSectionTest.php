<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('presents model activity as a persistent two column inspector', function () {
    $page = visit('/profiled-models?sources=1')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="models"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-model-workspace]');

    $page
        ->assertSee('Review Eloquent retrievals, writes, repeated records, and application sources.')
        ->assertSee('Retrieved')
        ->assertSee('Writes')
        ->assertSee('Reloads')
        ->assertVisible('[data-ndb-model-search]')
        ->assertVisible('[data-ndb-model-summary]')
        ->assertAttribute('[data-ndb-model-group]:first-of-type', 'aria-pressed', 'false')
        ->assertAttribute('[data-ndb-model-group]:first-of-type', 'aria-controls', 'newdebugbar-model-detail')
        ->assertVisible('[data-ndb-model-detail-empty]')
        ->assertScript('document.querySelectorAll("[data-ndb-model-detail-panel]").length', 0)
        ->assertSeeIn('[data-ndb-model-detail-empty]', 'Select a model to inspect its activity.')
        ->assertMissing('[data-ndb-model-detail]')
        ->assertDontSee('Write evidence')
        ->assertMissing('[data-ndb-model-operation]')
        ->assertMissing('[data-ndb-model-view-queries]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-model-workspace]');
                const loadedSection = document.querySelector('[data-ndb-loaded-section="models"]');
                const stage = document.querySelector('[data-ndb-section-stage]');
                const sectionContent = document.querySelector('[data-ndb-section-content]');
                const [list, detail] = workspace.children;
                const content = document.querySelector('[data-ndb-inspector-content]');
                const heading = document.querySelector('[data-ndb-model-list-heading]');
                const row = document.querySelector('[data-ndb-model-group]');
                const modelList = document.querySelector('[data-ndb-model-list]');
                const summary = document.querySelector('[data-ndb-model-summary]');
                const search = document.querySelector('[data-ndb-model-search]');
                const controls = summary.closest('[data-ndb-inspector-list-controls]');
                const empty = document.querySelector('[data-ndb-model-detail-empty]');
                const prompt = empty.querySelector('p');
                const rowCells = [
                    row.querySelector('[data-ndb-model-name]').parentElement,
                    row.querySelector('[data-ndb-model-retrieved-column]'),
                    row.querySelector('[data-ndb-model-write-column]'),
                    row.querySelector('[data-ndb-model-extra-column]'),
                ];
                const headingCells = [...heading.children];

                const checks = {
                    grid: getComputedStyle(workspace).display === 'grid',
                    horizontalPadding: getComputedStyle(loadedSection).paddingLeft === '0px'
                        && getComputedStyle(loadedSection).paddingRight === '0px',
                    edgeToEdge: Math.abs(workspace.getBoundingClientRect().left - stage.getBoundingClientRect().left) <= 1
                        && Math.abs(workspace.getBoundingClientRect().right - stage.getBoundingClientRect().right) <= 1,
                    frame: getComputedStyle(workspace).borderTopWidth === '1px'
                        && getComputedStyle(workspace).borderRightWidth === '0px'
                        && getComputedStyle(workspace).borderBottomWidth === '0px'
                        && getComputedStyle(workspace).borderLeftWidth === '0px'
                        && getComputedStyle(workspace).borderRadius === '0px',
                    seam: Math.abs(list.getBoundingClientRect().right - detail.getBoundingClientRect().left) <= 1,
                    proportions: detail.getBoundingClientRect().width > list.getBoundingClientRect().width * 1.6,
                    panes: getComputedStyle(list).display === 'flex' && getComputedStyle(detail).display === 'flex',
                    heading: getComputedStyle(heading).display === 'grid' && headingCells.length === 4,
                    columns: rowCells.every((cell, index) =>
                        Math.abs(cell.getBoundingClientRect().left - headingCells[index].getBoundingClientRect().left) <= 1
                    ),
                    searchHeader: list.firstElementChild.contains(search)
                        && list.children[1] === modelList
                        && modelList.firstElementChild === heading,
                    summaryHeader: controls !== null
                        && controls === search.closest('[data-ndb-inspector-list-controls]')
                        && list.firstElementChild.contains(summary)
                        && ! modelList.contains(summary)
                        && summary.getBoundingClientRect().bottom < search.getBoundingClientRect().top
                        && document.querySelector('[aria-label="Model activity totals"]') === null,
                    fullWidthControls: getComputedStyle(controls).gridTemplateColumns.trim().split(/\s+/).length === 1
                        && [...controls.children].every((child) =>
                            Math.abs(child.getBoundingClientRect().left - controls.getBoundingClientRect().left) <= 1
                                && Math.abs(child.getBoundingClientRect().right - controls.getBoundingClientRect().right) <= 1
                        ),
                    summaryCount: summary.querySelector('[data-ndb-model-summary-count]').textContent.trim() === '5 models'
                        && getComputedStyle(summary.querySelector('[data-ndb-model-visible-count]').parentElement).display === 'none',
                    fullHeight: getComputedStyle(content).display === 'flex'
                        && getComputedStyle(stage).display === 'flex'
                        && getComputedStyle(sectionContent).display === 'flex'
                        && Math.abs(
                            workspace.getBoundingClientRect().bottom
                            - loadedSection.getBoundingClientRect().bottom
                            + Number.parseFloat(getComputedStyle(loadedSection).paddingBottom)
                        ) <= 1,
                    emptyFill: empty.getBoundingClientRect().width === detail.getBoundingClientRect().width
                        && empty.getBoundingClientRect().height === detail.getBoundingClientRect().height,
                    emptyCenteredX: Math.abs(
                        prompt.getBoundingClientRect().left + prompt.getBoundingClientRect().width / 2
                        - detail.getBoundingClientRect().left - detail.getBoundingClientRect().width / 2
                    ) <= 1,
                    emptyCenteredY: Math.abs(
                        prompt.getBoundingClientRect().top + prompt.getBoundingClientRect().height / 2
                        - detail.getBoundingClientRect().top - detail.getBoundingClientRect().height / 2
                    ) <= 1,
                    desktopBack: getComputedStyle(document.querySelector('[data-ndb-model-detail-back]')).display === 'none',
                    scrollOwners: getComputedStyle(list.querySelector('[data-ndb-model-list]')).overflowY === 'auto'
                        && getComputedStyle(detail).overflowY === 'auto',
                    focusTarget: detail.tabIndex === 0,
                    contentFit: content.scrollHeight <= content.clientHeight + 2,
                    horizontalFit: workspace.scrollWidth <= workspace.clientWidth + 1,
                };
                const failures = Object.entries(checks).filter(([, passed]) => ! passed).map(([name]) => name);

                if (failures.length > 0) throw new Error('Models layout failed: ' + failures.join(', '));

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const headings = [...document.querySelectorAll('[data-ndb-model-sort-heading]')];
                const model = document.querySelector('[data-ndb-model-sort-heading="model"]');
                const label = model.querySelector('span[aria-hidden="true"]:not([data-ndb-sort-indicator])');
                model.dataset.ndbTestLabelLeft = String(label.getBoundingClientRect().left);

                return headings.length === 4
                    && headings.map((heading) => heading.querySelector('span[aria-hidden="true"]:not([data-ndb-sort-indicator])').textContent.trim()).join('|')
                        === 'Model|Retrieved|Writes|Reloads'
                    && headings.every((heading) => heading.getAttribute('aria-pressed') === 'false');
            })()
            JS)
        ->click('[data-ndb-model-sort-heading="model"]')
        ->assertAttribute('[data-ndb-model-sort-heading="model"]', 'aria-pressed', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const heading = document.querySelector('[data-ndb-model-sort-heading="model"]');
                const label = heading.querySelector('span[aria-hidden="true"]:not([data-ndb-sort-indicator])');
                const names = [...document.querySelectorAll('[data-ndb-model-group]')]
                    .map((row) => row.dataset.ndbModelShortName);

                return Math.abs(label.getBoundingClientRect().left - Number(heading.dataset.ndbTestLabelLeft)) <= 1
                    && names.every((name, index) => index === 0
                        || names[index - 1].localeCompare(name, undefined, { numeric: true, sensitivity: 'base' }) <= 0);
            })()
            JS)
        ->click('[data-ndb-model-sort-heading="model"]')
        ->assertScript(<<<'JS'
            (() => {
                const names = [...document.querySelectorAll('[data-ndb-model-group]')]
                    .map((row) => row.dataset.ndbModelShortName);

                return names.every((name, index) => index === 0
                    || names[index - 1].localeCompare(name, undefined, { numeric: true, sensitivity: 'base' }) >= 0);
            })()
            JS)
        ->click('[data-ndb-model-sort-heading="model"]')
        ->assertAttribute('[data-ndb-model-sort-heading="model"]', 'aria-pressed', 'false')
        ->assertScript(<<<'JS'
            JSON.stringify([...document.querySelectorAll('[data-ndb-model-group]')]
                .map((row) => Number(row.dataset.ndbModelIndex))) === JSON.stringify([0, 1, 2, 3, 4])
            JS)
        ->click('[data-ndb-model-sort-heading="retrieved"]')
        ->assertAttribute('[data-ndb-model-sort-heading="retrieved"]', 'aria-pressed', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const values = [...document.querySelectorAll('[data-ndb-model-group]')]
                    .map((row) => Number(row.dataset.ndbModelSortRetrieved));

                return values.every((value, index) => index === 0 || values[index - 1] >= value);
            })()
            JS)
        ->click('[data-ndb-model-sort-heading="retrieved"]')
        ->assertScript(<<<'JS'
            (() => {
                const values = [...document.querySelectorAll('[data-ndb-model-group]')]
                    .map((row) => Number(row.dataset.ndbModelSortRetrieved));

                return values.every((value, index) => index === 0 || values[index - 1] <= value);
            })()
            JS)
        ->click('[data-ndb-model-sort-heading="retrieved"]')
        ->assertAttribute('[data-ndb-model-sort-heading="retrieved"]', 'aria-pressed', 'false')
        ->fill('[data-ndb-model-search]', 'ProofVersion')
        ->assertScript('document.querySelectorAll("[data-ndb-model-group]:not([hidden])").length', 1)
        ->assertScript(<<<'JS'
            (() => {
                const summary = document.querySelector('[data-ndb-model-summary]');
                const visible = summary.querySelector('[data-ndb-model-visible-count]');

                return summary.querySelector('[data-ndb-model-summary-count]').textContent.trim() === '5 models'
                    && visible.textContent.trim() === '1'
                    && getComputedStyle(visible.parentElement).display !== 'none'
                    && summary.textContent.includes('shown');
            })()
            JS)
        ->fill('[data-ndb-model-search]', '')
        ->assertScript('document.querySelectorAll("[data-ndb-model-group]:not([hidden])").length', 5)
        ->click('[data-ndb-model-group]:first-of-type')
        ->assertAttribute('[data-ndb-model-group]:first-of-type', 'aria-pressed', 'true')
        ->assertAttribute('[data-ndb-model-detail-tab="records"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-model-detail-panel="records"]')
        ->assertScript('document.querySelectorAll("[data-ndb-model-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-model-detail-panel="source"]')
        ->assertMissing('[data-ndb-model-detail-tab="overview"]')
        ->assertMissing('[data-ndb-model-detail-panel="overview"]')
        ->assertSee('NewDebugBar\Tests\Fixtures\Models\StudioJob')
        ->assertDontSee('Write events')
        ->assertMissing('[data-ndb-model-write-table]')
        ->assertMissing('[data-ndb-model-record-sources]')
        ->assertDontSee('were observed for already identified records')
        ->assertMissing('[data-ndb-model-guidance]')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-model-detail-pane]');
                const tabs = [...document.querySelectorAll('[data-ndb-model-detail-tab]')];
                const tabGroup = tabs[0].closest('[data-ndb-filter-tabs]');
                const listMetadata = document.querySelector('[data-ndb-model-name] + span');
                const detailMetadata = [...document.querySelectorAll('[data-ndb-model-header] dd')];
                const metadataList = detailMetadata[0].closest('dl');
                const modelClass = document.querySelector('[data-ndb-model-class]');

                const headerText = document.querySelector('[data-ndb-model-header]').textContent;

                return tabs.map((tab) => tab.textContent.trim()).join('|') === 'Records|Source'
                    && tabs.every((tab) => tab.dataset.ndbFilterTabVariant === 'segmented')
                    && tabGroup.dataset.ndbFilterTabsVariant === 'segmented'
                    && ! getComputedStyle(listMetadata).fontFamily.includes('JetBrains Mono')
                    && ! getComputedStyle(modelClass).fontFamily.includes('JetBrains Mono')
                    && detailMetadata.every((value) => ! getComputedStyle(value).fontFamily.includes('JetBrains Mono'))
                    && Number.parseFloat(getComputedStyle(metadataList).columnGap) >= 32
                    && ! headerText.includes('Writes')
                    && ! headerText.includes('Logical writes')
                    && ! ['Retrieved', 'Extra retrievals', 'Identified records', 'First', 'Last', 'Source']
                        .some((label) => headerText.includes(label))
                    && Math.abs(
                        tabGroup.getBoundingClientRect().left + tabGroup.getBoundingClientRect().width / 2
                        - detail.getBoundingClientRect().left - detail.getBoundingClientRect().width / 2
                    ) <= 1;
            })()
            JS)
        ->click('[data-ndb-model-group][data-ndb-model-short-name="ProofVersion"]')
        ->assertAttribute('[data-ndb-model-group][data-ndb-model-short-name="ProofVersion"]', 'aria-pressed', 'true')
        ->assertSee('NewDebugBar\Tests\Fixtures\Models\ProofVersion')
        ->assertAttribute('[data-ndb-model-detail-tab="records"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-model-detail-panel="records"]')
        ->assertScript('document.querySelectorAll("[data-ndb-model-record]").length', 5)
        ->assertScript('document.querySelector("[data-ndb-model-record] p").textContent.trim()', '2')
        ->assertSeeIn('[data-ndb-model-records]', 'How this model was loaded')
        ->assertSeeIn('[data-ndb-model-records]', 'Retrieved counts each load of a record.')
        ->assertSeeIn('[data-ndb-model-records]', 'If it exceeds 1, check whether the repeated loads are expected.')
        ->assertScript(<<<'JS'
            [...document.querySelector('[data-ndb-model-records] > div:nth-child(2) > div:first-child').children]
                .map((heading) => heading.textContent.trim()).join('|') === 'Identifier|Retrieved|Source'
            JS)
        ->assertDontSee('Retrieved records')
        ->assertDontSee('Repeated identifiers reveal records')
        ->assertMissing('[data-ndb-model-extra-guidance]')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-model-record]").parentElement.parentElement).marginTop', '12px')
        ->assertScript(<<<'JS'
            (() => {
                window.newdebugbarModelClipboard = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarModelClipboard.push(value),
                    },
                });

                const link = document.querySelector('[data-ndb-model-record] [data-ndb-inspector-source-link]');
                const interfaceFont = getComputedStyle(document.querySelector('[data-ndb-model-workspace]')).fontFamily;
                window.newdebugbarModelRecordSource = link?.title;

                return link !== null
                    && getComputedStyle(link).fontFamily === interfaceFont
                    && getComputedStyle(link).textDecorationLine.includes('underline')
                    && link.querySelector('svg') === null;
            })()
            JS)
        ->click('[data-ndb-model-record]:first-child [data-ndb-inspector-source-link]')
        ->assertMissing('[data-ndb-model-record-sources] li')
        ->click('[data-ndb-model-record]:first-child [data-ndb-model-record-sources] > summary')
        ->assertCount('[data-ndb-model-record]:first-child [data-ndb-model-record-sources] li', 2)
        ->assertScript(<<<'JS'
            (() => {
                const record = document.querySelector('[data-ndb-model-record]');
                const expanded = record.querySelector('[data-ndb-model-record-sources]');
                const source = expanded.querySelector('[data-ndb-inspector-source-link]');

                return expanded.open
                    && source.textContent.trim() === window.newdebugbarModelRecordSource
                    && record.scrollWidth <= record.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-model-detail-tab="source"]')
        ->assertVisible('[data-ndb-model-detail-panel="source"]')
        ->assertScript('document.querySelectorAll("[data-ndb-model-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-model-detail-panel="records"]')
        ->assertVisible('[data-ndb-model-source]:first-of-type')
        ->click('[data-ndb-model-source]:first-of-type [data-ndb-model-source-path="application"]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            (() => {
                const sourceLink = document.querySelector('[data-ndb-model-source-path="application"]');
                const [recordSource, applicationSource] = window.newdebugbarModelClipboard;

                return window.newdebugbarModelClipboard.length === 2
                    && recordSource === window.newdebugbarModelRecordSource
                    && applicationSource === sourceLink.textContent.trim();
            })()
            JS)
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertVisible('[data-ndb-model-workspace]')
        ->assertAttribute('[data-ndb-model-group][data-ndb-model-short-name="ProofVersion"]', 'aria-pressed', 'true')
        ->refresh()
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="models"]')
        ->assertVisible('[data-ndb-model-list]')
        ->assertAttribute('[data-ndb-model-group]:first-of-type', 'aria-pressed', 'false')
        ->assertVisible('[data-ndb-model-detail-empty]')
        ->assertNoJavaScriptErrors();
});

it('adapts the model list and details into a mobile drill in flow', function () {
    $page = visit('/profiled-models')
        ->resize(390, 844)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]')
        ->click('[data-ndb-header-mobile-trigger="actions"]')
        ->click('[data-ndb-header-mobile-action="palette"]')
        ->click('[data-ndb-command="section:models"]')
        ->assertVisible('[data-ndb-model-list]')
        ->assertScript(<<<'JS'
            (() => {
                const list = document.querySelector('[data-ndb-model-list]');
                const summary = document.querySelector('[data-ndb-model-summary]');
                const controls = summary.closest('[data-ndb-inspector-list-controls]');

                return controls !== null
                    && controls.contains(document.querySelector('[data-ndb-model-search]'))
                    && ! list.contains(summary)
                    && summary.querySelector('[data-ndb-model-summary-count]').textContent.trim() === '5 models'
                    && getComputedStyle(summary.querySelector('[data-ndb-model-visible-count]').parentElement).display === 'none';
            })()
            JS)
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-model-detail-pane]")).display === "none"')
        ->keys('[data-ndb-model-group][data-ndb-model-short-name="ProofVersion"]', 'Enter');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-model-detail-pane]');

    $page
        ->assertVisible('[data-ndb-model-detail-back]')
        ->assertSee('NewDebugBar\Tests\Fixtures\Models\ProofVersion')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const panel = document.querySelector('[data-ndb-section-panel="models"]');
                const models = document.querySelector('[data-ndb-models]');
                const heading = document.querySelector('[data-ndb-model-list-heading]');
                const rows = [...document.querySelectorAll('[data-ndb-model-group]')];
                const listPanel = document.querySelector('[data-ndb-model-workspace]').children[0];
                const detail = document.querySelector('[data-ndb-model-detail-pane]');

                return panel.getBoundingClientRect().width <= content.clientWidth + 1
                    && content.scrollWidth <= content.clientWidth + 1
                    && ['auto', 'scroll'].includes(getComputedStyle(content).overflowY)
                    && getComputedStyle(models).overflowY === 'visible'
                    && getComputedStyle(heading).display === 'none'
                    && getComputedStyle(listPanel).display === 'none'
                    && rows.every((row) => row.getClientRects().length === 0)
                    && getComputedStyle(detail).display === 'flex'
                    && getComputedStyle(detail).overflowY === 'visible'
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && document.activeElement === detail;
            })()
            JS)
        ->click('[data-ndb-model-detail-tab="records"]')
        ->assertVisible('[data-ndb-model-detail-panel="records"]')
        ->assertScript('document.querySelectorAll("[data-ndb-model-record]").length', 5)
        ->click('[data-ndb-model-detail-back]')
        ->assertVisible('[data-ndb-model-list]');

    DebugBarBrowser::waitForFocus(
        $page,
        '[data-ndb-model-group][data-ndb-model-short-name="ProofVersion"]',
    );

    $page
        ->assertAttribute('[data-ndb-model-group][data-ndb-model-short-name="ProofVersion"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

it('summarizes writes and shows completed operations without lifecycle noise', function () {
    visit('/profiled-models?changes=1')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="models"]')
        ->assertScript('document.querySelector("[data-ndb-select-section=models]").textContent.trim().endsWith("48")')
        ->assertScript(<<<'JS'
            JSON.stringify([...document.querySelectorAll('[data-ndb-model-group]')]
                .map((group) => [
                    group.dataset.ndbModelShortName,
                    group.querySelector('[data-ndb-model-write-column]').textContent.trim(),
                ]))
                === JSON.stringify([
                    ['Client', '1'],
                    ['ProofVersion', '1'],
                    ['User', '1'],
                    ['JobActivity', '1'],
                    ['StudioJob', '0'],
                ])
            JS)
        ->click('[data-ndb-model-group][data-ndb-model-short-name="Client"]')
        ->assertSee('NewDebugBar\Tests\Fixtures\Models\Client')
        ->assertDontSee('Write events')
        ->assertVisible('[data-ndb-model-write-table]')
        ->assertVisible('[data-ndb-model-write-operation]')
        ->assertSeeIn('[data-ndb-model-write-table]', 'How this model changed')
        ->assertSeeIn('[data-ndb-model-write-table]', 'Each row is one completed write.')
        ->assertSeeIn('[data-ndb-model-write-table]', 'If a write is unexpected, inspect its changed fields and application source.')
        ->assertSeeIn('[data-ndb-model-write-operation]', 'Updated')
        ->assertDontSeeIn('[data-ndb-model-write-operation]', 'Updating')
        ->assertDontSeeIn('[data-ndb-model-write-operation]', 'Saved')
        ->assertDontSeeIn('[data-ndb-model-write-table]', 'Time')
        ->assertScript(<<<'JS'
            (() => {
                window.newdebugbarModelWriteClipboard = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarModelWriteClipboard.push(value),
                    },
                });

                const header = document.querySelector('[data-ndb-model-header]');
                const table = document.querySelector('[data-ndb-model-write-table]');
                const heading = [...table.querySelector('div > div:first-child').children];
                const tableGrid = heading[0].parentElement.parentElement;
                const rows = [...table.querySelectorAll('[data-ndb-model-write-operation]')];
                const cells = [...rows[0].children].filter((cell) => ! cell.matches('[data-ndb-model-write-details]'));
                const source = cells[2].matches('[data-ndb-inspector-source-link]')
                    ? cells[2]
                    : cells[2].querySelector('[data-ndb-inspector-source-link]');

                return ! header.textContent.includes('Writes')
                    && table.closest('[data-ndb-model-detail-panel="records"]') !== null
                    && getComputedStyle(tableGrid).marginTop === '12px'
                    && heading.map((cell) => cell.textContent.trim()).join('|') === 'Operation|Record|Source'
                    && rows.length === 1
                    && cells.length === 3
                    && cells[0].innerText.trim() === 'Updated'
                    && cells[1].innerText.trim() === '4'
                    && source !== null
                    && getComputedStyle(source).textDecorationLine.includes('underline')
                    && source.querySelector('svg') === null;
            })()
            JS)
        ->click('[data-ndb-model-write-operation] [data-ndb-inspector-source-link]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            (() => {
                const source = document.querySelector('[data-ndb-model-write-operation] [data-ndb-inspector-source-link]');

                return window.newdebugbarModelWriteClipboard.length === 1
                    && window.newdebugbarModelWriteClipboard[0] === source.title;
            })()
            JS)
        ->assertDontSee('Write evidence')
        ->assertDontSee('Changed attributes')
        ->assertMissing('[data-ndb-model-changed-fields]')
        ->click('[data-ndb-model-write-details] > summary')
        ->assertSeeIn('[data-ndb-model-changed-fields]', 'status')
        ->assertSeeIn('[data-ndb-model-changed-fields]', 'approved')
        ->assertSeeIn('[data-ndb-model-changed-fields]', '[redacted]')
        ->assertVisible('[data-ndb-model-write-time]')
        ->assertDontSee('Before')
        ->click('[data-ndb-model-write-details] > summary')
        ->assertMissing('[data-ndb-model-changed-fields]')
        ->assertDontSee('private-token')
        ->assertDontSee('updated-private-token')
        ->assertMissing('[data-ndb-model-operation]')
        ->assertMissing('[data-ndb-model-operation-changes]')
        ->assertNoJavaScriptErrors();
});

it('bounds record tables and explains unavailable identifiers', function () {
    visit('/profiled-models?large=1&missing=1')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="models"]')
        ->click('[data-ndb-model-group][data-ndb-model-short-name="JobActivity"]')
        ->click('[data-ndb-model-detail-tab="records"]')
        ->assertScript('document.querySelectorAll("[data-ndb-model-record]").length', 25)
        ->assertSeeIn('[data-ndb-model-record-limit]', 'Showing 25 of 40 identified records.')
        ->click('[data-ndb-model-group][data-ndb-model-short-name="ProfiledModel"]')
        ->click('[data-ndb-model-detail-tab="records"]')
        ->assertVisible('[data-ndb-model-missing-identifiers]')
        ->assertSee('A dash means the model identifier was unavailable.')
        ->assertSee('These retrievals are excluded from the reload count.')
        ->assertNoJavaScriptErrors();
});

it('shows application sources without related query controls', function () {
    visit('/profiled-models?queries=1')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="models"]')
        ->click('[data-ndb-model-group][data-ndb-model-short-name="JobActivity"]')
        ->click('[data-ndb-model-detail-tab="source"]')
        ->assertVisible('[data-ndb-model-detail-panel="source"]')
        ->assertVisible('[data-ndb-model-source]:first-of-type')
        ->assertDontSee('Start with locations responsible for the most model activity.')
        ->assertMissing('[data-ndb-model-sources] [data-ndb-inspector-explanation]')
        ->assertScript(<<<'JS'
            (() => {
                const sources = document.querySelector('[data-ndb-model-sources]');
                const firstSource = document.querySelector('[data-ndb-model-source]');
                const sourceList = document.querySelector('[data-ndb-model-source-list]');
                const heading = document.querySelector('[data-ndb-model-source-heading]');

                return sources.firstElementChild === sourceList
                    && [...heading.children].map((cell) => cell.textContent.trim()).join('|') === 'Source|Activity'
                    && firstSource.parentElement.parentElement === sourceList
                    && getComputedStyle(sourceList).marginTop === '0px';
            })()
            JS)
        ->assertDontSee('Related queries')
        ->assertMissing('[data-ndb-model-query-guidance]')
        ->assertMissing('[data-ndb-model-query-evidence]')
        ->assertMissing('[data-ndb-model-view-queries]')
        ->assertNoJavaScriptErrors();
});

it('shows original Blade and compiled source paths in normal interface type', function () {
    visit('/profiled-models?compiled=1')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="models"]')
        ->click('[data-ndb-model-group][data-ndb-model-short-name="JobActivity"]')
        ->click('[data-ndb-model-detail-tab="source"]')
        ->assertVisible('[data-ndb-model-compiled-source]')
        ->assertSee('Blade template')
        ->assertSee('tests/Fixtures/views/model-compiled.blade.php')
        ->assertSee('Compiled location')
        ->assertSee('storage/framework/views/')
        ->assertScript(<<<'JS'
            (() => {
                window.newdebugbarModelCompiledClipboard = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarModelCompiledClipboard.push(value),
                    },
                });

                const source = document.querySelector('[data-ndb-model-compiled-source]').closest('[data-ndb-model-source]');
                const paths = [...source.querySelectorAll('[data-ndb-model-source-path]')];
                const template = source.querySelector('[data-ndb-model-source-path="template"]');
                const compiled = source.querySelector('[data-ndb-model-source-path="compiled"]');

                return source.scrollWidth <= source.clientWidth + 1
                    && paths.length === 2
                    && paths.every((path) => !getComputedStyle(path).fontFamily.includes('JetBrains Mono'))
                    && template.matches('[data-ndb-inspector-source-link]')
                    && getComputedStyle(template).textDecorationLine.includes('underline')
                    && compiled.matches('p')
                    && ! compiled.matches('[data-ndb-inspector-source-link]')
                    && source.querySelector('[data-ndb-model-view-queries]') === null;
            })()
            JS)
        ->click('[data-ndb-model-source-path="template"]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            window.newdebugbarModelCompiledClipboard.length === 1
                && window.newdebugbarModelCompiledClipboard[0] === 'tests/Fixtures/views/model-compiled.blade.php'
            JS)
        ->assertNoJavaScriptErrors();
});

it('shows a useful empty model state', function () {
    $page = visit('/profiled-models-empty')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::selectSectionViaPalette($page, 'models');

    $page
        ->assertVisible('[data-ndb-section-panel="models"]')
        ->assertSee('No Eloquent model activity was captured for this request.')
        ->assertMissing('[data-ndb-model-summary]')
        ->assertMissing('[data-ndb-model-group]')
        ->assertNoJavaScriptErrors();
});

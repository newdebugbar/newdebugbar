<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('shows delayed mail without rendering it before a worker runs', function () {
    visit('/profiled-queued-communications')
        ->resize(1200, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="mail"]')
        ->waitForText('ProfiledMailable')
        ->assertSee('Delayed')
        ->assertSee('30 s delay')
        ->assertSee('The preview is created when the worker sends this message.')
        ->assertAttribute('[data-ndb-mail-item="1"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-mail-preview-frame]") === null')
        ->assertScript(
            'getComputedStyle(document.querySelector("[data-ndb-mail-related-profile]")).display === "none"',
        )
        ->assertNoJavaScriptErrors();
});

it('selects and inspects mail with a real in-panel preview', function () {
    $page = visit('/profiled-mail-rich')
        ->resize(1440, 1200)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="mail"]')
        ->waitForText('Payment receipt #NS-1042')
        ->assertSee('3 messages')
        ->assertSee('+5 more')
        ->assertValue('[data-ndb-mail-filter]', 'all')
        ->assertAttribute('[data-ndb-mail-detail-tab="preview"]', 'aria-pressed', 'true')
        ->assertValue('[data-ndb-mail-preview-format]', 'html')
        ->assertAttribute('[data-ndb-mail-preview-viewport="desktop"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-ndb-mail-actions-trigger]', 'aria-haspopup', 'menu')
        ->assertAttribute('[data-ndb-mail-actions-trigger]', 'aria-label', 'Mail actions')
        ->assertAttribute('[data-ndb-mail-actions-trigger]', 'aria-expanded', 'false')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-mail-workspace]');
                const loadedSection = document.querySelector('[data-ndb-loaded-section="mail"]');
                const stage = document.querySelector('[data-ndb-section-stage]');
                const [list, detail] = workspace.children;
                const content = document.querySelector('[data-ndb-inspector-content]');
                const details = workspace.parentElement.parentElement.parentElement;
                const workspaceBox = workspace.getBoundingClientRect();
                const contentBox = content.getBoundingClientRect();
                const expectedBottom = contentBox.bottom - Number.parseFloat(getComputedStyle(details).paddingBottom);
                const listBox = list.getBoundingClientRect();
                const detailBox = detail.getBoundingClientRect();
                const selected = document.querySelector('[data-ndb-mail-item][aria-pressed="true"]');
                const listTitle = selected.querySelector('[data-ndb-mail-list-title]');
                const listStatus = selected.querySelector('[data-ndb-mail-list-status]');
                const listRecipient = selected.querySelector('[data-ndb-mail-list-recipient]');
                const listActivity = selected.querySelector('[data-ndb-mail-list-activity]');
                const tabs = document.querySelector('[data-ndb-mail-detail-tabs] [data-ndb-filter-tabs]');
                const frame = document.querySelector('[data-ndb-mail-preview-frame]');
                const viewportButtons = [...document.querySelectorAll('[data-ndb-mail-preview-viewport]')];
                const viewportControl = document.querySelector('[data-ndb-mail-preview-viewport-control]');
                const formatControl = document.querySelector('[data-ndb-mail-preview-format]');
                const previewControls = document.querySelector('[data-ndb-mail-preview-controls]');
                const previewSurface = document.querySelector('[data-ndb-mail-preview-surface]');
                const previewCanvas = document.querySelector('[data-ndb-mail-preview-canvas]');
                const summary = document.querySelector('[data-ndb-mail-summary]');
                const summaryCount = document.querySelector('[data-ndb-mail-summary-count]');
                const summaryRuntime = document.querySelector('[data-ndb-mail-summary-runtime]');
                const filter = document.querySelector('[data-ndb-mail-filter]');
                const listControls = document.querySelector('[data-ndb-inspector-list-controls]');
                const header = detail.querySelector('header');
                const actions = header.querySelector('[data-ndb-mail-actions]');
                const visibleHeaderActions = [...actions.parentElement.children]
                    .filter((element) => getComputedStyle(element).display !== 'none');
                const primary = header.querySelector('[data-ndb-inspector-detail-header-primary]');
                const subject = header.querySelector('[data-ndb-mail-detail-subject]');
                const identity = header.querySelector('[data-ndb-mail-recipient]');
                const metadata = header.querySelector('[data-ndb-mail-metadata]');
                const metadataGrid = metadata.querySelector('[data-ndb-mail-facts]');
                const metadataFacts = [...metadata.querySelectorAll('[data-ndb-mail-fact]')];
                const metadataLabels = metadataFacts.map((fact) => fact.querySelector('dt').textContent.trim());
                const sourceLink = metadata.querySelector('[data-ndb-inspector-source-link]');
                const addressGroups = [...identity.querySelector('dl').children];
                const headerTop = header.getBoundingClientRect().top;
                const availableScroll = Math.max(0, detail.scrollHeight - detail.clientHeight);

                detail.scrollTop = 96;
                const headerScrollDistance = headerTop - header.getBoundingClientRect().top;
                detail.scrollTop = 0;

                return getComputedStyle(workspace).display === 'grid'
                    && workspaceBox.height > 576
                    && Math.abs(workspaceBox.bottom - expectedBottom) <= 1
                    && getComputedStyle(loadedSection).paddingLeft === '0px'
                    && getComputedStyle(loadedSection).paddingRight === '0px'
                    && Math.abs(workspaceBox.left - stage.getBoundingClientRect().left) <= 1
                    && Math.abs(workspaceBox.right - stage.getBoundingClientRect().right) <= 1
                    && getComputedStyle(workspace).borderTopWidth === '1px'
                    && getComputedStyle(workspace).borderRightWidth === '0px'
                    && getComputedStyle(workspace).borderBottomWidth === '0px'
                    && getComputedStyle(workspace).borderLeftWidth === '0px'
                    && getComputedStyle(workspace).borderRadius === '0px'
                    && detailBox.width > listBox.width * 1.6
                    && Math.abs(listBox.top - detailBox.top) <= 1
                    && selected.dataset.ndbMailItem === '1'
                    && getComputedStyle(selected).borderLeftWidth === '0px'
                    && selected.children.length === 4
                    && selected.getBoundingClientRect().height <= 68
                    && Math.abs(listTitle.getBoundingClientRect().left - listRecipient.getBoundingClientRect().left) <= 1
                    && Math.abs(listStatus.getBoundingClientRect().right - listActivity.getBoundingClientRect().right) <= 1
                    && getComputedStyle(listStatus).backgroundColor === 'rgba(0, 0, 0, 0)'
                    && getComputedStyle(listStatus).borderWidth === '0px'
                    && tabs.dataset.ndbFilterTabsVariant === 'segmented'
                    && [...tabs.querySelectorAll('[data-ndb-mail-detail-tab]')]
                        .every((tab) => tab.dataset.ndbFilterTabVariant === 'segmented')
                    && viewportButtons.length === 2
                    && viewportButtons.every((button) => button.querySelector('svg'))
                    && viewportButtons.every((button) => button.querySelector('svg').getBoundingClientRect().width <= 12.5)
                    && formatControl.getBoundingClientRect().left > viewportControl.getBoundingClientRect().right
                    && tabs.parentElement === previewControls.parentElement
                    && Math.abs(
                        tabs.getBoundingClientRect().left
                        - tabs.parentElement.getBoundingClientRect().left
                        - Number.parseFloat(getComputedStyle(tabs.parentElement).paddingLeft)
                    ) <= 1
                    && actions.open === false
                    && visibleHeaderActions.length === 1
                    && visibleHeaderActions[0] === actions
                    && subject.closest('[data-ndb-inspector-detail-header-primary]') === primary
                    && header.querySelector('[data-ndb-mail-status]') === null
                    && metadataFacts.length === 4
                    && metadataFacts.every((fact) => fact.hasAttribute('data-ndb-inspector-fact'))
                    && metadataLabels.join('|') === 'Attachments|Duration|Driver|Source'
                    && getComputedStyle(metadataGrid).display === 'grid'
                    && getComputedStyle(metadataGrid).borderTopWidth === '0px'
                    && ! header.textContent.includes('Sent')
                    && sourceLink.querySelector('svg') === null
                    && getComputedStyle(sourceLink).paddingLeft === '0px'
                    && getComputedStyle(sourceLink).textDecorationLine.includes('underline')
                    && metadata.querySelectorAll('svg').length === 0
                    && header.querySelector('[data-ndb-mail-actions-trigger]').textContent.trim() === ''
                    && identity.textContent.includes('Recipients')
                    && identity.textContent.includes('Sender')
                    && identity.textContent.includes('+5 more')
                    && identity.scrollWidth <= identity.clientWidth + 1
                    && metadata.scrollWidth <= metadata.clientWidth + 1
                    && /^Recipients\s/.test(addressGroups[0].textContent.trim())
                    && /^Sender\s/.test(addressGroups[1].textContent.trim())
                    && ! identity.textContent.includes('→')
                    && listControls.contains(summary)
                    && listControls.contains(filter)
                    && summary.getBoundingClientRect().left < filter.getBoundingClientRect().left
                    && summaryRuntime.getBoundingClientRect().top > summaryCount.getBoundingClientRect().top
                    && detail.scrollHeight >= detail.clientHeight
                    && getComputedStyle(detail).overflowY === 'auto'
                    && detail.tabIndex === 0
                    && getComputedStyle(previewSurface).overflowY === 'visible'
                    && Math.abs(headerScrollDistance - Math.min(96, availableScroll)) <= 1
                    && frame.getAttribute('sandbox') === 'allow-scripts'
                    && frame.getAttribute('src').endsWith('/0/html')
                    && frame.offsetWidth === 1024
                    && frame.getBoundingClientRect().width <= previewCanvas.clientWidth + 1
                    && Math.abs(frame.getBoundingClientRect().height - previewCanvas.getBoundingClientRect().height) <= 1
                    && frame.getBoundingClientRect().width > 500
                    && frame.clientHeight > 320
                    && !['•', '·'].some((separator) => document.querySelector('[data-ndb-mail]').textContent.includes(separator));
            })()
            JS)
        ->click('[data-ndb-mail-actions-trigger]')
        ->assertAttribute('[data-ndb-mail-actions-trigger]', 'aria-expanded', 'true')
        ->assertVisible('[data-ndb-mail-actions-menu]')
        ->assertScript(<<<'JS'
            (() => {
                const actions = document.querySelector('[data-ndb-mail-actions]');
                const menu = document.querySelector('[data-ndb-mail-actions-menu]');
                const surface = menu.querySelector('[data-ndb-popover-surface]');
                const items = [...menu.querySelectorAll('[role="menuitem"]')]
                    .filter((item) => getComputedStyle(item).display !== 'none');
                const preview = menu.querySelector('[data-ndb-mail-open-preview]');
                const download = menu.querySelector('[data-ndb-mail-download]');

                return actions.open
                    && menu.getAttribute('role') === 'menu'
                    && items.length === 2
                    && items.every((item) => Number.parseFloat(getComputedStyle(item).minHeight) >= 44)
                    && items.every((item) => item.querySelector('svg'))
                    && Number.parseFloat(getComputedStyle(surface).borderRadius) === 16
                    && preview.getAttribute('href').endsWith('/0/html')
                    && preview.getAttribute('target') === '_blank'
                    && download.getAttribute('href').endsWith('/0/eml');
            })()
            JS)
        ->keys('[data-ndb-mail-actions-trigger]', 'Escape')
        ->assertAttribute('[data-ndb-mail-actions-trigger]', 'aria-expanded', 'false')
        ->assertScript(<<<'JS'
            (() => {
                const actions = document.querySelector('[data-ndb-mail-actions]');
                const trigger = document.querySelector('[data-ndb-mail-actions-trigger]');

                return actions.open === false && document.activeElement === trigger;
            })()
            JS)
        ->select('[data-ndb-mail-preview-format]', 'text')
        ->assertValue('[data-ndb-mail-preview-format]', 'text')
        ->assertButtonDisabled('[data-ndb-mail-preview-viewport="desktop"]')
        ->assertButtonDisabled('[data-ndb-mail-preview-viewport="mobile"]')
        ->assertAttribute('[data-ndb-mail-preview-viewport-control]', 'aria-disabled', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const desktop = document.querySelector('[data-ndb-mail-preview-viewport="desktop"]');
                const mobile = document.querySelector('[data-ndb-mail-preview-viewport="mobile"]');

                mobile.click();

                return desktop.getAttribute('aria-pressed') === 'true'
                    && mobile.getAttribute('aria-pressed') === 'false';
            })()
            JS)
        ->assertScript('document.querySelector("[data-ndb-mail-preview-frame]").getAttribute("src").endsWith("/0/text")')
        ->select('[data-ndb-mail-preview-format]', 'html')
        ->assertButtonEnabled('[data-ndb-mail-preview-viewport="desktop"]')
        ->assertButtonEnabled('[data-ndb-mail-preview-viewport="mobile"]')
        ->assertScript('document.querySelector("[data-ndb-mail-preview-viewport-control]").getAttribute("aria-disabled") === null')
        ->select('[data-ndb-mail-filter]', 'attachments')
        ->assertValue('[data-ndb-mail-filter]', 'attachments')
        ->assertScript('document.querySelectorAll("[data-ndb-mail-item]:not([hidden])").length', 1)
        ->select('[data-ndb-mail-filter]', 'all')
        ->assertValue('[data-ndb-mail-filter]', 'all')
        ->assertScript('document.querySelectorAll("[data-ndb-mail-item]:not([hidden])").length', 3)
        ->click('[data-ndb-mail-item="2"]')
        ->assertAttribute('[data-ndb-mail-item="2"]', 'aria-pressed', 'true')
        ->assertSee('Welcome to Northstar')
        ->click('[data-ndb-mail-item="3"]')
        ->assertAttribute('[data-ndb-mail-item="3"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-mail-preview-frame]").getAttribute("src").endsWith("/2/text")')
        ->assertValue('[data-ndb-mail-preview-format]', 'text')
        ->click('[data-ndb-mail-item="1"]')
        ->click('[data-ndb-mail-preview-viewport="mobile"]')
        ->assertAttribute('[data-ndb-mail-preview-viewport="mobile"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-mail-preview-canvas]").getBoundingClientRect().width <= 376')
        ->select('[data-ndb-mail-preview-format]', 'text')
        ->assertScript(<<<'JS'
            (() => {
                const canvas = document.querySelector('[data-ndb-mail-preview-canvas]');
                const frame = document.querySelector('[data-ndb-mail-preview-frame]');

                return canvas.getBoundingClientRect().width > 500
                    && frame.offsetWidth === canvas.clientWidth;
            })()
            JS)
        ->select('[data-ndb-mail-preview-format]', 'html')
        ->assertAttribute('[data-ndb-mail-preview-viewport="mobile"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-mail-preview-canvas]").getBoundingClientRect().width <= 376')
        ->click('[data-ndb-mail-fact]:first-child button')
        ->assertAttribute('[data-ndb-mail-detail-tab="message"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-mail-detail-panel="message"]')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-mail-preview-controls]")).display === "none"')
        ->assertSee('receipt-NS-1042.pdf')
        ->assertSee('application/pdf')
        ->assertSee('Download')
        ->assertVisible('[data-ndb-mail-attachment-download]')
        ->assertAttribute('[data-ndb-mail-attachment-download]', 'download', 'receipt-NS-1042.pdf')
        ->assertScript('document.querySelector("[data-ndb-mail-attachment-download]").getAttribute("href").endsWith("/0/attachment/0")')
        ->assertScript(<<<'JS'
            (() => {
                const labels = [...document.querySelectorAll('[data-ndb-mail-detail-panel="message"] dt')]
                    .map((label) => label.textContent.trim());

                return ['BCC', 'Sender', 'Return path', 'Date']
                    .every((emptyLabel) => !labels.includes(emptyLabel));
            })()
            JS)
        ->assertSee('Default mailer')
        ->assertScript(<<<'JS'
            (() => {
                const values = [...document.querySelectorAll('[data-ndb-mail-detail-panel="message"] dd')];

                return values.every((value) => !getComputedStyle(value).fontFamily.includes('JetBrains Mono'));
            })()
            JS)
        ->click('[data-ndb-mail-detail-tab="source"]')
        ->assertVisible('[data-ndb-mail-detail-panel="source"]')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-mail-preview-controls]")).display === "none"')
        ->assertSee('NewDebugBar\\Tests\\Fixtures\\Mail\\ProfiledMailable')
        ->assertSee('tests/Support/DefinesTestApplication.php')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-mail-detail-panel="source"]');
                const disclosure = panel.querySelector('[data-ndb-inspector-disclosure]');

                return disclosure?.tagName === 'DETAILS'
                    && disclosure.open === false
                    && panel.querySelector('[data-ndb-inspector-stack]') === null;
            })()
            JS)
        ->click('[data-ndb-mail-detail-panel="source"] [data-ndb-inspector-disclosure] > summary')
        ->assertVisible('[data-ndb-mail-detail-panel="source"] [data-ndb-inspector-stack]')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-mail-detail-panel="source"]');
                const facts = [...panel.querySelectorAll('[data-ndb-inspector-source-fact]')];
                const stack = panel.querySelector('[data-ndb-inspector-stack]');
                const sourceClass = facts[0].querySelector('dd > code');
                const triggerPath = facts[1].querySelector('dd > span');
                const functionCall = stack.querySelector('li code');
                const stackPath = stack.querySelector('li [data-ndb-inspector-source-link]');

                return facts.length === 2
                    && stack !== null
                    && facts.every((fact) => fact.querySelector('svg') === null)
                    && getComputedStyle(sourceClass).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(sourceClass).fontFeatureSettings.includes('"calt"')
                    && !getComputedStyle(triggerPath).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFamily.includes('JetBrains Mono Variable')
                    && !getComputedStyle(stackPath).fontFamily.includes('JetBrains Mono Variable')
                    && facts.every((fact) => getComputedStyle(fact).color !== 'rgb(79, 70, 229)');
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('drills into mail details with compact icon tabs on mobile', function () {
    $preferences = json_encode([
        'theme' => 'dark',
        'favorites' => [],
    ], JSON_THROW_ON_ERROR);

    $page = visit('/profiled-mail-rich')
        ->resize(390, 844)
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
        ->click('[data-ndb-select-section="mail"]')
        ->waitForText('Payment receipt #NS-1042')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-mail-workspace]');
                const [list, detail] = workspace.children;
                const listBox = list.getBoundingClientRect();
                const detailBox = detail.getBoundingClientRect();
                const rows = [...document.querySelectorAll('[data-ndb-mail-item]')];

                return dialog.scrollWidth <= dialog.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && listBox.width > 0
                    && detailBox.width === 0
                    && rows.every((row) => getComputedStyle(row).borderLeftWidth === '0px')
                    && rows.every((row) => row.getBoundingClientRect().height <= 68);
            })()
            JS)
        ->click('[data-ndb-mail-item="2"]')
        ->assertAttribute('[data-ndb-mail-item="2"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-mail-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-mail-detail]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const frame = document.querySelector('[data-ndb-mail-preview-frame]');
                const previewCanvas = document.querySelector('[data-ndb-mail-preview-canvas]');
                const metadata = document.querySelector('[data-ndb-mail-metadata]');
                const metadataFacts = [...metadata.querySelectorAll('[data-ndb-mail-fact]')];
                const identity = document.querySelector('[data-ndb-mail-recipient]');
                const primary = document.querySelector('[data-ndb-inspector-detail-header-primary]');
                const workspace = document.querySelector('[data-ndb-mail-workspace]');
                const list = workspace.firstElementChild;
                const back = document.querySelector('[data-ndb-mail-detail-back]');
                const tabs = [...document.querySelectorAll('[data-ndb-mail-detail-tab]')];
                const labels = tabs.map((tab) => tab.querySelector('span'));
                const icons = tabs.map((tab) => tab.querySelector('[data-ndb-mail-detail-tab-icon]'));

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && detail.getBoundingClientRect().top >= content.getBoundingClientRect().top
                    && detail.getBoundingClientRect().width >= workspace.getBoundingClientRect().width - 2
                    && frame.offsetWidth === 1024
                    && frame.getBoundingClientRect().width <= detail.getBoundingClientRect().width
                    && frame.getBoundingClientRect().width <= previewCanvas.clientWidth + 1
                    && Math.abs(frame.getBoundingClientRect().height - previewCanvas.getBoundingClientRect().height) <= 1
                    && primary.scrollWidth <= primary.clientWidth + 1
                    && identity.scrollWidth <= identity.clientWidth + 1
                    && metadata.scrollWidth <= metadata.clientWidth + 1
                    && metadataFacts.length === 4
                    && metadataFacts.every((fact) => getComputedStyle(fact).display !== 'none')
                    && back.getClientRects().length > 0
                    && back.textContent.trim() === 'Messages'
                    && tabs.length === 3
                    && icons.every((icon) => icon && icon.getClientRects().length > 0)
                    && labels.every((label) => getComputedStyle(label).display === 'none')
                    && tabs.map((tab) => tab.getAttribute('aria-label')).join('|') === 'Preview|Message|Source'
                    && content.scrollWidth <= content.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-mail-preview-viewport="mobile"]')
        ->assertAttribute('[data-ndb-mail-preview-viewport="mobile"]', 'aria-pressed', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const frame = document.querySelector('[data-ndb-mail-preview-frame]');
                const canvas = document.querySelector('[data-ndb-mail-preview-canvas]');

                return frame.offsetWidth === 375
                    && frame.getBoundingClientRect().width <= canvas.clientWidth + 1
                    && Math.abs(frame.getBoundingClientRect().height - canvas.getBoundingClientRect().height) <= 1
                    && canvas.getBoundingClientRect().width <= 376;
            })()
            JS)
        ->click('[data-ndb-mail-preview-viewport="desktop"]')
        ->assertAttribute('[data-ndb-mail-preview-viewport="desktop"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-mail-preview-frame]").offsetWidth === 1024')
        ->click('[data-ndb-mail-detail-tab="message"]')
        ->assertVisible('[data-ndb-mail-detail-panel="message"]')
        ->click('[data-ndb-mail-detail-tab="preview"]')
        ->keys('[data-ndb-mail-actions-trigger]', 'Enter');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-mail-actions-menu]');

    $page
        ->assertVisible('[data-ndb-mail-actions-menu]')
        ->assertScript(<<<'JS'
            (() => {
                const menu = document.querySelector('[data-ndb-mail-actions-menu]');
                const rect = menu.getBoundingClientRect();

                return rect.left >= 0 && rect.right <= window.innerWidth;
            })()
            JS)
        ->keys('[data-ndb-mail-actions-trigger]', 'Escape')
        ->click('[data-ndb-mail-detail-back]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-mail-workspace]');
                const [list, detail] = workspace.children;
                const selected = document.querySelector('[data-ndb-mail-item="2"]');

                return getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && selected.getAttribute('aria-pressed') === 'true';
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

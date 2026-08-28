<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps a queued notification pending until its worker runs', function () {
    visit('/profiled-queued-communications')
        ->resize(1200, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="notifications"]')
        ->assertScript(DebugBarBrowser::waitForDetailsScript())
        ->waitForText('ProfiledNotification')
        ->assertSee('1 notification')
        ->assertSee('Queued')
        ->assertSee('Waiting for worker')
        ->assertSee('Queueable on notifications')
        ->assertSee('No destination resolved')
        ->assertAttribute('[data-ndb-notification-item="1"]', 'data-ndb-status', 'queued')
        ->assertScript(
            'getComputedStyle(document.querySelector("[data-ndb-notification-profile-link]")).display === "none"',
        )
        ->assertNoJavaScriptErrors();
});

it('groups notification attempts in a full-height delivery inspector', function () {
    $page = visit('/profiled-notifications-rich')
        ->resize(1440, 1200)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="notifications"]')
        ->assertScript(DebugBarBrowser::waitForDetailsScript())
        ->waitForText('ProfiledNotification')
        ->assertSee('2 notifications')
        ->assertScript('document.querySelector("[data-ndb-select-section=notifications]").textContent.trim().endsWith("2")')
        ->assertSee('Needs attention')
        ->assertSee('Elise Martin')
        ->assertSee('elise@example.test')
        ->assertSee('+32 470 12 34 56')
        ->assertSee('Sent to channel')
        ->assertSee('Traveler phone number is not verified.')
        ->assertValue('[data-ndb-notification-filter]', 'all')
        ->assertAttribute('[data-ndb-notification-detail-tab="delivery"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-notification-view-mail]')
        ->assertDontSee('View email')
        ->assertScript(<<<'JS'
            (() => {
                const control = document.querySelector('[data-ndb-notification-view-mail]');
                const card = control.closest('[data-ndb-notification-delivery]');
                const controlBox = control.getBoundingClientRect();
                const cardBox = card.getBoundingClientRect();

                return card.textContent.includes('Mail')
                    && control.getAttribute('aria-label') === 'Open email for Mail delivery'
                    && getComputedStyle(control).position === 'absolute'
                    && Math.abs(controlBox.left - cardBox.left) <= 1
                    && Math.abs(controlBox.top - cardBox.top) <= 1
                    && Math.abs(controlBox.right - cardBox.right) <= 1
                    && Math.abs(controlBox.bottom - cardBox.bottom) <= 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ndb-notifications]');
                const payload = root.querySelector('[data-ndb-notification-payload]');
                const payloadContent = payload.textContent.trim();
                const notifications = JSON.parse(atob(payloadContent));

                return !root.getAttribute('x-init').includes('ProfiledNotification')
                    && /^[A-Za-z0-9+/=]+$/.test(payloadContent)
                    && notifications.length === 2
                    && notifications.some(({ notification }) => notification.endsWith('\\ProfiledNotification'));
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-notification-workspace]');
                const loadedSection = document.querySelector('[data-ndb-loaded-section="notifications"]');
                const stage = document.querySelector('[data-ndb-section-stage]');
                const [list, detail] = workspace.children;
                const content = document.querySelector('[data-ndb-inspector-content]');
                const details = workspace.parentElement.parentElement.parentElement;
                const workspaceBox = workspace.getBoundingClientRect();
                const contentBox = content.getBoundingClientRect();
                const expectedBottom = contentBox.bottom - Number.parseFloat(getComputedStyle(details).paddingBottom);
                const listBox = list.getBoundingClientRect();
                const detailBox = detail.getBoundingClientRect();
                const selected = document.querySelector('[data-ndb-notification-item][aria-pressed="true"]');
                const header = document.querySelector('[data-ndb-notification-header]');
                const listTitle = selected.querySelector('[data-ndb-notification-list-title]');
                const listStatus = selected.querySelector('[data-ndb-notification-list-status]');
                const listRecipient = selected.querySelector('[data-ndb-notification-list-recipient]');
                const listActivity = selected.querySelector('[data-ndb-notification-list-activity]');
                const summary = document.querySelector('[data-ndb-notification-summary]');
                const summaryCount = document.querySelector('[data-ndb-notification-summary-count]');
                const summaryRuntime = document.querySelector('[data-ndb-notification-summary-runtime]');
                const filter = document.querySelector('[data-ndb-notification-filter]');
                const metadata = document.querySelector('[data-ndb-notification-metadata]');
                const metadataGrid = metadata.querySelector('[data-ndb-notification-facts]');
                const metadataFacts = [...metadata.querySelectorAll('[data-ndb-notification-fact]')];
                const sharedMetadataFacts = [...metadata.querySelectorAll('[data-ndb-inspector-fact]')];
                const sourceLink = metadata.querySelector('[data-ndb-inspector-source-link]');
                const metadataLabels = metadataFacts.map((fact) => fact.querySelector('dt').textContent.trim());
                const recipient = document.querySelector('[data-ndb-notification-recipient]');
                const deliveries = [...document.querySelectorAll('[data-ndb-notification-delivery]')];
                const channelControl = document.querySelector('[data-ndb-notification-channel-control]');
                const tabs = [...document.querySelectorAll('[data-ndb-notifications] [data-ndb-filter-tabs]')];
                const text = document.querySelector('[data-ndb-notifications]').textContent;

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
                    && selected.dataset.ndbNotificationItem === '1'
                    && selected.getAttribute('aria-pressed') === 'true'
                    && listStatus.textContent.trim() === 'Needs attention'
                    && !header.textContent.includes('Needs attention')
                    && getComputedStyle(selected).borderLeftWidth === '0px'
                    && selected.children.length === 4
                    && selected.getBoundingClientRect().height <= 68
                    && Math.abs(listTitle.getBoundingClientRect().left - listRecipient.getBoundingClientRect().left) <= 1
                    && Math.abs(listStatus.getBoundingClientRect().right - listActivity.getBoundingClientRect().right) <= 1
                    && getComputedStyle(listStatus).backgroundColor === 'rgba(0, 0, 0, 0)'
                    && getComputedStyle(listStatus).color !== getComputedStyle(listTitle).color
                    && selected.querySelector('[data-ndb-notification-outcomes]') === null
                    && summary.closest('[data-ndb-inspector-list-controls]') === filter.closest('[data-ndb-inspector-list-controls]')
                    && summary.getBoundingClientRect().left < filter.getBoundingClientRect().left
                    && Math.abs(summary.getBoundingClientRect().top - filter.getBoundingClientRect().top) <= 1
                    && summaryRuntime.getBoundingClientRect().top > summaryCount.getBoundingClientRect().top
                    && filter.options[0].value === 'all'
                    && tabs.length === 1
                    && tabs[0].parentElement === channelControl.parentElement
                    && tabs[0].dataset.ndbFilterTabsVariant === 'segmented'
                    && [...tabs[0].querySelectorAll('[data-ndb-notification-detail-tab]')]
                        .every((tab) => tab.dataset.ndbFilterTabVariant === 'segmented')
                    && Math.abs(
                        tabs[0].getBoundingClientRect().left + tabs[0].getBoundingClientRect().width / 2
                        - detail.getBoundingClientRect().left - detail.getBoundingClientRect().width / 2
                    ) <= 1
                    && getComputedStyle(channelControl).display === 'none'
                    && metadataFacts.length === 4
                    && sharedMetadataFacts.length === 4
                    && metadataFacts.every((fact) => fact.matches('[data-ndb-inspector-fact]'))
                    && metadataLabels.join('|') === 'Channels|Duration|Execution|Source'
                    && sourceLink !== null
                    && !getComputedStyle(sourceLink).fontFamily.includes('JetBrains Mono')
                    && sourceLink.querySelector('svg') === null
                    && getComputedStyle(sourceLink).paddingLeft === '0px'
                    && getComputedStyle(sourceLink).textDecorationLine.includes('underline')
                    && getComputedStyle(metadataGrid).display === 'grid'
                    && getComputedStyle(metadataGrid).borderTopWidth === '0px'
                    && metadata.querySelectorAll('svg').length === 0
                    && metadata.scrollWidth <= metadata.clientWidth + 1
                    && recipient.textContent.includes('Recipient')
                    && recipient.textContent.includes('Destinations')
                    && deliveries.length === 2
                    && getComputedStyle(detail).overflowY === 'auto'
                    && detail.tabIndex === 0
                    && !text.includes('Attempts')
                    && !text.includes('Delivered successfully')
                    && !['•', '·'].some((separator) => text.includes(separator));
            })()
            JS)
        ->click('[data-ndb-notification-detail-tab="payload"]')
        ->assertVisible('[data-ndb-notification-detail-panel="payload"]')
        ->assertVisible('[data-ndb-notification-channel-control]')
        ->assertValue('[data-ndb-notification-channel]', 'mail')
        ->select('[data-ndb-notification-channel]', 'profiled-sms')
        ->assertValue('[data-ndb-notification-channel]', 'profiled-sms')
        ->assertSee('RuntimeException')
        ->assertSee('No provider response was captured.')
        ->assertScript(<<<'JS'
            (() => {
                const fields = [...document.querySelectorAll('[data-ndb-notification-detail-panel="payload"] dl > div')];
                const valueFor = (label) => fields.find((field) => field.querySelector('dt').textContent.trim() === label)?.querySelector('dd');
                const codeBlocks = [...document.querySelectorAll('[data-ndb-notification-detail-panel="payload"] code[data-ndb-language="json"]')]
                    .filter((code) => code.getClientRects().length > 0);

                return getComputedStyle(valueFor('Exception')).fontFamily.includes('JetBrains Mono')
                    && !getComputedStyle(valueFor('Failed at')).fontFamily.includes('JetBrains Mono')
                    && codeBlocks.length >= 3
                    && codeBlocks.every((code) => code.hasAttribute('data-highlighted'))
                    && codeBlocks.some((code) => code.querySelector('[class^="hljs-"]'));
            })()
            JS)
        ->click('[data-ndb-notification-fact]:last-child button')
        ->assertVisible('[data-ndb-notification-detail-panel="source"]')
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-notification-channel-control]")).display === "none"')
        ->assertSee('NewDebugBar\\Tests\\Fixtures\\Notifications\\ProfiledNotification')
        ->assertSee('tests/Fixtures/Notifications/ProfiledNotification.php')
        ->assertSee('tests/Support/DefinesTestApplication.php')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-notification-detail-panel="source"]');
                const facts = [...panel.querySelectorAll('[data-ndb-inspector-source-fact]')];
                const stack = panel.querySelector('[data-ndb-inspector-stack]');
                const notificationClass = facts[0].querySelector('dd > code');
                const definedPath = facts[1].querySelector('dd > span');
                const triggerPath = facts[2].querySelector('dd > span');
                const notificationId = facts[3].querySelector('dd > span');
                const functionCall = stack.querySelector('li code');
                const stackPath = stack.querySelector('li [data-ndb-inspector-source-link]');

                return facts.length === 4
                    && stack !== null
                    && facts.every((fact) => fact.querySelector('svg') === null)
                    && getComputedStyle(notificationClass).fontFamily.includes('JetBrains Mono Variable')
                    && !getComputedStyle(notificationId).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFeatureSettings.includes('"calt"')
                    && !getComputedStyle(definedPath).fontFamily.includes('JetBrains Mono Variable')
                    && !getComputedStyle(triggerPath).fontFamily.includes('JetBrains Mono Variable')
                    && !getComputedStyle(stackPath).fontFamily.includes('JetBrains Mono Variable')
                    && facts.every((fact) => getComputedStyle(fact).color !== 'rgb(79, 70, 229)');
            })()
            JS)
        ->click('[data-ndb-notification-detail-tab="delivery"]')
        ->click('[data-ndb-notification-delivery]:has([data-ndb-notification-view-mail])');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertVisible('[data-ndb-section-panel="mail"]')
        ->assertSee('Your Kyoto journey is ready to review')
        ->assertAttribute('[data-ndb-mail-item="1"]', 'aria-pressed', 'true')
        ->click('[data-ndb-select-section="notifications"]')
        ->assertScript(DebugBarBrowser::waitForDetailsScript())
        ->select('[data-ndb-notification-filter]', 'sent')
        ->assertValue('[data-ndb-notification-filter]', 'sent')
        ->assertScript('document.querySelectorAll("[data-ndb-notification-item]:not([hidden])").length', 1)
        ->assertAttribute('[data-ndb-notification-item="2"]', 'aria-pressed', 'true')
        ->select('[data-ndb-notification-filter]', 'all')
        ->assertScript('document.querySelectorAll("[data-ndb-notification-item]:not([hidden])").length', 2)
        ->click('[data-ndb-notification-item="2"]')
        ->assertScript('document.querySelector("[data-ndb-notification-view-mail]") === null')
        ->assertNoJavaScriptErrors();
});

it('keeps many recipients readable without widening the inspector', function () {
    visit('/profiled-notifications-many-recipients')
        ->resize(1100, 820)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="notifications"]')
        ->assertScript(DebugBarBrowser::waitForDetailsScript())
        ->waitForText('Kyoto review team')
        ->assertSee('9 notifications')
        ->assertSee('8 recipients')
        ->assertVisible('[data-ndb-notification-search]')
        ->assertVisible('[data-ndb-notification-view-mail]')
        ->assertScript(<<<'JS'
            (() => {
                const search = document.querySelector('[data-ndb-notification-search]');
                const filter = document.querySelector('[data-ndb-notification-filter]');

                if (!search || !filter) {
                    return false;
                }

                const searchBox = search.getBoundingClientRect();

                return search.closest('[data-ndb-inspector-list-controls]') === filter.closest('[data-ndb-inspector-list-controls]')
                    && Math.abs(searchBox.height - filter.getBoundingClientRect().height) <= 1;
            })()
            JS)
        ->assertScript('document.querySelectorAll("[data-ndb-notification-item]").length', 9)
        ->assertScript(
            'document.querySelector("[data-ndb-notification-list]").scrollHeight > document.querySelector("[data-ndb-notification-list]").clientHeight',
        )
        ->assertScript(
            'getComputedStyle(document.querySelector("[data-ndb-notification-list]")).overflowY === "auto"',
        )
        ->assertScript(
            'document.querySelector("[data-ndb-notification-detail-title]").parentElement === document.querySelector("[data-ndb-notification-status]").parentElement',
        )
        ->assertScript(
            'document.querySelector("[data-ndb-notification-view-mail]").closest("[data-ndb-notification-delivery]") === document.querySelector("[data-ndb-notification-delivery]")',
        )
        ->assertScript(
            '[...document.querySelector("[data-ndb-notification-delivery]").querySelectorAll("[data-ndb-notification-destination]")].filter((destination) => destination.getClientRects().length > 0).length',
            8,
        )
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-notification-workspace]');
                const detail = document.querySelector('[data-ndb-notification-detail]');
                const header = document.querySelector('[data-ndb-notification-recipient]').parentElement;
                const destinations = [...document.querySelector('[data-ndb-notification-delivery]').querySelectorAll('[data-ndb-notification-destination]')]
                    .filter((destination) => destination.getClientRects().length > 0);

                return destinations.every((destination) => destination.textContent.includes('@example.test'))
                    && header.scrollWidth <= header.clientWidth + 1
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1;
            })()
            JS)
        ->fill('[data-ndb-notification-search]', 'Alexander Montgomery-Sinclair')
        ->assertScript('document.querySelectorAll("[data-ndb-notification-item]:not([hidden])").length', 1)
        ->assertSee('Alexander Montgomery-Sinclair')
        ->assertNoJavaScriptErrors();
});

it('drills into notification details with icon tabs on mobile', function () {
    $preferences = json_encode([
        'theme' => 'dark',
        'favorites' => [],
    ], JSON_THROW_ON_ERROR);

    visit('/profiled-notifications-rich')
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
        ->click('[data-ndb-select-section="notifications"]')
        ->assertScript(DebugBarBrowser::waitForDetailsScript())
        ->waitForText('ProfiledNotification')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-notification-workspace]');
                const [list, detail] = workspace.children;
                const rows = [...document.querySelectorAll('[data-ndb-notification-item]')];

                return dialog.scrollWidth <= dialog.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && rows.every((row) => getComputedStyle(row).borderLeftWidth === '0px')
                    && rows.every((row) => row.getBoundingClientRect().height <= 68);
            })()
            JS)
        ->click('[data-ndb-notification-item="1"]')
        ->assertAttribute('[data-ndb-notification-item="1"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-notification-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-notification-detail]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-notification-workspace]');
                const list = workspace.firstElementChild;
                const back = document.querySelector('[data-ndb-notification-detail-back]');
                const metadata = document.querySelector('[data-ndb-notification-metadata]');
                const tabs = [...document.querySelectorAll('[data-ndb-notification-detail-tab]')];
                const labels = tabs.map((tab) => tab.querySelector('span'));
                const icons = tabs.map((tab) => tab.querySelector('[data-ndb-notification-detail-tab-icon]'));

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && detail.getBoundingClientRect().top >= content.getBoundingClientRect().top
                    && detail.getBoundingClientRect().width >= workspace.getBoundingClientRect().width - 2
                    && metadata.scrollWidth <= metadata.clientWidth + 1
                    && back.getClientRects().length > 0
                    && back.textContent.trim() === 'Notifications'
                    && tabs.length === 3
                    && icons.every((icon) => icon && icon.getClientRects().length > 0)
                    && labels.every((label) => getComputedStyle(label).display === 'none')
                    && tabs.map((tab) => tab.getAttribute('aria-label')).join('|') === 'Delivery|Payload|Source'
                    && content.scrollWidth <= content.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-notification-detail-tab="payload"]')
        ->assertVisible('[data-ndb-notification-channel-control]')
        ->select('[data-ndb-notification-channel]', 'profiled-sms')
        ->assertSee('Traveler phone number is not verified.')
        ->click('[data-ndb-notification-detail-back]')
        ->click('[data-ndb-notification-item="2"]')
        ->assertScript('document.querySelector("[data-ndb-notification-view-mail]") === null')
        ->click('[data-ndb-notification-detail-back]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-notification-workspace]');
                const [list, detail] = workspace.children;
                const selected = document.querySelector('[data-ndb-notification-item="2"]');

                return getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && selected.getAttribute('aria-pressed') === 'true';
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

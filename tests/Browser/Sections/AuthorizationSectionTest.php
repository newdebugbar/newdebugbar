<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('scans filters searches and inspects authorization evidence on desktop', function () {
    $page = visit('/profiled-authorization-rich')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="authorization"]')
        ->waitForText('6 decisions');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertValue('[data-ndb-authorization-filter-control]', 'all')
        ->assertScript('document.querySelectorAll("[data-ndb-authorization-item]:not([hidden])").length', 6)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-authorization-workspace]');
                const [listPane, detail] = workspace.children;
                const list = document.querySelector('[data-ndb-authorization-list]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const abilities = [...document.querySelectorAll('[data-ndb-authorization-ability]')];
                const results = [...document.querySelectorAll('[data-ndb-authorization-result-label]')];
                const header = document.querySelector('[data-ndb-authorization-header]');
                const detailAbility = document.querySelector('[data-ndb-authorization-detail-ability]');
                const detailResult = document.querySelector('[data-ndb-authorization-detail-result]');
                const panel = document.querySelector('[data-ndb-authorization-detail-panel="combined"]');
                const source = panel.querySelector('[data-ndb-inspector-source-panel]');
                const handler = source.querySelector('code[data-ndb-language="php"]');
                const interfaceFont = getComputedStyle(workspace).fontFamily;

                return getComputedStyle(workspace).display === 'grid'
                    && getComputedStyle(listPane).display === 'flex'
                    && getComputedStyle(detail).display === 'flex'
                    && getComputedStyle(list).overflowY === 'auto'
                    && getComputedStyle(detail).overflowY === 'auto'
                    && content.scrollHeight <= content.clientHeight + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && abilities.every((ability) => Number.parseFloat(getComputedStyle(ability).fontSize) === 12)
                    && abilities.every((ability) => getComputedStyle(ability).fontFamily === interfaceFont)
                    && getComputedStyle(detailAbility).fontFamily === interfaceFont
                    && results.every((result) => Number.parseFloat(getComputedStyle(result).fontSize) === 12)
                    && results.every((result) => getComputedStyle(result).backgroundColor === 'rgba(0, 0, 0, 0)')
                    && results.every((result, index) => {
                        const gap = result.getBoundingClientRect().left - abilities[index].getBoundingClientRect().right;

                        return gap >= 7 && gap <= 9;
                    })
                    && header.querySelector('[data-ndb-authorization-detail-result]') === null
                    && header.querySelector('p') === null
                    && detailResult.getBoundingClientRect().top > header.getBoundingClientRect().bottom
                    && panel.querySelector('[data-ndb-authorization-metadata]') !== null
                    && panel.querySelector('[data-ndb-authorization-arguments-detail]') !== null
                    && source !== null
                    && handler?.hasAttribute('data-highlighted')
                    && document.querySelectorAll('[data-ndb-authorization-detail-tab]').length === 0
                    && document.querySelector('[data-ndb-authorization-item][aria-pressed="true"]') !== null;
            })()
            JS);

    foreach ([[1440, 700], [1440, 1000]] as [$width, $height]) {
        $page
            ->resize($width, $height)
            ->assertScript(<<<'JS'
                (() => {
                    const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                    const workspace = document.querySelector('[data-ndb-authorization-workspace]');
                    const content = document.querySelector('[data-ndb-inspector-content]');

                    return workspace.getBoundingClientRect().bottom <= dialog.getBoundingClientRect().bottom + 1
                        && workspace.getBoundingClientRect().height > 300
                        && workspace.scrollWidth <= workspace.clientWidth + 1
                        && content.scrollHeight <= content.clientHeight + 1;
                })()
                JS)
            ->assertNoJavaScriptErrors();
    }

    $page
        ->click('[data-ndb-authorization-item="3"]')
        ->assertScript("document.querySelector('[data-ndb-authorization-detail-ability]').textContent.trim() === 'create-studio-job'")
        ->assertScript(<<<'JS'
            (() => {
                const user = document.querySelector('[data-ndb-authorization-user-detail]').textContent;
                const argumentList = document.querySelector('[data-ndb-authorization-arguments-detail]');
                const arguments = argumentList.textContent;
                const rows = [...argumentList.querySelectorAll(':scope > div')];

                return user.includes('Guest')
                    && arguments.includes('Argument 1')
                    && arguments.includes('StudioJob')
                    && rows.every((row) => row.querySelector(':scope > dt') && row.querySelector(':scope > dd'));
            })()
            JS)
        ->click('[data-ndb-authorization-item="4"]')
        ->assertScript(<<<'JS'
            (() => {
                const ability = document.querySelector('[data-ndb-authorization-detail-ability]').textContent.trim();
                const arguments = document.querySelector('[data-ndb-authorization-arguments-detail]').textContent;

                return ability === 'revise-an-intentionally-long-kyoto-autumn-workspace-ability'
                    && arguments.includes('Argument 1')
                    && arguments.includes('Argument 2')
                    && arguments.includes('Argument 3');
            })()
            JS)
        ->assertDontSee('What should I inspect if this result looks wrong?')
        ->assertScript(<<<'JS'
            (() => {
                const filter = document.querySelector('[data-ndb-authorization-filter-control]');
                filter.focus();

                return document.activeElement === filter;
            })()
            JS)
        ->select('[data-ndb-authorization-filter-control]', 'denied')
        ->assertValue('[data-ndb-authorization-filter-control]', 'denied')
        ->assertScript('document.querySelectorAll("[data-ndb-authorization-item]:not([hidden])").length', 2)
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-ndb-authorization-item]:not([hidden])')]
                .every((item) => item.dataset.ndbAuthorizationResult === 'denied')
            JS)
        ->assertScript("document.querySelector('[data-ndb-authorization-detail-ability]').textContent.trim() === 'refund'")
        ->select('[data-ndb-authorization-filter-control]', 'all')
        ->type('[data-ndb-authorization-search]', 'private planning notes')
        ->assertScript('document.querySelectorAll("[data-ndb-authorization-item]:not([hidden])").length', 1)
        ->click('[data-ndb-authorization-item]:not([hidden])')
        ->assertScript("document.querySelector('[data-ndb-authorization-detail-ability]').textContent.trim() === 'access-private-planning-notes'")
        ->assertScript("document.querySelector('[data-ndb-authorization-detail-result]').textContent.trim() === 'Denied'")
        ->assertSee('Guests cannot open private planning notes.')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-authorization-detail-panel="combined"]');
                const response = panel.querySelector('[data-ndb-authorization-response]');
                const rows = [...response.querySelectorAll(':scope dl > div')];
                const valueFor = (label) => rows.find((row) => row.querySelector('dt')?.textContent.trim() === label)
                    ?.querySelector('dd')?.textContent.trim();
                const arguments = panel.querySelector('[data-ndb-authorization-arguments-detail]');
                const httpStatus = rows.find((row) => row.querySelector('dt')?.textContent.trim() === 'HTTP status');

                return response.getClientRects().length > 0
                    && arguments.getClientRects().length === 0
                    && valueFor('Message') === 'Guests cannot open private planning notes.'
                    && valueFor('Code') === 'guest_private_notes'
                    && httpStatus.getClientRects().length === 0
                    && document.querySelectorAll('[data-ndb-authorization-detail-tab]').length === 0;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const state = document.getElementById('newdebugbar')._x_dataStack?.[0];

                window.newdebugbarAuthorizationClipboard = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarAuthorizationClipboard.push(value),
                    },
                });

                return state?.selectedAuthorizationDecision?.ability === 'access-private-planning-notes';
            })()
            JS)
        ->assertCount('[data-ndb-authorization-detail-panel="combined"] [data-ndb-inspector-stack]', 0)
        ->click('[data-ndb-authorization-detail-panel="combined"] [data-ndb-inspector-disclosure] > summary')
        ->assertVisible('[data-ndb-authorization-detail-panel="combined"] [data-ndb-inspector-stack]')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-authorization-detail-panel="combined"]');
                const source = panel.querySelector('[data-ndb-inspector-source-panel]');
                const link = source.querySelector('[data-ndb-authorization-copy-handler-source]');
                const stack = source.querySelector('[data-ndb-inspector-stack]');
                const handler = source.querySelector('code[data-ndb-language="php"]');
                const interfaceFont = getComputedStyle(document.querySelector('[data-ndb-authorization]')).fontFamily;

                return source !== null
                    && link !== null
                    && getComputedStyle(link).fontFamily === interfaceFont
                    && getComputedStyle(link).textDecorationLine.includes('underline')
                    && link.querySelector('svg') === null
                    && handler.hasAttribute('data-highlighted')
                    && stack.textContent.includes('DefinesTestApplication.php')
                    && source.querySelector('[data-ndb-authorization-copy-callsite]') === null;
            })()
            JS)
        ->click('[data-ndb-authorization-copy-evidence]')
        ->click('[data-ndb-authorization-copy-handler-source]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            (() => {
                const [evidence, handlerSource] = window.newdebugbarAuthorizationClipboard;
                const parsed = JSON.parse(evidence);
                const handlerLink = document.querySelector('[data-ndb-authorization-copy-handler-source]');

                return window.newdebugbarAuthorizationClipboard.length === 2
                    && parsed.ability === 'access-private-planning-notes'
                    && parsed.result === 'denied'
                    && parsed.authorization_response.code === 'guest_private_notes'
                    && parsed.user === null
                    && !Object.hasOwn(parsed, 'actor')
                    && !Object.hasOwn(parsed, 'checked_from')
                    && parsed.stack[0].file.includes('DefinesTestApplication.php')
                    && handlerSource === handlerLink.textContent.trim();
            })()
            JS)
        ->click('[data-ndb-window-controls="expanded"] [data-ndb-window-action="shrink"]')
        ->assertVisible('[role="toolbar"][aria-label="Debug toolbar"]')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->assertVisible('[data-ndb-authorization-detail]')
        ->assertScript("document.querySelector('[data-ndb-authorization-detail-ability]').textContent.trim() === 'access-private-planning-notes'")
        ->fill('[data-ndb-authorization-search]', 'nothing can match this decision')
        ->assertScript('document.querySelectorAll("[data-ndb-authorization-item]:not([hidden])").length', 0)
        ->assertSee('No authorization decisions match these filters.')
        ->refresh()
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="authorization"]')
        ->waitForText('6 decisions')
        ->assertValue('[data-ndb-authorization-filter-control]', 'all')
        ->assertValue('[data-ndb-authorization-search]', '')
        ->assertNoJavaScriptErrors();
});

it('centers the decision count beside its filter', function () {
    $page = visit('/profiled-context')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="authorization"]')
        ->waitForText('2 decisions');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertScript(<<<'JS'
            (() => {
                const summary = document.querySelector('[data-ndb-authorization-summary]');
                const filter = document.querySelector('[data-ndb-authorization-filter-control]');
                const summaryRect = summary.getBoundingClientRect();
                const filterRect = filter.getBoundingClientRect();
                const summaryCenter = summaryRect.top + summaryRect.height / 2;
                const filterCenter = filterRect.top + filterRect.height / 2;

                return Math.abs(summaryCenter - filterCenter) <= 1;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('drills into authorization evidence on 390 pixel mobile in dark mode', function () {
    $preferences = json_encode([
        'theme' => 'dark',
        'favorites' => [],
    ], JSON_THROW_ON_ERROR);

    $page = visit('/profiled-authorization-rich')
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
        ->click('[data-ndb-select-section="authorization"]')
        ->waitForText('6 decisions');

    DebugBarBrowser::waitForDetails($page);

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-authorization-workspace]');
                const [list, detail] = workspace.children;
                const rows = [...document.querySelectorAll('[data-ndb-authorization-item]')];

                return dialog.scrollWidth <= dialog.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && rows.every((row) => row.scrollWidth <= row.clientWidth + 1);
            })()
            JS)
        ->click('[data-ndb-authorization-item]:nth-child(4)')
        ->assertVisible('[data-ndb-authorization-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-authorization-detail]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-authorization-workspace]');
                const list = workspace.firstElementChild;
                const back = document.querySelector('[data-ndb-authorization-detail-back]');
                const panel = document.querySelector('[data-ndb-authorization-detail-panel="combined"]');
                const source = panel.querySelector('[data-ndb-inspector-source-panel]');
                const contentRect = content.getBoundingClientRect();
                const backRect = back.getBoundingClientRect();

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && document.activeElement === detail
                    && detail.getBoundingClientRect().width >= workspace.getBoundingClientRect().width - 2
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && getComputedStyle(detail).overflowY !== 'auto'
                    && getComputedStyle(content).overflowY === 'auto'
                    && back.getClientRects().length > 0
                    && content.scrollTop === 0
                    && backRect.top >= contentRect.top
                    && backRect.bottom <= contentRect.bottom
                    && back.textContent.trim() === 'Decisions'
                    && document.querySelectorAll('[data-ndb-authorization-detail-tab]').length === 0
                    && source.getClientRects().length > 0
                    && source.getBoundingClientRect().top > document.querySelector('[data-ndb-authorization-metadata]').getBoundingClientRect().bottom
                    && content.scrollWidth <= content.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-authorization-detail-back]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-authorization-workspace]');
                const [list, detail] = workspace.children;
                const selected = document.querySelector('[data-ndb-authorization-item][aria-pressed="true"]');

                return getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && selected !== null
                    && document.activeElement === selected;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

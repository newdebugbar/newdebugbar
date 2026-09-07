<?php

it('collects background requests in the split button without changing the host page', function () {
    $page = visit('/profiled')
        ->assertScript('document.querySelector(\'[data-ndb-request-picker-trigger="toolbar"]\').disabled === true')
        ->assertScript(<<<'JS'
            (() => {
                const control = document.querySelector('[data-ndb-request-switcher="toolbar"] [data-ndb-request-control]');
                const primary = document.querySelector('[data-ndb-toolbar="request"]');
                const picker = document.querySelector('[data-ndb-request-picker-trigger="toolbar"]');
                const method = document.querySelector('[data-ndb-request-method="toolbar"]');
                const controlStyle = getComputedStyle(control);
                const primaryStyle = getComputedStyle(primary);
                const pickerStyle = getComputedStyle(picker);
                const methodStyle = getComputedStyle(method);

                return Number.parseFloat(controlStyle.borderTopWidth) === 1
                    && Number.parseFloat(primaryStyle.paddingLeft) === 10
                    && Number.parseFloat(primaryStyle.paddingRight) === 16
                    && Number.parseFloat(pickerStyle.borderLeftWidth) === 1
                    && Number.parseFloat(pickerStyle.paddingLeft) === 2
                    && Number.parseFloat(pickerStyle.paddingRight) === 2
                    && Number.parseFloat(methodStyle.paddingLeft) === 6
                    && Number.parseFloat(methodStyle.paddingRight) === 6
                    && method.getBoundingClientRect().width < 48;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                window.__newDebugBarActiveProfile = state.summary.id;
                window.__newDebugBarFetchSentinel = true;
                window.__newDebugBarDiscoveries = [];
                window.addEventListener('newdebugbar-profile-discovered', (event) => {
                    window.__newDebugBarDiscoveries.push(event.detail.profileId);
                });
                fetch('/api/plain-json?sequence=first');

                return true;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                fetch('/api/plain-json?sequence=third', { method: 'POST' });

                return true;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                fetch('/api/plain-json?sequence=second', { method: 'PATCH' });

                return true;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                const discoveries = window.__newDebugBarDiscoveries;

                return window.__newDebugBarFetchSentinel === true
                    && state.summary.id === window.__newDebugBarActiveProfile
                    && discoveries.length === 3
                    && state.laterRequestCount === 3
                    && document.querySelector('[data-ndb-request-picker-trigger="toolbar"]').disabled === false
                    && location.pathname === '/profiled'
                    && document.querySelectorAll('#newdebugbar').length === 1;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertVisible('[data-testid="host-page"]')
        ->assertSeeIn('[data-ndb-request-badge="toolbar"]', '3')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                Alpine.$data(toolbar).pinToolbar('bottom-right');

                return true;
            })()
            JS)
        ->assertAttribute('[data-ndb-toolbar-shell]', 'data-ndb-placement', 'bottom-right')
        ->assertSeeIn('[data-ndb-request-badge="corner"]', '3')
        ->click('[data-ndb-request-picker-trigger="corner"]')
        ->assertVisible('#newdebugbar-request-list-corner')
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ndb-request-picker-trigger="corner"]');
                const popover = document.querySelector('[data-ndb-request-popover="corner"]');
                const surface = popover.querySelector('[data-ndb-popover-surface]');
                const arrow = popover.querySelector('[data-ndb-popover-arrow]');
                const triggerBox = trigger.getBoundingClientRect();
                const surfaceBox = surface.getBoundingClientRect();
                const arrowBox = arrow.getBoundingClientRect();

                return surfaceBox.top < triggerBox.top
                    && surfaceBox.left >= 6
                    && surfaceBox.right <= window.innerWidth - 6
                    && Math.abs(
                        (triggerBox.left + triggerBox.width / 2)
                        - (arrowBox.left + arrowBox.width / 2),
                    ) <= 1;
            })()
            JS)
        ->keys('#newdebugbar-request-list-corner [data-ndb-request-option][aria-selected="true"]', 'Escape')
        ->assertScript('document.activeElement === document.querySelector(\'[data-ndb-request-picker-trigger="corner"]\')')
        ->assertScript(<<<'JS'
            (() => {
                const toolbar = document.querySelector('[data-ndb-toolbar-shell]');
                Alpine.$data(toolbar).pinToolbar('bottom');

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                state.setTheme('dark');

                return document.getElementById('newdebugbar').dataset.ndbTheme === 'dark';
            })()
            JS)
        ->click('[data-ndb-request-picker-trigger="toolbar"]')
        ->assertVisible('#newdebugbar-request-list-toolbar')
        ->assertVisible('[data-ndb-request-popover="toolbar"] [data-ndb-popover-surface]')
        ->assertVisible('[data-ndb-request-popover="toolbar"] [data-ndb-popover-arrow]')
        ->assertSeeIn('[data-ndb-request-badge="toolbar"]', '3')
        ->assertScript('document.querySelector(\'[data-ndb-request-popover="toolbar"] [data-ndb-request-popover-heading]\').textContent.trim() === \'Requests\'')
        ->assertAttribute('[data-ndb-request-picker-trigger="toolbar"]', 'aria-expanded', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ndb-request-picker-trigger="toolbar"]');
                const arrow = document.querySelector('[data-ndb-request-popover="toolbar"] [data-ndb-popover-arrow]');
                const triggerBox = trigger?.getBoundingClientRect();
                const arrowBox = arrow?.getBoundingClientRect();

                if (! triggerBox || ! arrowBox) return false;

                return Math.abs(
                    (triggerBox.left + triggerBox.width / 2)
                    - (arrowBox.left + arrowBox.width / 2),
                ) <= 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                const options = Array.from(document.querySelectorAll('#newdebugbar-request-list-toolbar [data-ndb-request-option]'));
                const groups = Array.from(document.querySelectorAll('#newdebugbar-request-list-toolbar [data-ndb-request-group]'));

                const methods = options.map((option) => option.querySelector('[data-ndb-request-method]'));
                const statuses = options.map((option) => option.querySelector('[data-ndb-request-status]'));
                const indicators = options.map((option) => option.querySelector('[data-ndb-request-current]'));
                const unread = options.map((option) => option.querySelector('[data-ndb-request-unread]'));
                const badge = document.querySelector('[data-ndb-request-badge="toolbar"]');
                const activeMethod = document.querySelector('[data-ndb-toolbar="request"] > span:first-child');

                return unread.filter((dot) => getComputedStyle(dot).opacity === '1').length === 3
                    && unread.every((dot) => dot.getBoundingClientRect().width === 6 && dot.getBoundingClientRect().height === 6)
                    && state.laterRequestCount === 3
                    && options.length >= 4
                    && groups.map((group) => group.dataset.ndbRequestGroup).join(',') === 'current,later'
                    && groups[0].querySelector('[data-ndb-request-option]').dataset.ndbProfileId === window.__newDebugBarActiveProfile
                    && groups[1].querySelectorAll('[data-ndb-request-option]').length === 3
                    && Number.parseFloat(getComputedStyle(groups[1]).borderTopWidth) === 0
                    && options.filter((option) => option.textContent.includes('/api/plain-json')).length >= 3
                    && methods.some((method) => method.textContent.trim() === 'POST')
                    && methods.some((method) => method.textContent.trim() === 'PATCH')
                    && document.activeElement.matches('[data-ndb-request-option]')
                    && getComputedStyle(badge).color === 'rgb(255, 255, 255)'
                    && getComputedStyle(badge).boxShadow === 'none'
                    && getComputedStyle(activeMethod).color === 'rgb(255, 255, 255)'
                    && methods.every((method) => getComputedStyle(method).color === 'rgb(255, 255, 255)')
                    && methods.every((method) => method.getBoundingClientRect().width >= 48)
                    && new Set(methods.map((method) => Math.round(method.getBoundingClientRect().width))).size === 1
                    && statuses.every((status, index) => {
                        const rowBox = options[index].getBoundingClientRect();
                        const statusBox = status.getBoundingClientRect();

                        return Math.abs(
                            (rowBox.top + rowBox.height / 2)
                            - (statusBox.top + statusBox.height / 2),
                        ) <= 1;
                    })
                    && new Set(statuses.map((status) => Math.round(status.getBoundingClientRect().left))).size === 1
                    && indicators.every((indicator) => Math.abs(indicator.getBoundingClientRect().width - 16) <= 0.5);
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                state.setTheme('light');

                return document.getElementById('newdebugbar').dataset.ndbTheme === 'light';
            })()
            JS)
        ->hover('#newdebugbar-request-list-toolbar [data-ndb-request-group="later"] [data-ndb-request-option]:first-of-type')
        ->assertScript(<<<'JS'
            (() => {
                const option = document.querySelector(
                    '#newdebugbar-request-list-toolbar [data-ndb-request-group="later"] [data-ndb-request-option]:first-of-type:hover',
                );
                const background = getComputedStyle(option).backgroundColor;
                const alpha = Number(
                    background.match(/\/\s*([\d.]+)\s*\)$/)?.[1]
                        ?? background.match(/,\s*([\d.]+)\s*\)$/)?.[1]
                        ?? 1
                );

                return alpha > 0 && alpha < 1;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                const option = Array.from(document.querySelectorAll('#newdebugbar-request-list-toolbar [data-ndb-request-option]'))
                    .find((candidate) => candidate.dataset.ndbProfileId !== state.summary.id && candidate.textContent.includes('/api/plain-json'));

                option.click();

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));

                return state.summary.path === '/api/plain-json'
                    && state.inspectorOpen === true
                    && state.selected === 'request'
                    && location.pathname === '/profiled'
                    && window.__newDebugBarFetchSentinel === true;
            })()
            JS)
        ->assertNoJavaScriptErrors()
        ->assertVisible('[data-ndb-section-panel="request"]')
        ->assertVisible('[data-ndb-request-picker-trigger="header"]')
        ->assertSeeIn('[data-ndb-request-badge="header"]', '2')
        ->click('[data-ndb-request-picker-trigger="header"]')
        ->assertVisible('#newdebugbar-request-list-header')
        ->assertScript(<<<'JS'
            (() => {
                const current = document.querySelector(
                    '#newdebugbar-request-list-header [data-ndb-request-group="current"] [data-ndb-request-option]',
                );
                const selectedLater = document.querySelector(
                    '#newdebugbar-request-list-header [data-ndb-request-group="later"] [data-ndb-request-option][aria-selected="true"]',
                );

                return current?.dataset.ndbProfileId === window.__newDebugBarActiveProfile
                    && selectedLater !== null
                    && getComputedStyle(current.querySelector('[data-ndb-request-unread]')).opacity === '0'
                    && getComputedStyle(selectedLater.querySelector('[data-ndb-request-unread]')).opacity === '0'
                    && Alpine.$data(document.getElementById('newdebugbar')).selected === 'request';
            })()
            JS)
        ->assertNoJavaScriptErrors();

    foreach ([1, 0] as $remaining) {
        $page->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.getElementById('newdebugbar'));
                window.newdebugbarViewedRequestIds ??= [state.summary.id];
                const option = [...document.querySelectorAll('#newdebugbar-request-list-header [data-ndb-request-group="later"] [data-ndb-request-option]')]
                    .find((option) => ! window.newdebugbarViewedRequestIds.includes(option.dataset.ndbProfileId));
                window.newdebugbarViewedRequestIds.push(option.dataset.ndbProfileId);
                option.click();
                return true;
            })()
            JS)
            ->assertScript('Alpine.$data(document.getElementById("newdebugbar")).unreadRequestCount', $remaining)
            ->assertScript('document.querySelector(\'[data-ndb-request-picker-trigger="header"]\').disabled === false')
            ->click('[data-ndb-request-picker-trigger="header"]')
            ->assertCount('#newdebugbar-request-list-header [data-ndb-request-group="later"] [data-ndb-request-option]', 3)
            ->assertScript(
                "[...document.querySelectorAll('#newdebugbar-request-list-header [data-ndb-request-unread]')].filter((dot) => getComputedStyle(dot).opacity === '1').length",
                $remaining,
            );
    }

    $page
        ->assertScript('document.querySelector(\'[data-ndb-request-badge="header"]\').getClientRects().length === 0')
        ->assertNoJavaScriptErrors();
});

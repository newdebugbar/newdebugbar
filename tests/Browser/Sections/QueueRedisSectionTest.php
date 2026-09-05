<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('filters queue activity and keeps its overview inline', function () {
    $page = visit('/profiled-queue')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="queue"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-queue-workspace]');

    $page
        ->assertValue('[data-ndb-queue-filter]', 'all')
        ->assertAttribute('[data-ndb-queue-item="1"]', 'aria-pressed', 'true')
        ->assertMissing('[data-ndb-queue-detail-tab]')
        ->assertVisible('[data-ndb-queue-detail-content]')
        ->assertMissing('[data-ndb-queue-attempts]')
        ->assertSee('ProfiledJob')
        ->assertDontSee('What happened to this job?')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-queue-detail]');
                const headerClass = document.querySelector('[data-ndb-queue-detail-header] h3');
                const sourceRow = [...document.querySelectorAll('[data-ndb-queue-communication] > div')]
                    .find((row) => row.querySelector('dt')?.textContent.trim() === 'Source');

                return getComputedStyle(headerClass).fontFamily !== getComputedStyle(detail).fontFamily
                    && getComputedStyle(sourceRow.querySelector('dd')).fontFamily !== getComputedStyle(detail).fontFamily;
            })()
            JS)
        ->assertScript('document.querySelectorAll("[data-ndb-queue-item]:not([hidden])").length', 3)
        ->select('[data-ndb-queue-filter]', 'failed')
        ->assertScript('document.querySelectorAll("[data-ndb-queue-item]:not([hidden])").length', 1)
        ->assertAttribute('[data-ndb-queue-item="3"]', 'aria-pressed', 'true')
        ->assertMissing('[data-ndb-queue-attempts]')
        ->assertSee('RuntimeException')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-queue-detail]');
                const exceptionClass = [...document.querySelectorAll('[data-ndb-queue-detail-content] p')]
                    .find((paragraph) => paragraph.textContent.trim() === 'RuntimeException');

                return getComputedStyle(exceptionClass).fontFamily !== getComputedStyle(detail).fontFamily;
            })()
            JS)
        ->assertScript('document.querySelectorAll("[data-ndb-queue-detail-tab]").length', 0)
        ->assertScript('document.querySelector("[data-ndb-queue-sort]") === null')
        ->assertNoJavaScriptErrors();
});

it('shows retained attempts inline and removes them when filtering selects an unlinked job', function () {
    $page = visit('/profiled-queue-attempts')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="queue"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-queue-attempts]');

    $page
        ->assertAttribute('[data-ndb-queue-item="1"]', 'aria-pressed', 'true')
        ->assertMissing('[data-ndb-queue-detail-tab]')
        ->assertVisible('[data-ndb-queue-detail-content]')
        ->assertVisible('[data-ndb-queue-attempts]')
        ->assertCount('[data-ndb-queue-attempt]', 1)
        ->assertSee('Open worker')
        ->select('[data-ndb-queue-filter]', 'failed')
        ->assertAttribute('[data-ndb-queue-item="2"]', 'aria-pressed', 'true')
        ->assertMissing('[data-ndb-queue-attempts]')
        ->assertVisible('[data-ndb-queue-detail-content]')
        ->assertNoJavaScriptErrors();
});

it('uses a focused Queue detail with Back on mobile dark mode', function () {
    $preferences = json_encode(['theme' => 'dark', 'favorites' => []], JSON_THROW_ON_ERROR);
    $page = visit('/profiled-queue-attempts')->resize(390, 844);

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
        ->click('[data-ndb-select-section="queue"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-queue-workspace]');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->click('[data-ndb-queue-item="1"]')
        ->assertVisible('[data-ndb-queue-back]')
        ->assertVisible('[data-ndb-queue-attempts]')
        ->assertMissing('[data-ndb-queue-detail-tab]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-queue-workspace]');
                const [list, detail] = workspace.children;
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const primary = detail.querySelector('[data-ndb-inspector-detail-header-primary]').getBoundingClientRect();
                const status = detail.querySelector('[data-ndb-queue-detail-status]').getBoundingClientRect();

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && status.width < primary.width
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && dialog.scrollWidth <= dialog.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-queue-back]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-queue-item=\\"1\\"]")')
        ->click('[data-ndb-queue-item="2"]')
        ->assertMissing('[data-ndb-queue-attempts]')
        ->assertVisible('[data-ndb-queue-detail-content]')
        ->click('[data-ndb-queue-back]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-queue-item=\\"2\\"]")')
        ->assertNoJavaScriptErrors();
});

it('shows Redis command and bounded key evidence without primary hashes', function () {
    $page = visit('/profiled-redis')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="redis"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-redis-workspace]');

    $page
        ->assertAttribute('[data-ndb-redis-item="1"]', 'aria-pressed', 'true')
        ->assertSee('Keys used')
        ->assertSee('private-direct-key')
        ->assertVisible('[data-ndb-redis-copy-keys]')
        ->assertVisible('[data-ndb-redis-detail-body] [data-ndb-inspector-source-link]')
        ->assertSeeIn(
            '[data-ndb-redis-detail-body] [data-ndb-inspector-source-link]',
            'tests/Support/DefinesTestApplication.php:',
        )
        ->assertSeeIn('[data-ndb-redis-detail-header] [data-ndb-redis-command]', 'GET')
        ->assertSeeIn('[data-ndb-redis-detail-header] [data-ndb-redis-key-label]', 'private-direct-key')
        ->assertScript(<<<'JS'
            (() => {
                const payload = JSON.parse(atob(document.querySelector('[data-ndb-redis-payload]').textContent.trim()));
                const primary = [...document.querySelectorAll('[data-ndb-redis-key-label]')].map((item) => item.textContent.trim());
                const hashes = payload.flatMap((command) => command.key_hashes ?? []);
                const header = document.querySelector('[data-ndb-redis-detail-header]');
                const workspace = document.querySelector('[data-ndb-redis-workspace]');
                const listOperations = [...document.querySelectorAll('[data-ndb-redis-list] [data-ndb-redis-command]')];
                const operation = header.querySelector('[data-ndb-redis-command]').getBoundingClientRect();
                const key = header.querySelector('[data-ndb-redis-key-label]').getBoundingClientRect();
                const source = document.querySelector('[data-ndb-redis-detail-body] [data-ndb-inspector-source-link]');
                const sourcePanel = document.querySelector('[data-ndb-redis-source]');
                const facts = document.querySelector('[data-ndb-redis-facts]');
                const keys = document.querySelector('[data-ndb-redis-key-evidence]');
                const keyValues = [...keys.querySelectorAll('[data-ndb-redis-key]')];
                const interfaceFont = getComputedStyle(workspace).fontFamily;

                window.newdebugbarRedisClipboardWrites = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarRedisClipboardWrites.push(value),
                    },
                });

                return document.querySelector('[data-ndb-redis-detail-body]') !== null
                    && document.querySelector('[data-ndb-redis-key-evidence]') !== null
                    && document.querySelector('[data-ndb-redis-detail-tab]') === null
                    && primary.every((label) => !hashes.includes(label))
                    && document.querySelector('[data-ndb-redis-sort]') === null
                    && listOperations.every((badge) => Math.round(badge.getBoundingClientRect().width) === 64)
                    && listOperations.every((badge) => getComputedStyle(badge).fontFamily === interfaceFont)
                    && source.tagName === 'BUTTON'
                    && getComputedStyle(source).textDecorationLine.includes('underline')
                    && getComputedStyle(source).fontFamily === interfaceFont
                    && !facts.contains(source)
                    && sourcePanel.contains(source)
                    && (keys.compareDocumentPosition(sourcePanel) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0
                    && keyValues.every((value) => getComputedStyle(value).fontFamily === interfaceFont)
                    && getComputedStyle(facts).borderBottomWidth === '0px'
                    && getComputedStyle(keys).borderTopWidth === '1px'
                    && Math.abs((operation.top + operation.height / 2) - (key.top + key.height / 2)) <= 1
                    && header.scrollWidth <= header.clientWidth;
            })()
            JS)
        ->click('[data-ndb-redis-detail-body] [data-ndb-inspector-source-link]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            window.newdebugbarRedisClipboardWrites.length === 1
                && /^tests\/Support\/DefinesTestApplication\.php:\d+$/.test(window.newdebugbarRedisClipboardWrites[0])
            JS)
        ->assertValue('[data-ndb-redis-filter]', 'all')
        ->select('[data-ndb-redis-filter]', 'failed')
        ->assertScript('document.querySelectorAll("[data-ndb-redis-item]:not([hidden])").length', 1)
        ->assertSee('RuntimeException')
        ->assertDontSee('What should I check after this failure?')
        ->assertScript('document.querySelector("[data-ndb-redis-failed=\\"true\\"]").textContent.includes("—")')
        ->assertNoJavaScriptErrors();
});

it('shows protected Redis identifiers in the interface typeface', function () {
    $page = visit('/profiled-redis-protected')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="redis"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-redis-workspace]');

    $page
        ->assertVisible('[data-ndb-redis-key-hash]')
        ->assertSee('Why are these identifiers protected?')
        ->assertSee('Copy identifiers')
        ->assertScript(<<<'JS'
            (() => {
                const identifier = document.querySelector('[data-ndb-redis-key-hash]');
                const detail = document.querySelector('[data-ndb-redis-detail]');

                return getComputedStyle(identifier).fontFamily === getComputedStyle(detail).fontFamily;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('uses a focused Redis detail with Back on mobile light mode', function () {
    $preferences = json_encode(['theme' => 'light', 'favorites' => []], JSON_THROW_ON_ERROR);
    $page = visit('/profiled-redis')->resize(390, 844);

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
        ->click('[data-ndb-select-section="redis"]');

    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-redis-workspace]');

    $page
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'light')
        ->click('[data-ndb-redis-item="1"]')
        ->assertVisible('[data-ndb-redis-back]')
        ->assertVisible('[data-ndb-redis-key-evidence]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-redis-workspace]');
                const [list, detail] = workspace.children;
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const header = document.querySelector('[data-ndb-redis-detail-header]');
                const facts = document.querySelector('[data-ndb-redis-facts]');
                const keys = document.querySelector('[data-ndb-redis-key-evidence]');

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && header.scrollWidth <= header.clientWidth + 1
                    && getComputedStyle(facts).borderBottomWidth === '0px'
                    && getComputedStyle(keys).paddingLeft === '12px'
                    && getComputedStyle(keys).paddingRight === '12px'
                    && dialog.scrollWidth <= dialog.clientWidth + 1;
            })()
            JS)
        ->click('[data-ndb-redis-back]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-redis-item=\\"1\\"]")')
        ->assertNoJavaScriptErrors();
});

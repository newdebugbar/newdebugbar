<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('filters, selects, and inspects outbound HTTP evidence', function () {
    visit('/profiled-http-client-rich')
        ->resize(1440, 900)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="http_client"]')
        ->waitForText('7 requests')
        ->assertSee('7 requests')
        ->assertDontSee('HTTP client needs attention.')
        ->assertScript('document.querySelector("[data-ndb-http-client-attention]") === null')
        ->assertValue('[data-ndb-http-client-filter]', 'all')
        ->assertAttribute('[data-ndb-http-client-detail-tab="response"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-http-client-detail-panel="request"]')
        ->assertMissing('[data-ndb-http-client-detail-panel="source"]')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-item]:not([hidden])").length', 7)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const [list, detail] = workspace.children;
                const sectionNavigation = document.querySelector('#newdebugbar-section-navigation');
                const inspectorContent = document.querySelector('[data-ndb-inspector-content]');
                const rows = [...document.querySelectorAll('[data-ndb-http-client-item]')];
                const listHeading = document.querySelector('[data-ndb-http-client-list-heading]');
                const headingCells = [...listHeading.children];
                const methods = rows.map((row) => row.querySelector('[data-ndb-http-client-method]'));
                const hosts = rows.map((row) => row.querySelector('[data-ndb-http-client-host]'));
                const paths = hosts.map((host) => host.nextElementSibling);
                const statuses = rows.map((row) => row.querySelector('[data-ndb-http-client-list-status]'));
                const durations = rows.map((row) => row.querySelector('[data-ndb-http-client-list-duration]'));
                const header = document.querySelector('[data-ndb-http-client-header]');
                const detailTabs = [...document.querySelectorAll('[data-ndb-http-client-detail-tab]')];
                const detailTabGroup = detailTabs[0].closest('[data-ndb-filter-tabs]');
                const requestFacts = document.querySelector('[data-ndb-http-client-request-facts]');
                const responseFacts = document.querySelector('[data-ndb-http-client-response-facts]');
                const sourceFacts = document.querySelector('[data-ndb-http-client-source-facts]');
                const search = document.querySelector('[data-ndb-http-client-search]');
                const searchIcon = search.parentElement.querySelector('svg');
                const filter = document.querySelector('[data-ndb-http-client-filter]');
                const urlAction = header.querySelector('[data-ndb-http-client-copy-url]');
                const urlIcon = urlAction.querySelector('svg');
                const headerMethod = header.querySelector('[data-ndb-http-client-detail-method]');
                const headerHost = header.querySelector('[data-ndb-http-client-detail-host]');
                const headerPath = header.querySelector('[data-ndb-http-client-detail-path]');
                const successStatus = document.querySelector('[data-ndb-http-client-item="2"] [data-ndb-http-client-list-status]');
                const failedStatus = document.querySelector('[data-ndb-http-client-item="6"] [data-ndb-http-client-list-status]');
                const slowDuration = document.querySelector('[data-ndb-http-client-item="1"] [data-ndb-http-client-list-duration] > span');

                const aligned = (elements, edge) => new Set(
                    elements.map((element) => Math.round(element.getBoundingClientRect()[edge])),
                ).size === 1;
                const centerY = (element) => {
                    const bounds = element.getBoundingClientRect();

                    return bounds.top + bounds.height / 2;
                };
                const verticallyAligned = (elements) => {
                    const centers = elements.map(centerY);

                    return Math.max(...centers) - Math.min(...centers) <= 1;
                };

                return getComputedStyle(workspace).display === 'grid'
                    && workspace.getBoundingClientRect().height > 500
                    && Math.abs(workspace.getBoundingClientRect().left - sectionNavigation.getBoundingClientRect().right) <= 1
                    && Math.abs(workspace.getBoundingClientRect().right - inspectorContent.getBoundingClientRect().right) <= 1
                    && getComputedStyle(workspace).borderTopWidth === '1px'
                    && getComputedStyle(workspace).borderRightWidth === '0px'
                    && getComputedStyle(workspace).borderBottomWidth === '0px'
                    && getComputedStyle(workspace).borderLeftWidth === '0px'
                    && getComputedStyle(workspace).borderRadius === '0px'
                    && detail.getBoundingClientRect().width > list.getBoundingClientRect().width * 1.6
                    && document.querySelector('[data-ndb-http-client-item][aria-pressed="true"]').dataset.ndbHttpClientItem === '1'
                    && rows.every((row) => getComputedStyle(row).borderLeftWidth === '0px')
                    && getComputedStyle(listHeading).position === 'sticky'
                    && getComputedStyle(listHeading).gridTemplateColumns === getComputedStyle(rows[0]).gridTemplateColumns
                    && headingCells.length === 4
                    && headingCells[0].textContent.trim() === 'Method'
                    && headingCells[1].textContent.trim() === 'Request'
                    && headingCells[2].textContent.trim() === 'Status'
                    && document.querySelectorAll('[data-ndb-http-client-sort-heading]').length === 1
                    && document.querySelector('[data-ndb-http-client-sort-heading="duration"]') !== null
                    && rows[0].children.length === headingCells.length
                    && headingCells.every((cell, index) => Math.abs(
                        cell.getBoundingClientRect().left - rows[0].children[index].getBoundingClientRect().left
                    ) <= 1)
                    && methods.length === 7
                    && methods.every((method) => Math.round(method.getBoundingClientRect().width) === 48)
                    && aligned(hosts, 'left')
                    && paths.every((path) => !getComputedStyle(path).fontFamily.includes('JetBrains Mono'))
                    && aligned(statuses, 'left')
                    && aligned(durations, 'left')
                    && rows.every((row, index) => verticallyAligned([
                        methods[index],
                        hosts[index].parentElement,
                        statuses[index],
                        durations[index],
                    ]))
                    && Math.abs(search.getBoundingClientRect().top - filter.getBoundingClientRect().top) <= 1
                    && search.getBoundingClientRect().right < filter.getBoundingClientRect().left
                    && searchIcon.getBoundingClientRect().left - search.getBoundingClientRect().left >= 9
                    && searchIcon.getBoundingClientRect().left - search.getBoundingClientRect().left <= 11
                    && Math.round(searchIcon.getBoundingClientRect().width) === 16
                    && searchIcon.getBoundingClientRect().right
                        <= search.getBoundingClientRect().left + parseFloat(getComputedStyle(search).paddingLeft)
                    && filter.tagName === 'SELECT'
                    && filter.options[0].value === 'all'
                    && filter.options[0].textContent.trim().startsWith('All (')
                    && document.querySelector('[data-ndb-http-client-sort]') === null
                    && !document.querySelector('[data-ndb-http-client]').textContent.includes('Oldest')
                    && !document.querySelector('[data-ndb-http-client]').textContent.includes('Slowest')
                    && hosts.every((host, index) => {
                        const gap = host.getBoundingClientRect().left - methods[index].getBoundingClientRect().right;

                        return gap >= 6 && gap <= 10;
                    })
                    && statuses.every((status) => getComputedStyle(status).backgroundColor === 'rgba(0, 0, 0, 0)')
                    && header.querySelector('[data-ndb-http-client-detail-status]') === null
                    && !header.textContent.includes('200 OK')
                    && !header.textContent.includes('ms')
                    && responseFacts && !header.contains(responseFacts)
                    && responseFacts.querySelector('[data-ndb-http-client-detail-status]').getAttribute('aria-label') === 'Status'
                    && responseFacts.textContent.includes('Duration')
                    && responseFacts.textContent.includes('Response size')
                    && !responseFacts.textContent.includes('Host')
                    && !responseFacts.textContent.includes('Source')
                    && requestFacts === null
                    && sourceFacts !== null
                    && (sourceFacts.compareDocumentPosition(responseFacts) & Node.DOCUMENT_POSITION_PRECEDING) !== 0
                    && header.querySelectorAll('button').length === 1
                    && headerHost.textContent.trim() === 'api.recommendations.test'
                    && !getComputedStyle(headerPath).fontFamily.includes('JetBrains Mono')
                    && verticallyAligned([headerMethod, headerHost])
                    && headerPath.getBoundingClientRect().top >= headerHost.getBoundingClientRect().bottom
                    && urlAction.textContent.trim() === 'Copy URL'
                    && urlIcon.querySelectorAll('path').length === 2
                    && urlIcon.querySelector('rect') === null
                    && document.querySelectorAll('[data-ndb-http-client-copy-curl]').length === 0
                    && detailTabs.map((tab) => tab.textContent.trim()).join('|') === 'Response|Request'
                    && detailTabs.every((tab) => tab.matches('[data-ndb-filter-tab]'))
                    && detailTabs.every((tab) => tab.dataset.ndbFilterTabVariant === 'segmented')
                    && detailTabGroup.dataset.ndbFilterTabsVariant === 'segmented'
                    && Math.abs(
                        detailTabGroup.getBoundingClientRect().left + detailTabGroup.getBoundingClientRect().width / 2
                        - detail.getBoundingClientRect().left - detail.getBoundingClientRect().width / 2
                    ) <= 1
                    && detailTabs.every((tab) => tab.querySelector('svg') === null)
                    && getComputedStyle(successStatus).color !== getComputedStyle(failedStatus).color
                    && getComputedStyle(slowDuration).color !== getComputedStyle(successStatus).color
                    && getComputedStyle(detail).overflowY === 'auto'
                    && detail.tabIndex === 0
                    && getComputedStyle(document.querySelector('[data-ndb-http-client-detail-panel="response"]')).display !== 'none'
                    && document.querySelector('[data-ndb-http-client-detail-panel="overview"]') === null
                    && document.querySelector('[data-ndb-http-client-guidance]') === null
                    && !document.querySelector('[data-ndb-http-client]').textContent.includes('•')
                    && rows.every((row) => ! /#\d+/.test(row.textContent));
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const body = document.querySelector('[data-ndb-http-client-body="response"]');
                const headers = document.querySelector('[data-ndb-http-client-headers="response"]');
                const source = document.querySelector('[data-ndb-http-client-source-facts]');

                return body !== null
                    && headers !== null
                    && (body.compareDocumentPosition(headers) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0
                    && headers.open === false
                    && headers.querySelector('pre') === null
                    && document.querySelector('[data-ndb-http-client-capture-limit="response"]').getClientRects().length === 0
                    && source.querySelector('details').open === false
                    && source.querySelector('[data-ndb-inspector-stack]') === null;
            })()
            JS)
        ->click('[data-ndb-http-client-headers="response"] > summary')
        ->assertScript('document.querySelector("[data-ndb-http-client-headers=\"response\"]").open === true')
        ->assertScript('document.querySelector("[data-ndb-http-client-headers=\"response\"] code[data-highlighted]") !== null')
        ->assertScript(<<<'JS'
            (() => {
                const heading = document.querySelector('[data-ndb-http-client-sort-heading="duration"]');
                const label = [...heading.children].find((child) => child.getAttribute('aria-hidden') === 'true' && ! child.matches('[data-ndb-sort-indicator]'));
                const executions = [...document.querySelectorAll('[data-ndb-http-client-item]:not([hidden])')]
                    .map((row) => Number(row.dataset.ndbExecution));
                window.__newdebugbarHttpTimeLabelLeft = label?.getBoundingClientRect().left;

                return heading.getAttribute('aria-pressed') === 'false'
                    && Number.isFinite(window.__newdebugbarHttpTimeLabelLeft)
                    && executions.every((execution, index) => index === 0 || executions[index - 1] <= execution);
            })()
            JS)
        ->keys('[data-ndb-http-client-sort-heading="duration"]', 'Enter')
        ->assertAttribute('[data-ndb-http-client-sort-heading="duration"]', 'aria-pressed', 'true')
        ->assertScript(<<<'JS'
            (() => {
                const rows = [...document.querySelectorAll('[data-ndb-http-client-item]:not([hidden])')];
                const durations = rows.map((row) => Number(row.dataset.ndbDuration));
                const firstMissing = durations.findIndex((duration) => duration < 0);
                const retained = firstMissing === -1 ? durations : durations.slice(0, firstMissing);
                const missing = firstMissing === -1 ? [] : durations.slice(firstMissing);
                const heading = document.querySelector('[data-ndb-http-client-sort-heading="duration"]');
                const label = [...heading.children].find((child) => child.getAttribute('aria-hidden') === 'true' && ! child.matches('[data-ndb-sort-indicator]'));
                const indicator = heading.querySelector('[data-ndb-sort-indicator]');

                return retained.every((duration, index) => index === 0 || retained[index - 1] >= duration)
                    && missing.every((duration) => duration < 0)
                    && document.querySelector('[data-ndb-http-client-item][aria-pressed="true"]').dataset.ndbHttpClientItem === '1'
                    && getComputedStyle(heading).color !== getComputedStyle(heading.parentElement).color
                    && indicator.getBoundingClientRect().width > 0
                    && Math.abs(label.getBoundingClientRect().left - window.__newdebugbarHttpTimeLabelLeft) <= 1;
            })()
            JS)
        ->click('[data-ndb-http-client-sort-heading="duration"]')
        ->assertScript(<<<'JS'
            (() => {
                const durations = [...document.querySelectorAll('[data-ndb-http-client-item]:not([hidden])')]
                    .map((row) => Number(row.dataset.ndbDuration));
                const firstMissing = durations.findIndex((duration) => duration < 0);
                const retained = firstMissing === -1 ? durations : durations.slice(0, firstMissing);
                const missing = firstMissing === -1 ? [] : durations.slice(firstMissing);

                return retained.every((duration, index) => index === 0 || retained[index - 1] <= duration)
                    && missing.every((duration) => duration < 0);
            })()
            JS)
        ->click('[data-ndb-http-client-sort-heading="duration"]')
        ->assertAttribute('[data-ndb-http-client-sort-heading="duration"]', 'aria-pressed', 'false')
        ->assertScript(<<<'JS'
            (() => {
                const heading = document.querySelector('[data-ndb-http-client-sort-heading="duration"]');
                const label = [...heading.children].find((child) => child.getAttribute('aria-hidden') === 'true' && ! child.matches('[data-ndb-sort-indicator]'));
                const indicatorIcon = heading.querySelector('[data-ndb-sort-indicator] svg');
                const executions = [...document.querySelectorAll('[data-ndb-http-client-item]:not([hidden])')]
                    .map((row) => Number(row.dataset.ndbExecution));

                return Math.abs(label.getBoundingClientRect().left - window.__newdebugbarHttpTimeLabelLeft) <= 1
                    && getComputedStyle(indicatorIcon).display === 'none'
                    && executions.every((execution, index) => index === 0 || executions[index - 1] <= execution);
            })()
            JS)
        ->select('[data-ndb-http-client-filter]', 'failed')
        ->assertValue('[data-ndb-http-client-filter]', 'failed')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-item]:not([hidden])").length', 4)
        ->assertScript('getComputedStyle(document.querySelector("[data-ndb-http-client-item=\\"1\\"]")).display === "none"')
        ->select('[data-ndb-http-client-filter]', 'slow')
        ->assertValue('[data-ndb-http-client-filter]', 'slow')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-item]:not([hidden])").length', 1)
        ->assertAttribute('[data-ndb-http-client-item="1"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-http-client-detail-panel="response"]')
        ->select('[data-ndb-http-client-filter]', 'failed')
        ->click('[data-ndb-http-client-item="6"]')
        ->assertAttribute('[data-ndb-http-client-item="6"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-status]").textContent.trim() === "503 Service Unavailable"')
        ->assertMissing('[data-ndb-http-client-failure]')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-http-client-detail-panel="response"]');

                return (panel.textContent.match(/Service unavailable\./g) ?? []).length === 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const state = document.getElementById('newdebugbar')._x_dataStack?.[0];

                window.newdebugbarExpectedClipboard = {
                    curl: state?.selectedHttpClientRequest?.curl,
                    url: state?.selectedHttpClientRequest?.url,
                    responseBody: state?.formatHttpClientEvidence(state?.selectedHttpClientRequest?.response?.body),
                    urlWidth: document.querySelector('[data-ndb-http-client-copy-url]').getBoundingClientRect().width,
                };
                window.newdebugbarClipboardWrites = [];
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarClipboardWrites.push(value),
                    },
                });

                return window.newdebugbarExpectedClipboard.url === 'https://api.error.test/v1/stale-cache/very-long-resource-identifier';
            })()
            JS)
        ->click('[data-ndb-http-client-copy-url]')
        ->click('[data-ndb-http-client-copy-body="response"]')
        ->wait(0.05)
        ->click('[data-ndb-http-client-detail-tab="request"]')
        ->assertVisible('[data-ndb-http-client-detail-panel="request"]')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-http-client-detail-panel="response"]')
        ->assertMissing('[data-ndb-http-client-detail-panel="source"]')
        ->assertSee('Request size')
        ->assertSeeIn('[data-ndb-http-client-request-facts]', 'Request size')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-host]").textContent.trim() === "api.error.test"')
        ->assertScript('document.querySelector("[data-ndb-http-client-headers=\"request\"]").open === false')
        ->click('[data-ndb-http-client-headers="request"] > summary')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-panel=\"request\"] code[data-ndb-language=\"json\"][data-highlighted]") !== null')
        ->click('[data-ndb-http-client-copy-curl]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            (() => {
                const [url, body, curl] = window.newdebugbarClipboardWrites;
                const currentUrlWidth = document.querySelector('[data-ndb-http-client-copy-url]').getBoundingClientRect().width;

                return url === window.newdebugbarExpectedClipboard.url
                    && body === window.newdebugbarExpectedClipboard.responseBody
                    && body.includes('Service unavailable.')
                    && curl === window.newdebugbarExpectedClipboard.curl
                    && Math.abs(currentUrlWidth - window.newdebugbarExpectedClipboard.urlWidth) <= 1
                    && curl.includes("--request 'DELETE'")
                    && curl.includes("'https://api.error.test/v1/stale-cache/very-long-resource-identifier'");
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                window.navigator.clipboard.writeText = async () => {
                    throw new Error('Clipboard permission denied');
                };
                document.execCommand = (command) => {
                    window.newdebugbarFallbackClipboard = command === 'copy'
                        ? document.activeElement?.value
                        : null;

                    return command === 'copy';
                };

                return true;
            })()
            JS)
        ->click('[data-ndb-http-client-copy-curl]')
        ->wait(0.05)
        ->assertScript('window.newdebugbarFallbackClipboard === window.newdebugbarExpectedClipboard.curl')
        ->click('[data-ndb-http-client-detail-tab="response"]')
        ->assertVisible('[data-ndb-http-client-detail-panel="response"]')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-detail-panel]").length', 1)
        ->assertMissing('[data-ndb-http-client-detail-panel="request"]')
        ->assertMissing('[data-ndb-http-client-detail-panel="source"]')
        ->assertSee('Response body')
        ->assertSee('Service unavailable.')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-panel=\"response\"] code[data-ndb-language=\"json\"][data-highlighted]") !== null')
        ->assertMissing('[data-ndb-http-client-detail-tab="source"]')
        ->assertVisible('[data-ndb-http-client-source-facts]')
        ->assertSeeIn('[data-ndb-http-client-source-facts]', 'Source')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-source]").textContent.includes("tests/Support/DefinesTestApplication.php")')
        ->assertSee('tests/Support/DefinesTestApplication.php')
        ->assertScript('document.querySelector("[data-ndb-http-client-source-facts] details").open === false')
        ->click('[data-ndb-http-client-source-facts] summary')
        ->assertScript(<<<'JS'
            (() => {
                const source = document.querySelector('[data-ndb-http-client-detail-source]');
                const sourceGroup = source.closest('[data-ndb-http-client-primary-source]');
                const sourceLink = source.closest('[data-ndb-inspector-source-link]');
                const stack = document.querySelector('[data-ndb-http-client-source-facts] [data-ndb-inspector-stack]');
                const functionCall = stack.querySelector('li code');
                const stackPath = stack.querySelector('[data-ndb-inspector-source-link] > span');
                const sourceGroupStyle = getComputedStyle(sourceGroup);
                const sourceLinkStyle = getComputedStyle(sourceLink);

                return sourceGroup !== null
                    && sourceLink !== null
                    && stack !== null
                    && sourceLink.querySelector('svg') === null
                    && sourceGroupStyle.backgroundColor === 'rgba(0, 0, 0, 0)'
                    && sourceLinkStyle.textDecorationLine.includes('underline')
                    && !getComputedStyle(source).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFamily.includes('JetBrains Mono Variable')
                    && getComputedStyle(functionCall).fontFeatureSettings.includes('"calt"')
                    && !getComputedStyle(stackPath).fontFamily.includes('JetBrains Mono Variable');
            })()
            JS)
        ->click('[data-ndb-http-client-detail-source]')
        ->wait(0.05)
        ->assertScript('window.newdebugbarFallbackClipboard === document.querySelector("[data-ndb-http-client-detail-source]").textContent.trim()')
        ->select('[data-ndb-http-client-filter]', 'all')
        ->click('[data-ndb-http-client-item="3"]')
        ->assertVisible('[data-ndb-http-client-detail-panel="response"]')
        ->assertScript('document.querySelector("[data-ndb-http-client-source-facts] details").open === false')
        ->assertScript('document.querySelector("[data-ndb-http-client-headers=\"response\"]").open === false')
        ->assertSee('Redirect to')
        ->assertSee('https://api.redirect.test/v2/current')
        ->click('[data-ndb-http-client-item="4"]')
        ->assertVisible('[data-ndb-http-client-detail-panel="response"]')
        ->assertSeeIn('[data-ndb-http-client-body="response"]', '[maximum depth reached]')
        ->assertVisible('[data-ndb-http-client-capture-limit="response"]')
        ->click('[data-ndb-http-client-detail-tab="request"]')
        ->assertSeeIn('[data-ndb-http-client-body="request"]', 'not-an-email')
        ->click('[data-ndb-http-client-copy-body="request"]')
        ->wait(0.05)
        ->assertScript(<<<'JS'
            (() => {
                const state = document.getElementById('newdebugbar')._x_dataStack?.[0];

                return window.newdebugbarFallbackClipboard
                    === state.formatHttpClientEvidence(state.selectedHttpClientRequest.request.body);
            })()
            JS)
        ->click('[data-ndb-http-client-item="7"]')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-status]").textContent.trim() === "Connection failed"')
        ->assertSee('No HTTP response was received.')
        ->assertMissing('[data-ndb-http-client-failure]')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-http-client-detail-panel="response"]');

                return (panel.textContent.match(/Connection refused/g) ?? []).length === 1;
            })()
            JS)
        ->select('[data-ndb-http-client-filter]', 'all')
        ->type('[data-ndb-http-client-search]', 'healthy.test')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-item]:not([hidden])").length', 1)
        ->assertAttribute('[data-ndb-http-client-item="2"]', 'aria-pressed', 'true')
        ->assertScript('document.querySelector("[data-ndb-http-client-detail-status]").textContent.trim() === "204 No Content"')
        ->type('[data-ndb-http-client-search]', ' no request matches')
        ->assertScript('document.querySelectorAll("[data-ndb-http-client-item]:not([hidden])").length', 0)
        ->assertSee('No outbound HTTP requests match these controls.')
        ->assertScript(<<<'JS'
            (() => {
                const listPanel = document.querySelector('[data-ndb-http-client-list]').parentElement;
                const empty = listPanel.lastElementChild;
                const emptyBounds = empty.getBoundingClientRect();
                const messageBounds = empty.firstElementChild.getBoundingClientRect();

                return getComputedStyle(document.querySelector('[data-ndb-http-client-list]')).display === 'none'
                    && Math.abs(
                        messageBounds.top + messageBounds.height / 2
                        - emptyBounds.top - emptyBounds.height / 2
                    ) <= 1;
            })()
            JS)
        ->assertDontSee('What happened')
        ->assertDontSee('Check next')
        ->assertDontSee('Open source')
        ->assertNoJavaScriptErrors();
});

it('keeps a sparse HTTP request useful without empty evidence', function () {
    visit('/profiled-http-client-sparse')
        ->resize(1280, 720)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="http_client"]')
        ->waitForText('1 request')
        ->assertSee('1 request')
        ->assertVisible('[data-ndb-http-client-search]')
        ->assertVisible('[data-ndb-http-client-filter]')
        ->assertScript(<<<'JS'
            (() => {
                const search = document.querySelector('[data-ndb-http-client-search]');
                const filter = document.querySelector('[data-ndb-http-client-filter]');
                const source = document.querySelector('[data-ndb-http-client-source-facts]');
                const response = document.querySelector('[data-ndb-http-client-detail-panel="response"]');

                return Math.abs(search.getBoundingClientRect().top - filter.getBoundingClientRect().top) <= 1
                    && source === null
                    && response.querySelector('[data-ndb-inspector-evidence]') === null
                    && response.querySelector('code') === null
                    && response.textContent.includes('204 No Content')
                    && !response.textContent.includes('Failure');
            })()
            JS)
        ->click('[data-ndb-http-client-detail-tab="request"]')
        ->assertVisible('[data-ndb-http-client-detail-panel="request"]')
        ->assertSeeIn('[data-ndb-http-client-header]', 'api.healthy.test')
        ->assertScript(<<<'JS'
            (() => {
                const request = document.querySelector('[data-ndb-http-client-detail-panel="request"]');
                const bodyFact = [...request.querySelectorAll('[data-ndb-inspector-fact]')]
                    .find((fact) => fact.textContent.includes('Request size'));

                return request.querySelector('[data-ndb-inspector-evidence]') === null
                    && request.querySelector('pre') === null
                    && bodyFact.getClientRects().length === 0;
            })()
            JS)
        ->assertMissing('[data-ndb-http-client-detail-panel="source"]')
        ->assertNoJavaScriptErrors();
});

it('uses the available height on a short desktop', function () {
    visit('/profiled-http-client-rich')
        ->resize(1280, 600)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="http_client"]')
        ->waitForText('7 requests')
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const loadedSection = document.querySelector('[data-ndb-loaded-section="http_client"]');
                const loadedStyle = getComputedStyle(loadedSection);

                return Math.abs(
                    workspace.getBoundingClientRect().bottom
                    - loadedSection.getBoundingClientRect().bottom
                    + Number.parseFloat(loadedStyle.paddingBottom)
                ) <= 1
                    && content.scrollHeight <= content.clientHeight + 2;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const list = document.querySelector('[data-ndb-http-client-list]');
                const detail = document.querySelector('[data-ndb-http-client-detail]');
                const controls = document.querySelector('[data-ndb-inspector-list-controls]');
                const listHeading = document.querySelector('[data-ndb-http-client-list-heading]');

                return controls.getClientRects().length > 0
                    && listHeading.getClientRects().length > 0
                    && getComputedStyle(listHeading).position === 'sticky'
                    && getComputedStyle(list).overflowY === 'auto'
                    && getComputedStyle(detail).overflowY === 'auto'
                    && list.scrollHeight > list.clientHeight;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const list = document.querySelector('[data-ndb-http-client-list]');
                const detail = document.querySelector('[data-ndb-http-client-detail]');
                const activeScrollOwners = [...workspace.querySelectorAll('*')].filter((element) => {
                    const overflowY = getComputedStyle(element).overflowY;

                    return ['auto', 'scroll'].includes(overflowY)
                        && element.scrollHeight > element.clientHeight + 1;
                });

                return activeScrollOwners.every((owner) => owner === list || owner === detail);
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const list = document.querySelector('[data-ndb-http-client-list]');
                const listHeading = document.querySelector('[data-ndb-http-client-list-heading]');
                list.scrollTop = 120;

                return dialog.scrollWidth <= dialog.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && Math.abs(listHeading.getBoundingClientRect().top - list.getBoundingClientRect().top) <= 1;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('keeps HTTP request details readable on mobile in dark mode', function () {
    $preferences = json_encode([
        'theme' => 'dark',
        'favorites' => [],
    ], JSON_THROW_ON_ERROR);

    visit('/profiled-http-client-rich')
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
        ->click('[data-ndb-select-section="http_client"]')
        ->waitForText('7 requests')
        ->assertAttribute('#newdebugbar', 'data-ndb-theme', 'dark')
        ->assertScript(<<<'JS'
            (() => {
                const dialog = document.querySelector('[role="dialog"][aria-label="Request inspector"]');
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const [list, detail] = workspace.children;
                const rows = [...document.querySelectorAll('[data-ndb-http-client-item]')];
                const methods = rows.map((row) => row.querySelector('[data-ndb-http-client-method]'));
                const hosts = rows.map((row) => row.querySelector('[data-ndb-http-client-host]'));
                const listHeading = document.querySelector('[data-ndb-http-client-list-heading]');
                const timeHeading = document.querySelector('[data-ndb-http-client-sort-heading="duration"]');

                return dialog.scrollWidth <= dialog.clientWidth + 1
                    && workspace.scrollWidth <= workspace.clientWidth + 1
                    && listHeading.scrollWidth <= listHeading.clientWidth + 1
                    && getComputedStyle(listHeading).gridTemplateColumns === getComputedStyle(rows[0]).gridTemplateColumns
                    && timeHeading.getBoundingClientRect().height < 32
                    && getComputedStyle(workspace).display !== 'grid'
                    && getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && rows.every((row) => row.scrollWidth <= row.clientWidth + 1)
                    && hosts.every((host, index) => {
                        const gap = host.getBoundingClientRect().left - methods[index].getBoundingClientRect().right;

                        return gap >= 6 && gap <= 10;
                    });
            })()
            JS)
        ->click('[data-ndb-http-client-item="7"]')
        ->assertVisible('[data-ndb-http-client-detail]')
        ->assertScript(<<<'JS'
            (() => {
                const detail = document.querySelector('[data-ndb-http-client-detail]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const [list] = workspace.children;
                const back = document.querySelector('[data-ndb-http-client-detail-back]');
                const tabs = [...document.querySelectorAll('[data-ndb-http-client-detail-tab]')];
                const urlAction = document.querySelector('[data-ndb-http-client-copy-url]');

                return getComputedStyle(list).display === 'none'
                    && getComputedStyle(detail).display === 'flex'
                    && detail.getBoundingClientRect().width >= workspace.getBoundingClientRect().width - 2
                    && detail.scrollWidth <= detail.clientWidth + 1
                    && content.scrollWidth <= content.clientWidth + 1
                    && back.getClientRects().length > 0
                    && back.textContent.trim() === 'Requests'
                    && tabs.length === 2
                    && tabs.map((tab) => tab.textContent.trim()).join('|') === 'Response|Request'
                    && tabs[0].getAttribute('aria-pressed') === 'true'
                    && tabs.every((tab) => tab.getClientRects().length > 0)
                    && tabs.every((tab) => tab.querySelector('svg') === null)
                    && urlAction.getBoundingClientRect().height >= 36;
            })()
            JS)
        ->assertVisible('[data-ndb-http-client-detail-panel="response"]')
        ->assertSee('No HTTP response was received.')
        ->click('[data-ndb-http-client-detail-back]')
        ->assertScript(<<<'JS'
            (() => {
                const workspace = document.querySelector('[data-ndb-http-client-workspace]');
                const [list, detail] = workspace.children;
                const selected = document.querySelector('[data-ndb-http-client-item="7"]');

                return getComputedStyle(list).display === 'flex'
                    && getComputedStyle(detail).display === 'none'
                    && selected.getAttribute('aria-pressed') === 'true';
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

it('shows a useful empty HTTP client state', function () {
    $page = visit('/profiled-http-client-empty')->resize(1280, 720);

    $page->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::selectSectionViaPalette($page, 'http_client');
    DebugBarBrowser::waitForVisibleElement($page, '[data-ndb-http-client-empty]');

    $page
        ->assertVisible('[data-ndb-http-client-empty]')
        ->assertSee("Requests made through Laravel's HTTP client will appear here with their response, timing, and source.")
        ->assertScript('document.querySelector("[data-ndb-http-client-workspace]") === null')
        ->assertNoJavaScriptErrors();
});

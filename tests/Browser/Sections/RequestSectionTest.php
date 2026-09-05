<?php

use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps expanded request evidence reachable through one scroll owner', function (int $width, int $height, string $theme) {
    $query = [];

    foreach (range(1, 24) as $index) {
        $query['filter_'.$index] = str_repeat('long-request-value-', 6).$index;
    }

    $page = visit('/profiled?'.http_build_query($query));
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: '$theme'}))");
    $page->refresh()->resize($width, $height);

    if ($width < 640) {
        $page->click('[data-ndb-mobile-toolbar-trigger="actions"]')
            ->click('[data-ndb-mobile-toolbar-action="inspector"]');
    } else {
        $page->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');
    }

    DebugBarBrowser::waitForDetails($page);

    $page->assertAttribute('#newdebugbar', 'data-ndb-theme', $theme)
        ->assertVisible('[data-ndb-request-details]')
        ->assertScript(<<<'JS'
            (() => {
                const details = document.querySelector('[data-ndb-request-details]');

                return details.clientHeight >= details.querySelector('summary').offsetHeight;
            })()
            JS)
        ->click('[data-ndb-request-details] > summary')
        ->assertScript(<<<'JS'
            (() => {
                const details = document.querySelector('[data-ndb-request-details]');

                return details.open
                    && details.clientHeight >= details.scrollHeight - 1;
            })()
            JS)
        ->click('[data-ndb-request-detail="query"]')
        ->assertAttribute('[data-ndb-request-detail="query"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-request-detail-panel="query"]')
        ->assertCount('[data-ndb-request-detail-panel="query"] tbody tr', 24)
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const details = document.querySelector('[data-ndb-request-details]');
                const scrollOwners = [content, ...content.querySelectorAll('*')].filter((element) => {
                    return element.clientHeight > 0
                        && element.scrollHeight > element.clientHeight + 1
                        && ['auto', 'scroll'].includes(getComputedStyle(element).overflowY);
                });

                return scrollOwners.length === 1
                    && scrollOwners[0] === content
                    && details.clientHeight >= details.scrollHeight - 1
                    && content.scrollWidth <= content.clientWidth + 1;
            })()
            JS);

    $page->script('document.querySelector("[data-ndb-inspector-content]").scrollTop = 100000');
    $page->assertScript(<<<'JS'
        (() => {
            const content = document.querySelector('[data-ndb-inspector-content]');
            const lastRow = document.querySelector('[data-ndb-request-detail-panel="query"] tbody tr:last-child');
            const bounds = content.getBoundingClientRect();
            const rowBounds = lastRow.getBoundingClientRect();

            return content.scrollTop > 0
                && rowBounds.top >= bounds.top
                && rowBounds.bottom <= bounds.bottom;
        })()
        JS);

    $page->click('[data-ndb-request-details] > summary')
        ->assertScript('document.querySelector("[data-ndb-request-details]").open === false')
        ->click('[data-ndb-request-details] > summary')
        ->assertScript(<<<'JS'
            (() => {
                const details = document.querySelector('[data-ndb-request-details]');

                return details.open && details.clientHeight >= details.scrollHeight - 1;
            })()
            JS)
        ->assertNoJavaScriptErrors();
})->with([
    'short desktop light' => [1280, 720, 'light'],
    'short desktop dark' => [1280, 720, 'dark'],
    'tall desktop light' => [1440, 1000, 'light'],
    'tall desktop dark' => [1440, 1000, 'dark'],
    'mobile light' => [390, 844, 'light'],
    'mobile dark' => [390, 844, 'dark'],
]);

it('keeps request evidence aligned and middleware accessible across viewports', function (int $width, int $height, string $theme) {
    $page = visit('/profiled');
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: '$theme'}))");
    $page->refresh()->resize($width, $height);
    $id = $page->script("document.getElementById('newdebugbar')._x_dataStack[0].summary.id");
    $store = app(ProfileStore::class);
    $profile = $store->get($id);
    $profile['sections']['request']['payload']['path'] = '/trips/'.str_repeat('very-long-journey-reference-', 8);
    $profile['sections']['request']['payload']['url'] = 'http://newdebugbar.test'.$profile['sections']['request']['payload']['path'];
    $profile['sections']['request']['payload']['action'] = 'App\\Http\\Controllers\\'.str_repeat('LongJourneyWorkspace', 6).'Controller@show';
    $profile['sections']['request']['payload']['middleware'] = array_map(
        static fn (int $index): string => 'App\\Http\\Middleware\\EnsureJourneyWorkspaceMembershipForOrganization'.$index,
        range(1, 12),
    );
    $store->put($profile);

    if ($width < 640) {
        $page->click('[data-ndb-mobile-toolbar-trigger="actions"]')
            ->click('[data-ndb-mobile-toolbar-action="inspector"]');
    } else {
        $page->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');
    }

    DebugBarBrowser::waitForDetails($page);

    $page->assertAttribute('#newdebugbar', 'data-ndb-theme', $theme)
        ->assertVisible('[data-ndb-request-trace]')
        ->assertScript(<<<'JS'
            (() => {
                const steps = [...document.querySelectorAll('[data-ndb-request-step]')];
                const near = (left, right) => Math.abs(left - right) <= 1;
                const center = (element) => {
                    const bounds = element.getBoundingClientRect();

                    return bounds.top + bounds.height / 2;
                };
                const firstLineCenter = (element) => element.getBoundingClientRect().top
                    + parseFloat(getComputedStyle(element).paddingTop)
                    + parseFloat(getComputedStyle(element).lineHeight) / 2;

                return steps.map((step) => step.dataset.ndbRequestStep).join(',') === 'received,matched,responded'
                    && steps.every((step) => {
                        const dot = step.querySelector('[data-ndb-request-dot]');
                        const heading = step.querySelector('h3');
                        const primary = step.querySelector('[data-ndb-request-primary]');

                        return near(center(dot), center(heading))
                            && (window.innerWidth >= 1024
                                ? near(center(dot), firstLineCenter(primary))
                                : primary.getBoundingClientRect().top >= heading.getBoundingClientRect().bottom);
                    });
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const received = document.querySelector('[data-ndb-request-step="received"]');
                const method = received.querySelector('[data-ndb-request-method]');
                const path = received.querySelector('[data-ndb-request-path]');
                const methodBox = method.getBoundingClientRect();
                const methodStyle = getComputedStyle(method);
                const pathFirstLineCenter = path.getBoundingClientRect().top
                    + parseFloat(getComputedStyle(path).lineHeight) / 2;

                return Math.abs(methodBox.top + methodBox.height / 2 - pathFirstLineCenter) <= 1
                    && ['borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth']
                        .every((property) => methodStyle[property] === '0px')
                    && methodStyle.boxShadow === 'none';
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const dots = [...document.querySelectorAll('[data-ndb-request-dot]')];
                const lines = [...document.querySelectorAll('[data-ndb-request-line]')];
                const near = (left, right) => Math.abs(left - right) <= 1;

                return lines.length === dots.length - 1 && lines.every((line, index) => {
                    const current = dots[index].getBoundingClientRect();
                    const next = dots[index + 1].getBoundingClientRect();
                    const bounds = line.getBoundingClientRect();

                    return near(bounds.top, current.top + current.height / 2)
                        && near(bounds.bottom, next.top + next.height / 2)
                        && near(bounds.left + bounds.width / 2, current.left + current.width / 2)
                        && Math.abs(bounds.width - 1) < 0.1;
                });
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const content = document.querySelector('[data-ndb-inspector-content]');
                const path = document.querySelector('[data-ndb-request-path]');
                const controller = document.querySelector('[data-ndb-request-controller]');
                const longValues = [path, controller];

                return content.scrollWidth <= content.clientWidth + 1
                    && longValues.every((element) => {
                        const style = getComputedStyle(element);

                        return element.getBoundingClientRect().height > parseFloat(style.lineHeight)
                            && style.whiteSpace !== 'nowrap'
                            && style.textOverflow !== 'ellipsis';
                    });
            })()
            JS)
        ->assertScript('document.querySelector("[data-ndb-request-details]").open === false')
        ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'false')
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ndb-request-middleware-trigger]');
                const responded = document.querySelector('[data-ndb-request-step="responded"]');

                trigger.scrollIntoView({ block: 'center' });
                window.newdebugbarRespondedBeforePopover = responded.getBoundingClientRect().top
                    - document.querySelector('[data-ndb-request-trace]').getBoundingClientRect().top;

                return trigger.getAttribute('aria-controls').startsWith('newdebugbar');
            })()
            JS)
        ->click('[data-ndb-request-middleware-trigger]')
        ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'true')
        ->assertVisible('[data-ndb-request-middleware-popover]')
        ->assertCount('[data-ndb-request-middleware-popover] li', 12)
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ndb-request-middleware-popover]');
                const trigger = document.querySelector('[data-ndb-request-middleware-trigger]');
                const content = document.querySelector('[data-ndb-inspector-content]');
                const responded = document.querySelector('[data-ndb-request-step="responded"]');
                const surface = popover.querySelector('[data-ndb-popover-surface]');
                const bounds = surface.getBoundingClientRect();

                const checks = {
                    association: trigger.getAttribute('aria-controls') === popover.id,
                    stableLayout: Math.abs(responded.getBoundingClientRect().top
                        - document.querySelector('[data-ndb-request-trace]').getBoundingClientRect().top
                        - window.newdebugbarRespondedBeforePopover) <= 1,
                    horizontalBounds: bounds.left >= 0 && bounds.right <= window.innerWidth,
                    verticalBounds: bounds.top >= 0 && bounds.bottom <= window.innerHeight,
                    overflow: content.scrollWidth <= content.clientWidth + 1,
                };
                const failures = Object.entries(checks).filter(([, passed]) => !passed).map(([name]) => name);

                if (failures.length > 0) {
                    throw new Error(JSON.stringify({ failures, viewport: [window.innerWidth, window.innerHeight], bounds: bounds.toJSON() }));
                }

                return true;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ndb-request-middleware-popover]');
                const scrollOwner = [popover, ...popover.querySelectorAll('*')].find((element) =>
                    element.clientHeight > 0 && element.scrollHeight > element.clientHeight + 1
                    && ['auto', 'scroll'].includes(getComputedStyle(element).overflowY),
                );

                if (scrollOwner) scrollOwner.scrollTop = scrollOwner.scrollHeight;

                const bounds = (scrollOwner ?? popover).getBoundingClientRect();
                const last = popover.querySelector('li:last-child');
                const lastBounds = last.getBoundingClientRect();

                return last.textContent.includes('EnsureJourneyWorkspaceMembershipForOrganization12')
                    && lastBounds.top >= bounds.top - 1
                    && lastBounds.bottom <= bounds.bottom + 1;
            })()
            JS)
        ->keys('[data-ndb-request-middleware-popover]', 'Escape')
        ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'false')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-request-middleware-trigger]")')
        ->click('[data-ndb-request-middleware-trigger]')
        ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'true')
        ->assertVisible('[data-ndb-request-middleware-popover]')
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ndb-request-middleware-popover]');
                const target = [...document.querySelectorAll('[data-ndb-request-step] h3, [data-ndb-request-step] dt, [data-ndb-section-heading]')]
                    .find((element) => {
                        const bounds = element.getBoundingClientRect();
                        const x = bounds.left + bounds.width / 2;
                        const y = bounds.top + bounds.height / 2;
                        const visible = document.elementFromPoint(x, y);

                        return bounds.width > 0 && bounds.height > 0
                            && x > 0 && x < window.innerWidth && y > 0 && y < window.innerHeight
                            && !popover.contains(visible) && element.contains(visible);
                    });

                target?.setAttribute('data-ndb-test-outside-target', '');

                return target !== undefined;
            })()
            JS)
        ->click('[data-ndb-test-outside-target]')
        ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'false')
        ->assertMissing('[data-ndb-request-middleware-popover]')
        ->assertNoJavaScriptErrors();
})->with([
    'short desktop light' => [1280, 720, 'light'],
    'short desktop dark' => [1280, 720, 'dark'],
    'tall desktop light' => [1440, 1000, 'light'],
    'tall desktop dark' => [1440, 1000, 'dark'],
    'tablet light' => [900, 900, 'light'],
    'tablet dark' => [900, 900, 'dark'],
    'mobile light' => [390, 844, 'light'],
    'mobile dark' => [390, 844, 'dark'],
]);

it('copies the complete request URL with feedback', function () {
    $page = visit('/profiled?destination=kyoto&season=autumn')
        ->resize(1440, 1000)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::waitForDetails($page);

    $page->assertScript(<<<'JS'
            (() => {
                window.newdebugbarClipboardWrites = [];
                window.newdebugbarRequestCopyWidth = document.querySelector('[data-ndb-request-copy]').getBoundingClientRect().width;
                Object.defineProperty(window.navigator, 'clipboard', {
                    configurable: true,
                    value: {
                        writeText: async (value) => window.newdebugbarClipboardWrites.push(value),
                    },
                });

                return true;
            })()
            JS)
        ->click('[data-ndb-request-copy]')
        ->assertSeeIn('[data-ndb-request-copy]', 'Copied')
        ->assertScript(<<<'JS'
            (() => {
                const [copied] = window.newdebugbarClipboardWrites;
                const copy = document.querySelector('[data-ndb-request-copy]');
                const url = new URL(copied);

                return window.newdebugbarClipboardWrites.length === 1
                    && url.pathname === '/profiled'
                    && url.searchParams.get('destination') === 'kyoto'
                    && url.searchParams.get('season') === 'autumn'
                    && Math.abs(copy.getBoundingClientRect().width - window.newdebugbarRequestCopyWidth) <= 1;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                window.navigator.clipboard.writeText = async () => {
                    throw new Error('Clipboard unavailable');
                };
                document.execCommand = () => false;

                return true;
            })()
            JS)
        ->click('[data-ndb-request-copy]')
        ->assertSeeIn('[data-ndb-request-copy]', 'Copy failed')
        ->click('[data-ndb-request-details] > summary')
        ->assertAttribute('[data-ndb-request-detail="headers"]', 'aria-pressed', 'true')
        ->click('[data-ndb-request-detail="query"]')
        ->assertVisible('[data-ndb-request-detail-panel="query"]')
        ->assertSeeIn('[data-ndb-request-detail-panel="query"]', 'kyoto')
        ->assertNoJavaScriptErrors();
});

it('returns middleware focus safely through keyboard navigation and the command palette', function () {
    $page = visit('/profiled')
        ->resize(1440, 1000)
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

    DebugBarBrowser::waitForDetails($page);

    foreach (['Tab', 'Shift+Tab'] as $key) {
        $page->click('[data-ndb-request-middleware-trigger]')
            ->assertVisible('[data-ndb-request-middleware-popover]')
            ->assertScript('document.activeElement === document.querySelector("[data-ndb-request-middleware-popover] ol")')
            ->keys('[data-ndb-request-middleware-popover] ol', $key)
            ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'false')
            ->assertMissing('[data-ndb-request-middleware-popover]')
            ->assertScript('document.activeElement === document.querySelector("[data-ndb-request-middleware-trigger]")');
    }

    $page->click('[data-ndb-request-middleware-trigger]')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-request-middleware-popover] ol")')
        ->keys('[data-ndb-request-middleware-popover] ol', 'Meta+Shift+P')
        ->assertVisible('[role="dialog"][aria-label="Command palette"]')
        ->assertAttribute('[data-ndb-request-middleware-trigger]', 'aria-expanded', 'false')
        ->assertMissing('[data-ndb-request-middleware-popover]')
        ->keys('[data-ndb-palette-search]', 'Escape')
        ->assertScript('document.activeElement === document.querySelector("[data-ndb-request-middleware-trigger]")')
        ->assertVisible('[role="dialog"][aria-label="Request inspector"]')
        ->assertNoJavaScriptErrors();
});

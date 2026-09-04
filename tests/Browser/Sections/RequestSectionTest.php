<?php

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

it('shows an aligned request trace and switches request detail groups', function () {
    $page = visit('/profiled')
        ->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]')
        ->click('[data-ndb-select-section="request"]')
        ->assertVisible('[data-ndb-request-trace]')
        ->assertScript(<<<'JS'
            (() => {
                const description = document.querySelector('[data-ndb-section-description]').getBoundingClientRect();
                const trace = document.querySelector('[data-ndb-request-trace]').getBoundingClientRect();

                return trace.top - description.bottom <= 32;
            })()
            JS)
        ->assertScript('document.querySelector("[data-ndb-request-status]").textContent.trim() === "200"')
        ->assertScript('/^Completed in (?:<1|\\d+(?:\\.\\d+)?) (?:µs|ms|s)$/.test(document.querySelector("[data-ndb-request-completion]").textContent.replace(/\\s+/g, " ").trim())')
        ->assertScript('!["Success", "Failed", "Completed successfully", "Completed with an error"].some((meaning) => document.querySelector("[data-ndb-request-trace]").textContent.includes(meaning))')
        ->assertScript('!["Laravel received the request.", "Laravel matched the route and middleware.", "Laravel sent the response to the client."].some((copy) => document.querySelector("[data-ndb-request-trace]").textContent.includes(copy))')
        ->assertVisible('[data-ndb-request-details]')
        ->assertScript('document.querySelector("[data-ndb-request-details]").open === false')
        ->click('[data-ndb-request-details] > summary')
        ->assertScript('document.querySelector("[data-ndb-request-details]").open === true')
        ->assertScript('document.querySelectorAll("[data-ndb-request-step]").length', 3)
        ->assertScript('document.querySelectorAll("[data-ndb-request-line]").length', 2)
        ->assertScript(<<<'JS'
            (() => {
                const panelBounds = document.querySelector('[data-ndb-section-panel="request"]').getBoundingClientRect();
                const summaryElement = document.querySelector('[data-ndb-request-summary]');
                const summaryBounds = summaryElement.getBoundingClientRect();
                const summary = getComputedStyle(summaryElement);
                const timelineElement = document.querySelector('[data-ndb-request-timeline]');
                const timeline = getComputedStyle(timelineElement);
                const firstStepBounds = timelineElement.querySelector('[data-ndb-request-step]').getBoundingClientRect();
                const detailsBounds = document.querySelector('[data-ndb-request-details]').getBoundingClientRect();
                const inset = innerWidth >= 640 ? 24 : 16;
                const near = (actual, expected) => Math.abs(actual - expected) < 1;

                return summary.borderTopWidth === '1px'
                    && summary.borderRightWidth === '0px'
                    && summary.borderBottomWidth === '1px'
                    && summary.borderLeftWidth === '0px'
                    && summary.borderRadius === '0px'
                    && summary.paddingLeft === '0px'
                    && summary.paddingRight === '0px'
                    && timeline.paddingLeft === `${inset}px`
                    && timeline.paddingRight === `${inset}px`
                    && near(summaryBounds.left, panelBounds.left + inset)
                    && near(summaryBounds.right, panelBounds.right - inset)
                    && near(firstStepBounds.left, summaryBounds.left)
                    && near(detailsBounds.left, panelBounds.left + inset)
                    && near(detailsBounds.right, panelBounds.right - inset);
            })()
            JS)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-request-step]')).every((step) => {
                const dot = step.querySelector('[data-ndb-request-dot]').getBoundingClientRect();
                const heading = step.querySelector('h3').getBoundingClientRect();

                return Math.abs((dot.top + dot.height / 2) - (heading.top + heading.height / 2)) < 1;
            })
            JS)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-request-line]')).every((line, index) => {
                const nextDot = document.querySelectorAll('[data-ndb-request-dot]')[index + 1].getBoundingClientRect();
                const bounds = line.getBoundingClientRect();

                return Math.abs(bounds.bottom - nextDot.top) < 1
                    && Math.abs(bounds.width - 2) < 0.1;
            })
            JS)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-request-detail]')).every((button) => {
                const parent = button.parentElement;
                const styles = getComputedStyle(parent);
                const availableWidth = parent.clientWidth
                    - parseFloat(styles.paddingLeft)
                    - parseFloat(styles.paddingRight);

                return Math.abs(button.getBoundingClientRect().width - availableWidth) < 1;
            })
            JS)
        ->assertScript(<<<'JS'
            Array.from(document.querySelectorAll('[data-ndb-request-detail-count], [data-ndb-request-detail-panel-count]'))
                .every((count) => /^\d+$/.test(count.textContent.trim()))
            JS)
        ->assertAttribute('[data-ndb-request-detail="headers"]', 'aria-pressed', 'true')
        ->click('[data-ndb-request-detail="session"]')
        ->assertAttribute('[data-ndb-request-detail="session"]', 'aria-pressed', 'true')
        ->assertVisible('[data-ndb-request-detail-panel="session"]')
        ->assertNoJavaScriptErrors();
});

it('uses the mobile request width for evidence instead of nested side gaps', function () {
    $page = visit('/profiled')
        ->resize(402, 874)
        ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
        ->click('[data-ndb-mobile-toolbar-action="inspector"]');

    $page
        ->assertVisible('[data-ndb-request-trace]')
        ->assertScript(<<<'JS'
            (() => {
                const panel = document.querySelector('[data-ndb-section-panel="request"]');
                const loaded = document.querySelector('[data-ndb-loaded-section="request"]');
                const description = document.querySelector('[data-ndb-section-description]');
                const summary = document.querySelector('[data-ndb-request-summary]');
                const method = summary.querySelector('span');
                const path = summary.querySelector('.ndb\\:truncate');
                const status = summary.querySelector('[data-ndb-request-status]');
                const completion = summary.querySelector('[data-ndb-request-completion]');
                const timeline = document.querySelector('[data-ndb-request-timeline]');
                const firstStep = timeline.querySelector('[data-ndb-request-step]');
                const details = document.querySelector('[data-ndb-request-details]');
                const panelBox = panel.getBoundingClientRect();
                const descriptionBox = description.getBoundingClientRect();
                const summaryBox = summary.getBoundingClientRect();
                const methodBox = method.getBoundingClientRect();
                const pathBox = path.getBoundingClientRect();
                const statusBox = status.getBoundingClientRect();
                const completionBox = completion.getBoundingClientRect();
                const timelineBox = timeline.getBoundingClientRect();
                const near = (actual, expected) => Math.abs(actual - expected) <= 1;
                const headerGap = summaryBox.top - descriptionBox.bottom;
                const textGap = completionBox.top - pathBox.bottom;

                return getComputedStyle(loaded).paddingLeft === '12px'
                    && getComputedStyle(summary).display === 'grid'
                    && headerGap >= 8
                    && headerGap <= 16
                    && near(summaryBox.left, panelBox.left)
                    && near(summaryBox.right, panelBox.right)
                    && near(firstStep.getBoundingClientRect().left, panelBox.left)
                    && near(details.getBoundingClientRect().left, panelBox.left)
                    && near(details.getBoundingClientRect().right, panelBox.right)
                    && near(pathBox.left, completionBox.left)
                    && textGap >= 0
                    && textGap <= 4
                    && near(methodBox.top + methodBox.height / 2, statusBox.top + statusBox.height / 2)
                    && timelineBox.top - summaryBox.bottom >= 8
                    && timelineBox.top - summaryBox.bottom <= 16
                    && panel.scrollWidth <= panel.clientWidth + 1;
            })()
            JS)
        ->assertNoJavaScriptErrors();
});

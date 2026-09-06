<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps related request actions in the activity header and source details compact', function (int $width, int $height) {
    $page = visit('/profiled-livewire')->resize($width, $height);

    if ($width < 640) {
        $page
            ->click('[data-ndb-mobile-toolbar-trigger="actions"]')
            ->click('[data-ndb-mobile-toolbar-action="inspector"]')
            ->click('[data-ndb-header-mobile-trigger="actions"]')
            ->click('[data-ndb-header-mobile-action="palette"]')
            ->click('[data-ndb-command="section:livewire"]');
    } else {
        $page->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');

        DebugBarBrowser::selectSectionViaPalette($page, 'livewire');
    }

    DebugBarBrowser::waitForDetails($page);

    if ($width < 1024) {
        $page->click('[data-ndb-livewire-activity-item][aria-pressed="true"]');
    }

    $page->assertVisible('[data-ndb-livewire-detail-panel="overview"]');

    $page->script(<<<'JS'
        (() => {
            const state = Alpine.$data(document.getElementById('newdebugbar'));

            window.newdebugbarActivityEvidenceCalls = { copies: [], requests: [] };
            state.copyText = (value) => window.newdebugbarActivityEvidenceCalls.copies.push(value);
            state.openRelatedProfile = (profileId, section) =>
                window.newdebugbarActivityEvidenceCalls.requests.push([profileId, section]);
            state.livewireServerActivity = [];
        })()
        JS);

    $setEvidence = function (?array $callsite, array $profileIds, string $status = 'complete') use ($page): void {
        $evidence = json_encode(compact('callsite', 'profileIds', 'status'), JSON_THROW_ON_ERROR);

        $page->script(<<<JS
            (() => {
                const state = Alpine.\$data(document.getElementById('newdebugbar'));
                const selected = state.livewireSelectedActivityId;
                state.livewireTrace = {
                    ...state.livewireTrace,
                    activity: state.livewireTrace.activity.map((item) =>
                        item.id === selected ? { ...item, ...{$evidence} } : item,
                    ),
                };
            })()
            JS);
    };

    $header = '[data-ndb-livewire-activity-header]';
    $group = '[role="group"][aria-label="Activity source"]';
    $source = $group.' [data-ndb-inspector-source-link]';
    $requests = $header.' button[aria-label^="Open related request "]';
    $firstRequest = $header.' [aria-label="Open related request 1"]';
    $secondRequest = $header.' [aria-label="Open related request 2"]';
    $firstProfileId = '550e8400-e29b-41d4-a716-446655440001';
    $secondProfileId = '550e8400-e29b-41d4-a716-446655440002';
    $callsite = ['file' => 'app/Livewire/HostCounter.php', 'line' => 29];

    $assertDetailFits = <<<'JS'
        (() => {
            const group = document.querySelector('[role="group"][aria-label="Activity source"]');
            const header = document.querySelector('[data-ndb-livewire-activity-header]');
            const detail = document.querySelector('[data-ndb-livewire-detail-pane]');
            const headerBox = header.getBoundingClientRect();
            const detailBox = detail.getBoundingClientRect();
            const titleBox = header.querySelector('h3').getBoundingClientRect();
            const controls = [...header.querySelectorAll('button, [data-ndb-livewire-activity-status]')]
                .filter((control) => control.getClientRects().length > 0)
                .map((control) => control.getBoundingClientRect());
            const overlaps = (left, right) => left.left < right.right && left.right > right.left
                && left.top < right.bottom && left.bottom > right.top;
            const visibleSource = group.getClientRects().length > 0;
            const sourceBox = group.getBoundingClientRect();

            const checks = {
                headerFits: headerBox.width > 0
                    && header.scrollWidth <= header.clientWidth + 1
                    && headerBox.left >= detailBox.left
                    && headerBox.right <= detailBox.right + 1
                    && headerBox.left >= 0
                    && headerBox.right <= window.innerWidth + 1,
                detailFits: detail.scrollWidth <= detail.clientWidth + 1,
                controlsFit: controls.every((box, index) => box.width > 0 && box.height > 0
                    && box.left >= headerBox.left
                    && box.right <= headerBox.right + 1
                    && box.top >= headerBox.top
                    && box.bottom <= headerBox.bottom + 1
                    && ! overlaps(box, titleBox)
                    && controls.slice(index + 1).every((other) => ! overlaps(box, other))),
                sourceFits: ! visibleSource || (group.scrollWidth <= group.clientWidth + 1
                    && sourceBox.left >= detailBox.left
                    && sourceBox.right <= detailBox.right + 1
                    && [...group.querySelectorAll('button')].every((button) => {
                        const box = button.getBoundingClientRect();

                        return box.width > 0 && box.height > 0
                            && box.left >= sourceBox.left
                            && box.right <= sourceBox.right + 1;
                    })),
            };
            const failures = Object.entries(checks).filter(([, passed]) => ! passed).map(([name]) => name);

            if (failures.length > 0) throw new Error('Livewire activity evidence layout failed: ' + failures.join(', '));

            return true;
        })()
        JS;

    $setEvidence($callsite, []);

    $page
        ->assertVisible($source)
        ->assertCount($requests, 0)
        ->assertScript($assertDetailFits)
        ->script(<<<'JS'
            window.newdebugbarActivityHeaderHeight = document.querySelector('[data-ndb-livewire-activity-header]')
                .getBoundingClientRect().height
            JS);

    $setEvidence($callsite, [$firstProfileId]);

    $page
        ->assertVisible($group)
        ->assertVisible($source)
        ->assertCount($requests, 1)
        ->assertSeeIn($firstRequest, 'Open request')
        ->assertScript($assertDetailFits);

    if ($width >= 1024) {
        $page->assertScript(<<<'JS'
            Math.abs(document.querySelector('[data-ndb-livewire-activity-header]').getBoundingClientRect().height
                - window.newdebugbarActivityHeaderHeight) <= 1
            JS);
    }

    $page
        ->click($source)
        ->click($firstRequest)
        ->assertScript(<<<'JS'
            JSON.stringify(window.newdebugbarActivityEvidenceCalls) === JSON.stringify({
                copies: ['app/Livewire/HostCounter.php:29'],
                requests: [['550e8400-e29b-41d4-a716-446655440001', 'request']],
            })
            JS);

    $longCallsite = [
        'file' => 'app/Livewire/Travel/Journeys/Preferences/InternationalJourneyPreferences.php',
        'line' => 129,
    ];
    $setEvidence($longCallsite, [$firstProfileId, $secondProfileId], 'failed_validation');

    $page
        ->assertVisible($source)
        ->assertCount($requests, 2)
        ->assertSeeIn($firstRequest, 'Open request 1')
        ->assertSeeIn($secondRequest, 'Open request 2')
        ->assertVisible($header.' [data-ndb-livewire-activity-status]')
        ->assertScript($assertDetailFits)
        ->click($source)
        ->click($firstRequest)
        ->click($secondRequest)
        ->assertScript(<<<'JS'
            JSON.stringify(window.newdebugbarActivityEvidenceCalls) === JSON.stringify({
                copies: [
                    'app/Livewire/HostCounter.php:29',
                    'app/Livewire/Travel/Journeys/Preferences/InternationalJourneyPreferences.php:129',
                ],
                requests: [
                    ['550e8400-e29b-41d4-a716-446655440001', 'request'],
                    ['550e8400-e29b-41d4-a716-446655440001', 'request'],
                    ['550e8400-e29b-41d4-a716-446655440002', 'request'],
                ],
            })
            JS);

    $setEvidence($callsite, []);

    $page
        ->assertVisible($group)
        ->assertVisible($source)
        ->assertCount($requests, 0)
        ->assertScript($assertDetailFits);

    $setEvidence(null, [$firstProfileId]);

    $page
        ->assertCount($requests, 1)
        ->assertScript(<<<'JS'
            document.querySelector('[role="group"][aria-label="Activity source"]').getClientRects().length === 0
            JS)
        ->assertScript($assertDetailFits);

    $setEvidence(null, []);

    $page
        ->assertVisible('[data-ndb-livewire-detail-panel="overview"]')
        ->assertCount($requests, 0)
        ->assertScript(<<<'JS'
            document.querySelector('[role="group"][aria-label="Activity source"]').getClientRects().length === 0
            JS)
        ->assertScript($assertDetailFits)
        ->assertNoJavaScriptErrors();
})->with([
    'desktop' => [1440, 900],
    'mobile' => [390, 844],
]);

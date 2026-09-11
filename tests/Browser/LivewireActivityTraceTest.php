<?php

use NewDebugBar\Tests\Support\DebugBarBrowser;

it('keeps activity evidence and every trace step together with accessible help', function (int $width, int $height, string $theme) {
    $page = ($width < 640 ? visit('/profiled-livewire')->on()->mobile() : visit('/profiled-livewire'))
        ->resize($width, $height);
    $page->script("localStorage.setItem('newdebugbar.preferences.v1', JSON.stringify({theme: '$theme'}))");
    $page->refresh()->resize($width, $height)
        ->click('[data-testid="host-counter"] button')
        ->assertSeeIn('[data-testid="host-counter-value"]', '1');

    if ($width < 640) {
        $page->click('[data-ndb-mobile-toolbar-trigger="actions"]')
            ->click('[data-ndb-mobile-toolbar-action="inspector"]')
            ->click('[data-ndb-header-mobile-trigger="actions"]')
            ->click('[data-ndb-header-mobile-action="palette"]')
            ->click('[data-ndb-command="inspector:livewire"]');
    } else {
        $page->click('[data-ndb-window-controls="compact"] [data-ndb-window-action="expand"]');
        DebugBarBrowser::selectInspectorViaPalette($page, 'livewire');
    }

    DebugBarBrowser::waitForDetails($page);

    if ($width < 1024) {
        $page->click('[data-ndb-livewire-activity-item][aria-pressed="true"]');
    }

    $first = '[data-ndb-livewire-phase="Queued"] [data-ndb-livewire-phase-trigger]';
    $second = '[data-ndb-livewire-phase="Sent"] [data-ndb-livewire-phase-trigger]';
    $help = '[data-ndb-livewire-phase-help]';

    $page
        ->assertVisible('[data-ndb-livewire-activity-evidence]')
        ->assertPresent('[data-ndb-livewire-trace]')
        ->assertScript(<<<'JS'
            (() => {
                const state = Alpine.$data(document.querySelector('[data-ndb-loaded-inspector="livewire"]'));
                const phases = state.selectedLivewireActivity.phases;
                const names = [...document.querySelectorAll('[data-ndb-livewire-phase]')].map((el) => el.dataset.ndbLivewirePhase);

                return phases.length >= 7
                    && JSON.stringify(names) === JSON.stringify(phases.map((phase) => phase.name));
            })()
            JS);

    // Retain repeated stream chunks without regrouping or deduplicating them.
    $page->script(<<<'JS'
        (() => {
            const state = Alpine.$data(document.querySelector('[data-ndb-loaded-inspector="livewire"]'));
            const selected = state.livewireSelectedActivityId;
            const item = state.selectedLivewireActivity;
            const sent = item.phases.find((phase) => phase.name === 'Sent');
            const responded = item.phases.find((phase) => phase.name === 'Responded');
            const phases = item.phases.flatMap((phase) => phase.name === 'Responded'
                ? [
                    { name: 'Streamed', at: sent.at + (responded.at - sent.at) / 3 },
                    { name: 'Streamed', at: sent.at + (responded.at - sent.at) * 2 / 3 },
                    phase,
                ]
                : [phase]);
            window.newdebugbarExpectedPhases = phases;
            state.livewireTrace = {
                ...state.livewireTrace,
                activity: state.livewireTrace.activity.map((item) => item.id === selected ? { ...item, phases } : item),
            };
        })()
        JS);

    $assertTrace = <<<'JS'
        (() => {
            const state = Alpine.$data(document.querySelector('[data-ndb-loaded-inspector="livewire"]'));
            const phases = state.selectedLivewireActivity.phases;
            const rows = [...document.querySelectorAll('[data-ndb-livewire-phase]')];
            const evidence = document.querySelector('[data-ndb-livewire-activity-evidence]');
            if (phases.every((phase) => phase.name === 'Queued')) {
                return rows.length === 0 && document.querySelector('[data-ndb-livewire-trace]') === null;
            }

            return rows.length === phases.length
                && rows.every((row, index) => row.dataset.ndbLivewirePhase === phases[index].name
                    && row.querySelector('[data-ndb-livewire-phase-name]').textContent === state.livewirePhaseLabel(phases[index].name))
                && rows.every((row, index) => row.querySelector('[data-ndb-livewire-phase-time]').textContent
                    === '+' + state.formatDuration(Math.max(0, phases[index].at - state.selectedLivewireActivity.startedAt)))
                && rows.every((row) => {
                    const name = row.querySelector('[data-ndb-livewire-phase-name]').getBoundingClientRect();
                    const time = row.querySelector('[data-ndb-livewire-phase-time]').getBoundingClientRect();
                    const owner = row.closest('[data-ndb-livewire-phase-group]').dataset.ndbLivewirePhaseGroup;

                    return time.top >= name.bottom && Math.abs(time.left - name.left) <= 1
                        && owner === (['Queued', 'Sent'].includes(row.dataset.ndbLivewirePhase) ? 'send' : 'receive');
                })
                && document.querySelectorAll('[data-ndb-livewire-phase-group="server"] [data-ndb-livewire-phase-time]').length === 0
                && evidence.scrollWidth <= evidence.clientWidth + 1;
        })()
        JS;
    $assertHelp = <<<'JS'
        (() => {
            const state = Alpine.$data(document.querySelector('[data-ndb-loaded-inspector="livewire"]'));
            const open = document.querySelector('[data-ndb-livewire-phase-trigger][aria-expanded="true"]');
            const help = document.getElementById(open?.getAttribute('aria-describedby'));
            const name = open?.closest('[data-ndb-livewire-phase]').dataset.ndbLivewirePhase;
            const box = help?.getBoundingClientRect();
            const timeline = document.querySelector('[aria-label="Livewire update timeline"]');
            const positions = [...timeline.querySelectorAll('[data-ndb-livewire-phase]')]
                .map((row) => row.getBoundingClientRect().top - timeline.getBoundingClientRect().top);

            const checks = {
                accessible: help?.getAttribute('role') === 'tooltip',
                explanation: help?.textContent.trim() === state.livewirePhaseDescription(name),
                fits: box?.width > 0 && box.left >= 0 && box.right <= window.innerWidth
                    && box.top >= 0 && box.bottom <= window.innerHeight,
                stable: JSON.stringify(positions) === JSON.stringify(window.newdebugbarTracePositions),
            };
            if (Object.values(checks).some((passed) => !passed)) {
                throw new Error(JSON.stringify({ checks, box: box?.toJSON(), positions, previous: window.newdebugbarTracePositions }));
            }

            return true;
        })()
        JS;

    $page
        ->assertScript($assertTrace)
        ->script(<<<'JS'
            (() => {
                const timeline = document.querySelector('[aria-label="Livewire update timeline"]');
                window.newdebugbarTracePositions = [...timeline.querySelectorAll('[data-ndb-livewire-phase]')]
                    .map((row) => row.getBoundingClientRect().top - timeline.getBoundingClientRect().top);
            })()
            JS);

    $page
        ->hover($first)
        ->assertVisible($help)
        ->assertScript($assertHelp)
        ->hover($help)
        ->assertVisible($help)
        ->hover('[data-ndb-livewire-activity-header]')
        ->assertMissing($help)
        ->click($first)
        ->assertVisible($help)
        ->assertScript($assertHelp)
        ->click($first)
        ->assertMissing($help)
        ->keys($first, 'Tab')
        ->assertScript('document.activeElement === document.querySelector(\''.$second.'\')')
        ->assertVisible($help)
        ->assertScript($assertHelp)
        ->keys($second, 'Escape')
        ->assertMissing($help)
        ->assertVisible('[data-ndb-livewire-activity-evidence]')
        ->assertScript('document.activeElement === document.querySelector(\''.$second.'\')')
        ->click($second)
        ->assertVisible($help)
        ->click('[data-ndb-livewire-activity-header] h3')
        ->assertMissing($help);

    // A failed update keeps exactly the checkpoints it reached.
    $page->script(<<<'JS'
        (() => {
            const state = Alpine.$data(document.querySelector('[data-ndb-loaded-inspector="livewire"]'));
            const selected = state.livewireSelectedActivityId;
            state.livewireTrace = {
                ...state.livewireTrace,
                activity: state.livewireTrace.activity.map((item) => item.id === selected
                    ? { ...item, status: 'failed', error: 'The connection closed.', phases: item.phases.slice(0, 4) }
                    : item),
            };
        })()
        JS);

    $page
        ->assertCount('[data-ndb-livewire-phase]', 4)
        ->assertVisible('[data-ndb-livewire-activity-status]')
        ->assertScript($assertTrace)
        ->click($first)
        ->assertVisible($help);

    if ($width < 1024) {
        $page->click('[data-ndb-livewire-detail-back]')
            ->assertMissing($help)
            ->click('[data-ndb-livewire-activity-item][aria-pressed="true"]');
    }

    $page->script(<<<'JS'
        (() => {
            const state = Alpine.$data(document.querySelector('[data-ndb-loaded-inspector="livewire"]'));
            const other = state.livewireActivity.find((item) => item.id !== state.livewireSelectedActivityId);
            state.selectLivewireActivity(other.id);
        })()
        JS);

    $page
        ->assertMissing($help)
        ->assertVisible('[data-ndb-livewire-activity-evidence]')
        ->assertScript($assertTrace)
        ->assertNoJavaScriptErrors();
})->with([
    'short desktop light' => [1024, 720, 'light'],
    'short desktop dark' => [1024, 720, 'dark'],
    'tall desktop light' => [1440, 1000, 'light'],
    'tall desktop dark' => [1440, 1000, 'dark'],
    'mobile light' => [390, 844, 'light'],
    'mobile dark' => [390, 844, 'dark'],
]);

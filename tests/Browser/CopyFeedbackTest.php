<?php

it('keeps labeled and source copy feedback stable across selections', function (int $width, int $height, string $theme) {
    $page = visit('/profiled')->resize($width, $height);
    $page->script("Alpine.\$data(document.getElementById('newdebugbar')).setTheme('$theme')");
    $page->click($width < 640
        ? '[data-ndb-mobile-toolbar-metric-scope="toolbar"][data-ndb-mobile-toolbar-metric="queries"]'
        : '[data-ndb-toolbar="queries"]')
        ->click('[data-ndb-query-item][data-ndb-repeated="true"]')
        ->assertVisible('[data-ndb-query-copy-sql]');

    $page->script(<<<'JS'
        (() => {
            window.newdebugbarCopyWrites = [];
            window.newdebugbarCopyWidths = [...document.querySelectorAll('[data-ndb-query-detail] [data-ndb-copy-button]')]
                .map((button) => button.getBoundingClientRect().width);
            Object.defineProperty(navigator, 'clipboard', {
                configurable: true,
                value: { writeText: async (value) => window.newdebugbarCopyWrites.push(value) },
            });
        })()
        JS);

    $source = '[data-ndb-query-detail] [data-ndb-inspector-source-link]';

    $page->click('[data-ndb-query-copy-sql]')
        ->assertSeeIn('[data-ndb-query-copy-sql] [role="status"]', 'Copied')
        ->assertAttribute('[data-ndb-query-copy-sql]', 'aria-label', 'Copied')
        ->click($source)
        ->assertSeeIn($source.' [role="status"]', 'Copied')
        ->assertAttribute($source, 'aria-label', 'Copied')
        ->assertScript(<<<'JS'
            (() => {
                const buttons = [...document.querySelectorAll('[data-ndb-query-detail] [data-ndb-copy-button]')];
                const sql = document.querySelector('[data-ndb-query-sql]').textContent.trim();
                const source = document.querySelector('[data-ndb-query-detail] [data-ndb-inspector-source-link] [role="status"]')
                    .previousElementSibling.textContent.trim();

                return window.newdebugbarCopyWrites[0] === sql
                    && window.newdebugbarCopyWrites[1] === source
                    && buttons.every((button, index) => {
                        const status = button.querySelector('[role="status"]');
                        return getComputedStyle(status.previousElementSibling).visibility === 'hidden'
                            && status.textContent === 'Copied'
                            && Math.abs(button.getBoundingClientRect().width - window.newdebugbarCopyWidths[index]) <= 1;
                    })
                    && [...buttons[0].querySelectorAll('svg')].filter((icon) => icon.getClientRects().length > 0).length === 1;
            })()
            JS)
        ->select('[data-ndb-query-execution-select]', '2')
        ->assertScript(<<<'JS'
            (() => {
                const status = document.querySelector('[data-ndb-query-copy-sql] [role="status"]');
                return status.getClientRects().length === 0
                    && getComputedStyle(status.previousElementSibling).visibility === 'visible';
            })()
            JS)
        ->click('[data-ndb-query-copy-sql]')
        ->assertSeeIn('[data-ndb-query-copy-sql] [role="status"]', 'Copied')
        ->assertScript("window.newdebugbarCopyWrites.at(-1) === 'select 2 as number'")
        ->assertScript(<<<'JS'
            [...document.querySelectorAll('[data-ndb-query-detail] [data-ndb-copy-button]')].every((button) => {
                const status = button.querySelector('[role="status"]');
                return status.getClientRects().length === 0
                    && getComputedStyle(status.previousElementSibling).visibility === 'visible';
            })
            JS)
        ->assertNoJavaScriptErrors();
})->with([
    'short desktop light' => [1024, 720, 'light'],
    'short desktop dark' => [1024, 720, 'dark'],
    'tall desktop light' => [1440, 1000, 'light'],
    'tall desktop dark' => [1440, 1000, 'dark'],
    'mobile light' => [390, 844, 'light'],
    'mobile dark' => [390, 844, 'dark'],
]);

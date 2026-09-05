<?php

it('composes Queue from the shared inspector workspace grammar', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/queue.blade.php');
    $attempts = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/queue/attempts.blade.php');

    expect($view)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<template x-if="selectedQueueActivity">')
        ->toContain('data-ndb-queue-detail-content')
        ->toContain('newdebugbar::livewire.sections.queue.attempts')
        ->toContain('data-ndb-queue-payload')
        ->toContain('data-ndb-queue-profile-link')
        ->not->toContain('<input')
        ->not->toContain('<select')
        ->not->toContain('queueSort')
        ->not->toContain('queueDetailTab')
        ->not->toContain('data-ndb-queue-detail-tab')
        ->not->toContain('What happened to this job?')
        ->not->toContain('Oldest')
        ->not->toContain('Slowest');

    expect($attempts)
        ->toContain('<template x-if="selectedQueueActivity.attempts.length > 0">')
        ->toContain('data-ndb-queue-attempts')
        ->toContain('data-ndb-queue-attempt')
        ->toContain('<x-newdebugbar::inspector-action')
        ->not->toContain('<x-newdebugbar::inspector-explanation')
        ->not->toContain('No worker attempt has been linked yet.');
});

it('composes Redis from the shared inspector workspace grammar', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/redis.blade.php');

    expect($view)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-operation-badge')
        ->toContain('layout="wrap"')
        ->toContain('data-ndb-redis-detail-header')
        ->toContain('data-ndb-redis-command')
        ->toContain('data-ndb-redis-key-label')
        ->toContain('<template x-if="selectedRedisCommand">')
        ->toContain('data-ndb-redis-detail-body')
        ->toContain('data-ndb-redis-key-evidence')
        ->toContain('data-ndb-redis-copy-keys')
        ->toContain('<template x-if="selectedRedisCommand.callsite">')
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('data-ndb-redis-source')
        ->toContain('<x-newdebugbar::inspector-source-link')
        ->toContain('copyText(selectedRedisCommand.source_label)')
        ->toContain('Why are these identifiers protected?')
        ->toContain('data-ndb-redis-payload')
        ->toContain(':bordered="false"')
        ->toContain('data-ndb-redis-facts')
        ->toContain('No key metadata was retained for this command.')
        ->not->toContain('<input')
        ->not->toContain('<select')
        ->not->toContain('redisDetailTab')
        ->not->toContain('setRedisDetailTab')
        ->not->toContain('data-ndb-redis-detail-tab')
        ->not->toContain('What should I check after this failure?')
        ->not->toContain('This list contains direct Redis commands.')
        ->not->toContain('redisSort')
        ->not->toContain('Succeeded')
        ->not->toContain('ndb:font-mono ndb:text-sm ndb:font-bold')
        ->not->toContain('Oldest')
        ->not->toContain('Slowest');

    expect(substr_count($view, 'x-text="selectedRedisCommand.key_count"'))->toBe(0)
        ->and(substr_count($view, 'x-text="selectedRedisCommand.status_label"'))->toBe(1);
});

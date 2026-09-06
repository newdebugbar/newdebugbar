<?php

it('supports static and Alpine-driven definition terms through one shared row', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $component = file_get_contents($views.'/components/inspector-definition-row.blade.php');
    $mail = file_get_contents($views.'/components/mail-message-details.blade.php');
    $authorization = file_get_contents($views.'/components/authorization-detail.blade.php');
    $redis = file_get_contents($views.'/livewire/sections/redis.blade.php');
    $events = file_get_contents($views.'/components/event-detail.blade.php');
    $queue = file_get_contents($views.'/livewire/sections/queue.blade.php');
    $duplicatedGeometry = 'ndb:grid ndb:gap-1 ndb:py-3 ndb:first:pt-0 ndb:sm:grid-cols-[8rem_minmax(0,1fr)] ndb:sm:gap-4';

    expect($component)
        ->toContain("'label' => null")
        ->toContain('@isset($term)')
        ->toContain('$term->attributes->class')
        ->and($mail)
        ->toContain('<x-slot:term x-text="field[0]"')
        ->not->toContain($duplicatedGeometry)
        ->and($authorization)
        ->toContain('<x-slot:term x-text="argument.role_label"')
        ->not->toContain($duplicatedGeometry)
        ->and($redis)
        ->toContain('<x-slot:term x-text="`Key ${index + 1}`"')
        ->toContain('<x-slot:term x-text="`Identifier ${index + 1}`"')
        ->not->toContain($duplicatedGeometry)
        ->and($events)
        ->toContain('<x-slot:term>')
        ->not->toContain($duplicatedGeometry)
        ->and($queue)
        ->toContain('<x-newdebugbar::inspector-definition-row label="Type"');
});

it('supports Alpine-driven labels through the shared source fact', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $component = file_get_contents($views.'/components/inspector-source-fact.blade.php');
    $authorization = file_get_contents($views.'/components/authorization-detail.blade.php');

    expect($component)
        ->toContain("'label' => null")
        ->toContain('@isset($term)')
        ->toContain('$term->attributes->class')
        ->and($authorization)
        ->toContain('<x-slot:term x-text="selectedAuthorizationDecision.handler_label"');
});

it('keeps protected Redis identifiers in the interface typeface', function () {
    $redis = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/redis.blade.php');

    preg_match('/<x-slot:value\s+data-ndb-redis-key-hash(?<attributes>.*?)>/s', $redis, $match);

    expect($match['attributes'] ?? null)
        ->not->toBeNull()
        ->not->toContain('ndb:font-mono');
});

it('uses the shared badge for Redis commands and code type for exception classes', function () {
    $redis = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/redis.blade.php');

    expect($redis)
        ->toContain('data-ndb-language="php"')
        ->toContain('<x-newdebugbar::inspector-operation-badge', 'x-text="selectedRedisCommand.command"')
        ->not->toMatch('/ndb:font-mono[^\"]*\"\s+x-text=\"selectedRedisCommand\.command\"/')
        ->toMatch('/ndb:font-mono[^\"]*\"\s+x-text=\"selectedRedisCommand\.exception_class/')
        ->not->toMatch('/ndb:font-mono[^\"]*\"\s+x-text=\"selectedRedisCommand\.(connection|key_count|phase_label)/');
});

it('uses code type only for Queue class references', function () {
    $queue = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/queue.blade.php');

    expect($queue)
        ->toMatch('/ndb:font-mono[^\"]*\"\s+x-text="selectedQueueActivity\.job"/')
        ->toMatch('/ndb:font-mono[^\"]*\"\s+x-text="selectedQueueActivity\.exception_class"/')
        ->not->toMatch('/ndb:font-mono[^\"]*\"\s+x-text="selectedQueueActivity\.job_id/')
        ->not->toMatch('/ndb:font-mono[^\"]*\"\s+x-text="selectedQueueActivity\.(connection|queue)/');
});

it('keeps authorization ability labels in the interface typeface', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/authorization.blade.php');
    $detail = file_get_contents($views.'/components/authorization-detail.blade.php');

    expect($section)
        ->toContain('<span', 'data-ndb-authorization-ability')
        ->not->toMatch('/<code[^>]*data-ndb-authorization-ability/s')
        ->not->toMatch('/data-ndb-authorization-ability[^>]*ndb:font-mono/s')
        ->and($detail)
        ->toContain('<h3', 'data-ndb-authorization-detail-ability')
        ->not->toMatch('/<code[^>]*data-ndb-authorization-detail-ability/s')
        ->not->toMatch('/data-ndb-authorization-detail-ability[^>]*ndb:font-mono/s');
});

it('offers a compact two column layout for controls inside split panes', function () {
    $component = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/inspector-list-controls.blade.php');
    $logs = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/logs.blade.php');

    expect($component)
        ->toContain("'layout' => 'responsive'")
        ->toContain('$isCompact = $layout === \'compact\'')
        ->toContain('\'ndb:grid-cols-2\' => $hasSecondaryFilter && $isCompact')
        ->toContain('\'ndb:col-span-full\' => $showSearch')
        ->and($logs)
        ->toContain('<x-newdebugbar::inspector-list-controls :show-search="true" layout="compact">');
});

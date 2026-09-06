<?php

use NewDebugBar\Presentation\LivewireActivityPresenter;

it('normalizes activity while associating renders with the preceding operation on the same component', function () {
    $records = (new LivewireActivityPresenter)->present([
        ['id' => 'orphan', 'type' => 'render', 'component_id' => 'child', 'duration_ms' => 9],
        ['id' => 'mount', 'type' => 'mount', 'component_id' => 'parent', 'at_ms' => 1],
        ['id' => 'child', 'type' => 'action', 'component_id' => 'child', 'method' => 'save', 'params' => ['été']],
        ['id' => 'render', 'type' => 'render', 'component_id' => 'parent', 'duration_ms' => 2.5],
        ['id' => 'render-again', 'type' => 'render', 'component_id' => 'parent', 'duration_ms' => 1],
        ['id' => 'change', 'type' => 'change', 'component_id' => 'parent', 'property' => 'count', 'before' => 0, 'submitted' => 1, 'server' => 1],
        ['id' => 'last-render', 'type' => 'render', 'component_id' => 'parent'],
    ], ['id' => 'profile-id', 'request_type' => 'livewire']);

    expect($records)->toHaveCount(7)
        ->and($records[1]['serverRenderIds'])->toBe(['render', 'render-again'])
        ->and($records[1]['initialRenderDurationMs'])->toBe(2.5)
        ->and($records[1]['serverRenderDurationMs'])->toBe(3.5)
        ->and($records[2]['serverRenderIds'])->toBe([])
        ->and($records[2]['actions'][0]['params'])->toBe(['été'])
        ->and($records[5]['serverRenderIds'])->toBe(['last-render'])
        ->and($records[5]['serverRenderDurationMs'])->toBeNull()
        ->and($records[5]['changes'][0])->toBe(['path' => 'count', 'before' => 0, 'submitted' => 1, 'server' => 1])
        ->and($records[5]['profileIds'])->toBe(['profile-id']);
});

it('retains event and failure evidence without associating a full page with a Livewire request', function () {
    $records = (new LivewireActivityPresenter)->present([[
        'component_id' => 'counter', 'event' => 'saved', 'params' => ['message' => "line one\n日本語"],
        'declared_target' => 'counter', 'effect' => 'redirect', 'message' => 'Failed',
        'callsite' => ['file' => 'app/Counter.php', 'line' => 12], 'profile_ids' => ['linked-profile'],
    ]], ['id' => 'page-profile', 'request_type' => 'full_page']);

    expect($records[0]['profileIds'])->toBe(['linked-profile'])
        ->and($records[0]['events'][0]['params']['message'])->toBe("line one\n日本語")
        ->and($records[0]['events'][0]['declaredTarget'])->toBe('counter')
        ->and($records[0]['effects'])->toBe(['redirect' => true])
        ->and($records[0]['error'])->toBe('Failed')
        ->and($records[0]['callsite'])->toBe(['file' => 'app/Counter.php', 'line' => 12]);
});

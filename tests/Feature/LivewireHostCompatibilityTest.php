<?php

use Illuminate\Support\Facades\File;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\HostCounter;
use NewDebugBar\Tests\Fixtures\HostValidationForm;

beforeEach(function () {
    Livewire::component('host-counter', HostCounter::class);
    Livewire::component('host-validation-form', HostValidationForm::class);
});

/** @return array<string, mixed> */
$hostCounterSnapshot = function (): array {
    $html = (string) app('livewire')->mount('host-counter');

    return Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');
};

/** @param array<string, mixed> $snapshot @return array<string, mixed> */
$hostCounterMessage = function (array $snapshot): array {
    return [
        'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
        'updates' => [],
        'calls' => [['method' => 'increment', 'params' => []]],
    ];
};

it('profiles host Livewire requests without storing framework snapshots', function () use ($hostCounterMessage, $hostCounterSnapshot) {
    $response = $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [$hostCounterMessage($hostCounterSnapshot())],
    ], ['X-Livewire' => '1']);

    $response->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));

    $livewire = $profile['sections']['livewire'];

    expect($profile['sections']['request']['payload']['input'])->toBe([
        'component_message_count' => 1,
        'snapshot_data_stored' => false,
    ])->and($profile['sections']['request']['payload']['request_type'])->toBe('livewire')
        ->and($livewire['summary'])
        ->component_count->toBe(1)
        ->activity_count->toBeGreaterThanOrEqual(2)
        ->and($livewire['payload']['components'])
        ->toHaveCount(1)
        ->{0}->name->toBe('host-counter')
        ->{0}->class->toBe(HostCounter::class)
        ->{0}->properties->{0}->toMatchArray([
            'path' => 'count',
            'type' => 'Integer',
            'php_type' => 'int',
            'server_value' => 1,
            'writable' => true,
            'write_allowed' => true,
        ])
        ->{0}->properties->{1}->toMatchArray([
            'path' => 'settings',
            'type' => 'Array',
            'server_value' => null,
            'writable' => false,
            'array_leaf_writable' => true,
            'write_allowed' => true,
        ])
        ->{0}->properties->{2}->toMatchArray([
            'path' => 'fixedLabel',
            'type' => 'String',
            'server_value' => 'Host counter',
            'writable' => false,
            'write_allowed' => false,
            'write_reason' => 'locked',
        ])
        ->and(array_column($livewire['payload']['activity'], 'type'))
        ->toContain('action', 'render')
        ->and(array_unique(array_column($livewire['payload']['activity'], 'component_id')))
        ->toBe([$livewire['payload']['components'][0]['id']])
        ->and(json_encode($profile))->not->toContain('wire:snapshot', 'checksum', 'newdebugbar.toolbar');
});

it('captures components mounted during a profiled page render', function () {
    $response = $this->get('/profiled-livewire', ['Accept' => 'text/html']);

    $response->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $livewire = $profile['sections']['livewire'];

    expect($livewire['summary'])
        ->component_count->toBe(1)
        ->activity_count->toBeGreaterThanOrEqual(1)
        ->and($livewire['payload']['components'][0])
        ->name->toBe('host-counter')
        ->parent_id->toBeNull()
        ->implementation->toBe('class')
        ->source->file->toBe('tests/Fixtures/HostCounter.php')
        ->and(array_column($livewire['payload']['activity'], 'type'))
        ->toContain('mount', 'render');
});

it('reports the original source for single-file components', function () {
    $response = $this->get('/profiled-livewire-single-file', ['Accept' => 'text/html']);

    $response->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $component = $profile['sections']['livewire']['payload']['components'][0];

    expect($component)
        ->name->toBe('host-functional-status')
        ->implementation->toBe('single_file')
        ->source->file->toBe('tests/Fixtures/views/components/⚡host-functional-status.blade.php')
        ->view->name->toBe('Same file')
        ->view->source->file->toBe('tests/Fixtures/views/components/⚡host-functional-status.blade.php');
});

it('preserves nested component instance identity and parentage', function () {
    $response = $this->get('/profiled-livewire-nested', ['Accept' => 'text/html']);

    $response->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $components = collect($profile['sections']['livewire']['payload']['components'])->keyBy('name');

    expect($components)
        ->toHaveCount(2)
        ->toHaveKeys(['host-counter-group', 'host-counter'])
        ->and($components['host-counter-group']['parent_id'])->toBeNull()
        ->and($components['host-counter']['parent_id'])->toBe($components['host-counter-group']['id'])
        ->and($components['host-counter']['id'])->not->toBe($components['host-counter-group']['id']);
});

it('captures validation failures handled inside host Livewire components', function () {
    $html = (string) app('livewire')->mount('host-validation-form');
    $snapshot = Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');
    $response = $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'updates' => [],
            'calls' => [['method' => 'save', 'params' => []]],
        ]],
    ], ['X-Livewire' => '1']);

    $response->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $validation = $profile['sections']['validation'];

    expect($validation['summary']['count'])->toBe(1)
        ->and($validation['payload']['items'][0])
        ->source->toBe('exception')
        ->fields->toBe(['email', 'name'])
        ->rules->email->toContain('Email')
        ->messages->name->toContain('The name field is required.')
        ->exception_status->toBe(422)
        ->response_status->toBe(200)
        ->callsite->file->toBe('tests/Fixtures/HostValidationForm.php')
        ->and($profile['sections']['exceptions']['summary']['count'])->toBe(0);
});

it('captures a changed Livewire error bag when framework propagation has stopped', function () {
    $html = (string) app('livewire')->mount('host-validation-form');
    $snapshot = Utils::extractAttributeDataFromHtml($html, 'wire:snapshot');
    $response = $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'updates' => [],
            'calls' => [['method' => 'addManualError', 'params' => []]],
        ]],
    ], ['X-Livewire' => '1']);

    $response->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $failures = collect($profile['sections']['livewire']['payload']['activity'])
        ->where('status', 'failed_validation');

    expect($profile['sections']['validation']['payload']['items'])
        ->toHaveCount(1)
        ->{0}->toMatchArray([
            'source' => 'livewire',
            'fields' => ['email'],
            'messages' => ['email' => ['This email was rejected by the component.']],
            'exception_class' => null,
        ])
        ->and($failures)->toHaveCount(1)
        ->and($failures->first())->toMatchArray([
            'component_name' => 'host-validation-form',
            'type' => 'failure',
            'status' => 'failed_validation',
            'fields' => ['email'],
        ]);
});

it('preserves host Livewire response bytes', function () use ($hostCounterMessage, $hostCounterSnapshot) {
    $payload = ['components' => [$hostCounterMessage($hostCounterSnapshot())]];
    $profiled = $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => '1']);

    config(['newdebugbar.enabled' => false]);
    $plain = $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => '1']);

    $profiled->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $plain->assertOk()->assertHeaderMissing('X-NewDebugBar-Profile');

    expect($profiled->getContent())->toBe($plain->getContent())
        ->and($profiled->headers->get('Content-Type'))->toBe($plain->headers->get('Content-Type'));
});

it('excludes debug toolbar updates from profiling and storage', function () {
    $host = $this->get('/profiled')->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $toolbar = (string) app('livewire')->mount('newdebugbar.toolbar', [
        'profileId' => $host->headers->get('X-NewDebugBar-Profile'),
    ]);
    $snapshot = Utils::extractAttributeDataFromHtml($toolbar, 'wire:snapshot');
    $storedBefore = count(File::files(config('newdebugbar.storage.path')));

    $response = $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'updates' => [],
            'calls' => [['method' => 'loadSection', 'params' => ['request']]],
        ]],
    ], ['X-Livewire' => '1']);

    $response->assertOk()->assertHeaderMissing('X-NewDebugBar-Profile');

    expect(count(File::files(config('newdebugbar.storage.path'))))->toBe($storedBefore);
});

it('keeps host work profiled when its update shares a request with the toolbar', function () use ($hostCounterMessage, $hostCounterSnapshot) {
    $host = $this->get('/profiled')->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $toolbar = (string) app('livewire')->mount('newdebugbar.toolbar', [
        'profileId' => $host->headers->get('X-NewDebugBar-Profile'),
    ]);
    $snapshot = Utils::extractAttributeDataFromHtml($toolbar, 'wire:snapshot');

    $response = $this->postJson(app('livewire')->getUpdateUri(), [
        'components' => [
            $hostCounterMessage($hostCounterSnapshot()),
            [
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'updates' => [],
                'calls' => [['method' => 'loadSection', 'params' => ['request']]],
            ],
        ],
    ], ['X-Livewire' => '1'])->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $hostSnapshot = json_decode($response->json('components.0.snapshot'), true, flags: JSON_THROW_ON_ERROR);

    expect($hostSnapshot['data']['count'])->toBe(1)
        ->and($response->json('components'))->toHaveCount(2)
        ->and(array_column($profile['sections']['livewire']['payload']['components'], 'name'))->toBe(['host-counter'])
        ->and($profile['sections']['request']['payload']['request_type'])->toBe('livewire');
});

<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Redis\Events\CommandFailed;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Presentation\ProfileSummaryPresenter;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\Mail\ProfiledMailable;
use NewDebugBar\Tests\Fixtures\Models\Client;
use NewDebugBar\Tests\Fixtures\Models\JobActivity;
use NewDebugBar\Tests\Fixtures\Models\ProfiledModel;
use NewDebugBar\Tests\Fixtures\Notifications\ProfiledNotifiable;
use NewDebugBar\Tests\Fixtures\Notifications\ProfiledNotification;
use NewDebugBar\Tests\Fixtures\Redis\ProfiledRedisCaller;

it('captures a local web request and its Laravel activity', function () {
    $route = app('router')->getRoutes()->match(request()->create('/profiled'));

    expect(app()->environment())->toBe('testing')
        ->and(app()->bound('middleware.disable'))->toBeFalse()
        ->and(config('newdebugbar.environments'))->toBe(['testing'])
        ->and(app('router')->gatherRouteMiddleware($route))->toContain(ProfileRequest::class);

    $response = $this->get('/profiled?token=visible', [
        'Accept' => 'text/html',
        'Authorization' => 'Bearer visible',
    ]);

    $response
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertSee('data-testid="host-page"', false)
        ->assertSee('id="newdebugbar"', false)
        ->assertSee('/__newdebugbar/assets/newdebugbar.css', false)
        ->assertSee('/__newdebugbar/assets/newdebugbar.js', false)
        ->assertSee('id="newdebugbar-critical-css"', false)
        ->assertDontSee('data-navigate-track', false)
        ->assertSee('wire:key="newdebugbar-toolbar"', false)
        ->assertSee('data-update-uri', false);

    expect(substr_count((string) $response->getContent(), '<!-- Livewire Styles -->'))->toBe(1)
        ->and(substr_count((string) $response->getContent(), 'data-update-uri='))->toBe(1);

    $files = File::files(config('newdebugbar.storage.path'));

    expect($files)->toHaveCount(1);

    $profile = json_decode(File::get($files[0]->getPathname()), true, flags: JSON_THROW_ON_ERROR);

    expect($profile)
        ->schema_version->toBe(2)
        ->environment->toBe('testing')
        ->inspectors->request->summary->method->toBe('GET')
        ->inspectors->request->summary->status->toBe(200)
        ->inspectors->request->payload->path->toBe('/profiled')
        ->inspectors->request->payload->url->not->toContain('visible')
        ->inspectors->request->payload->url->toContain('token=%5Bredacted%5D')
        ->inspectors->request->payload->query->token->toBe('[redacted]')
        ->inspectors->request->payload->headers->authorization->toBe('[redacted]')
        ->inspectors->request->payload->content_type->toContain('text/html')
        ->inspectors->request->payload->request_size_bytes->toBe(0)
        ->inspectors->request->payload->response_size_bytes->toBeGreaterThan(0)
        ->inspectors->request->payload->session_present->toBeFalse()
        ->inspectors->request->payload->authenticated->toBeFalse()
        ->inspectors->overview->payload->runtime->environment->toBe('testing')
        ->inspectors->overview->payload->runtime->laravel->toBe(app()->version())
        ->inspectors->overview->payload->drivers->database->toBe(config('database.default'))
        ->inspectors->queries->summary->count->toBeGreaterThanOrEqual(1)
        ->inspectors->queries->payload->items->{0}->driver->toBe('sqlite')
        ->inspectors->models->summary->count->toBeGreaterThanOrEqual(1)
        ->inspectors->cache->summary->hits->toBe(1)
        ->inspectors->cache->summary->misses->toBe(1)
        ->inspectors->logs->summary->count->toBe(1)
        ->inspectors->events->summary->count->toBeGreaterThanOrEqual(1);

    expect(array_column($profile['inspectors']['models']['payload']['items'], 'event'))
        ->toContain('retrieved');

    expect($profile['inspectors']['logs']['payload']['items'][0]['callsite'])
        ->toMatchArray(['file' => 'tests/Support/DefinesTestApplication.php'])
        ->and($profile['inspectors']['logs']['payload']['items'][0]['stack'])->not->toBeEmpty();

    foreach ($profile['inspectors'] as $inspector) {
        foreach ($inspector['payload']['items'] ?? [] as $item) {
            expect($item['at_ms'])->toBeNumeric()->toBeGreaterThanOrEqual(0);
        }
    }
});

it('captures bounded cache timing source value and failure metadata', function () {
    $response = $this->get('/profiled-cache-rich', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $inspector = $profile['inspectors']['cache'];
    $items = collect($inspector['payload']['items']);
    $write = $items->firstWhere('key', 'trip:kyoto:weather');
    $hit = $items->where('key', 'trip:kyoto:weather')->firstWhere('operation', 'hit');
    $batchWrites = $items->where('operation', 'write')->where('duration_scope', 'batch')->values();
    $failureCount = (int) class_exists(KeyWriteFailed::class)
        + (int) class_exists(KeyForgetFailed::class)
        + (int) class_exists(CacheFailedOver::class);
    $flushCount = (int) class_exists(CacheFlushed::class);
    $cacheTimingAvailable = class_exists(WritingKey::class);

    expect($inspector['summary'])
        ->hits->toBeGreaterThanOrEqual(2)
        ->misses->toBeGreaterThanOrEqual(3)
        ->writes->toBeGreaterThanOrEqual(4)
        ->forgets->toBeGreaterThanOrEqual(2)
        ->flushes->toBe($flushCount)
        ->failures->toBe($failureCount)
        ->and($write)
        ->driver->toBe('array')
        ->callsite->file->toBe('tests/Support/DefinesTestApplication.php')
        ->stack->not->toBeEmpty()
        ->and($write['value'])->toBe(['high' => 24, 'low' => 15])
        ->and($hit['value'])->toBe(['high' => 24, 'low' => 15]);

    if ($cacheTimingAvailable) {
        expect($inspector['summary'])
            ->timed_count->toBeGreaterThan(0)
            ->duration_ms->toBeGreaterThanOrEqual(0.0)
            ->and($write)
            ->duration_ms->toBeNumeric()->toBeGreaterThanOrEqual(0)
            ->duration_scope->toBe('operation')
            ->and($batchWrites)->toHaveCount(2)
            ->and($batchWrites->pluck('duration_id')->unique())->toHaveCount(1)
            ->and($batchWrites->pluck('batch_size')->unique()->all())->toBe([2])
            ->and($batchWrites->pluck('value')->all())->toBe(['A compact autumn itinerary', true]);
    } else {
        expect($inspector['summary'])
            ->timed_count->toBe(0)
            ->duration_ms->toEqual(0)
            ->and($write)
            ->duration_ms->toBeNull()
            ->duration_scope->toBeNull()
            ->and($batchWrites)->toBeEmpty();
    }

    if ($failureCount > 0) {
        expect($items->where('failed', true))->toHaveCount($failureCount);
    }
});

it('presents model activity as useful record loads', function () {
    $response = $this->get('/profiled-models', ['Accept' => 'text/html'])->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $models = app(ProfilePresenter::class)->present($stored)['inspectors']['models'];

    expect($models['summary'])
        ->retrieval_count->toBe(44)
        ->distinct_record_count->toBe(24)
        ->repeated_load_count->toBe(20)
        ->model_change_count->toBe(0)
        ->activity_count->toBe(44)
        ->intermediate_lifecycle_event_count->toBe(0)
        ->unknown_source_activity_count->toBe(0)
        ->and(array_map(
            fn (array $group): array => [
                class_basename($group['model']),
                $group['load_count'],
                $group['record_count'],
                $group['repeated_load_count'],
                $group['source_count'],
            ],
            $models['payload']['model_groups'],
        ))->toBe([
            ['StudioJob', 14, 6, 8, 1],
            ['Client', 10, 4, 6, 1],
            ['ProofVersion', 8, 5, 3, 1],
            ['User', 5, 2, 3, 1],
            ['JobActivity', 7, 7, 0, 1],
        ]);
});

it('captures model sources and folds lifecycle callbacks into logical write operations', function () {
    $response = $this->get('/profiled-models?changes=1', ['Accept' => 'text/html'])->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $rawModels = collect($stored['inspectors']['models']['payload']['items']);
    $clientLifecycle = $rawModels
        ->where('model', Client::class)
        ->whereIn('event', ['updating', 'updated', 'saved'])
        ->values();
    $updated = $clientLifecycle->firstWhere('event', 'updated');
    $models = app(ProfilePresenter::class)->present($stored)['inspectors']['models'];

    expect($clientLifecycle)->toHaveCount(3)
        ->and($clientLifecycle->pluck('operation_id')->unique()->filter())->toHaveCount(1)
        ->and($updated['key_name'])->toBe('id')
        ->and($updated['changes'])->toBe([
            'status' => 'approved',
            'api_token' => '[redacted]',
        ])
        ->and($updated['callsite']['file'])->toBe('tests/Support/DefinesTestApplication.php')
        ->and($updated['callsite']['line'])->toBeInt()
        ->and($models['summary'])
        ->retrieval_count->toBe(44)
        ->model_change_count->toBe(4)
        ->activity_count->toBe(48)
        ->intermediate_lifecycle_event_count->toBe(7)
        ->model_change_events->toBe([
            'updated' => 1,
            'created' => 1,
            'deleted' => 1,
            'trashed' => 1,
        ])
        ->and($models['payload']['model_groups'][0]['change_operations'][0])
        ->event->toBe('updated')
        ->change_attribute_count->toBe(2)
        ->lifecycle_events->toBe([
            'updating' => 1,
            'updated' => 1,
            'saved' => 1,
        ])
        ->changes->toBe([
            'status' => 'approved',
            'api_token' => '[redacted]',
        ]);
});

it('captures compiled Blade provenance and correlates model activity with an exact-source query', function () {
    $response = $this->get('/profiled-models?compiled=1', ['Accept' => 'text/html'])->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);
    $rawModel = collect($stored['inspectors']['models']['payload']['items'])
        ->first(fn (array $item): bool => ($item['key'] ?? null) === 77);
    $rawQuery = collect($stored['inspectors']['queries']['payload']['items'])
        ->first(fn (array $item): bool => str_contains((string) ($item['sql'] ?? ''), 'select 77 as id'));
    $group = collect($profile['inspectors']['models']['payload']['model_groups'])
        ->firstWhere('model', JobActivity::class);
    $source = collect($group['sources'])
        ->first(fn (array $source): bool => ($source['callsite']['kind'] ?? null) === 'compiled_view');

    expect($rawModel['callsite'])
        ->kind->toBe('compiled_view')
        ->template_file->toBe('tests/Fixtures/views/model-compiled.blade.php')
        ->and($rawQuery['callsite'])->toBe($rawModel['callsite'])
        ->and($source)
        ->not->toBeNull()
        ->query_count->toBe(1)
        ->query_read_count->toBe(1)
        ->and(array_column($group['guidance'], 'type'))
        ->toContain('compiled_blade_source', 'query_correlation');
});

it('captures bounded redacted outbound HTTP request and response evidence', function () {
    $failedConnection = method_exists(Factory::class, 'failedConnection')
        ? Http::failedConnection('private connection details')
        : fn ($request) => Create::rejectionFor(new ConnectException(
            'private connection details',
            $request->toPsrRequest(),
        ));

    Http::fake([
        'api.example.test/*' => Http::response(
            ['private' => 'response-body'],
            202,
            ['Set-Cookie' => 'session=private-response-cookie'],
        ),
        'down.example.test/*' => $failedConnection,
    ]);

    $response = $this->get('/profiled-http-client', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $inspector = $profile['inspectors']['http_client'];

    expect($inspector['summary'])
        ->count->toBe(2)
        ->failed_count->toBe(1)
        ->duration_ms->toBeFloat()
        ->and($inspector['payload']['items'][0])
        ->method->toBe('GET')
        ->url->toBe('https://api.example.test/v1/patients?token=%5Bredacted%5D&limit=5')
        ->status->toBe(202)
        ->failed->toBeFalse()
        ->request->headers->Authorization->toBe('[redacted]')
        ->request->headers->{'X-Trace'}->toBe(['trace-1'])
        ->request->body->toBeNull()
        ->response->headers->{'Set-Cookie'}->toBe('[redacted]')
        ->response->body->toBe(['private' => 'response-body'])
        ->stack->not->toBeEmpty()
        ->and($inspector['payload']['items'][1])
        ->method->toBe('POST')
        ->url->toBe('https://down.example.test/v1/sync?api_key=%5Bredacted%5D')
        ->status->toBeNull()
        ->failed->toBeTrue()
        ->exception_class->toBe(ConnectionException::class)
        ->request->headers->Cookie->toBe('[redacted]')
        ->request->body->toBe([
            'token' => '[redacted]',
            'patient' => 'visible-patient',
        ])
        ->response->toBeNull()
        ->and(json_encode($inspector))->not->toContain(
            'private-token',
            'private-key',
            'private-bearer',
            'private-cookie',
            'private-response-cookie',
            'private-body-token',
            'connection details',
        );
});

it('captures queued dispatches and synchronous execution without job data', function () {
    $response = $this->get('/profiled-queue', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $inspector = $profile['inspectors']['queue'];
    $presented = app(ProfilePresenter::class)->present($profile);
    $activityStatuses = collect($presented['background_activity']['items'])->pluck('status')->all();

    expect($inspector['summary'])
        ->count->toBe(3)
        ->queued_count->toBe(1)
        ->executed_count->toBe(2)
        ->failed_count->toBe(1)
        ->duration_ms->toBeFloat()
        ->and($inspector['payload']['items'][0])
        ->kind->toBe('queued')
        ->connection->toBe('redis')
        ->queue->toBe('emails')
        ->job_id->toBe('job-1')
        ->delay_seconds->toBe(5)
        ->and($inspector['payload']['items'][1])
        ->kind->toBe('executed')
        ->connection->toBe('sync')
        ->queue->toBe('sync')
        ->duration_ms->toBeGreaterThanOrEqual(0)
        ->and($inspector['payload']['items'][2])
        ->kind->toBe('failed')
        ->status->toBe('failed')
        ->exception_class->toBe(RuntimeException::class)
        ->and($activityStatuses)->toBe(['delayed'])
        ->and(json_encode($inspector))->not->toContain('private queued value', 'queued payload', 'private sync value', 'private failed value', 'private failure message');
});

it('shows queued communication facts and refreshes their correlated outcome', function () {
    $response = $this->get('/profiled-queued-communications', ['Accept' => 'text/html'])->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $initial = app(ProfilePresenter::class)->present($stored);
    $summary = app(ProfileSummaryPresenter::class)->present($initial);
    $queuedMail = $initial['inspectors']['mail']['payload']['items'][0];
    $queuedNotification = $initial['inspectors']['notifications']['payload']['items'][0];

    expect($summary)
        ->background_pending->toBeTrue()
        ->background_activity_count->toBe(2)
        ->and($queuedMail)
        ->status->toBe('delayed')
        ->source->toBe(ProfiledMailable::class)
        ->connection->toBe('redis')
        ->queue->toBe('mail-delayed')
        ->delay_seconds->toBe(30)
        ->recipient_count->toBe(1)
        ->and($queuedNotification)
        ->status->toBe('queued')
        ->notification->toBe(ProfiledNotification::class)
        ->channel->toBe('mail')
        ->notifiable_types->toBe([ProfiledNotifiable::class])
        ->notifiable_count->toBe(1)
        ->and(json_encode($initial))->not->toContain(
            'Private queued subject',
            'Private queued heading',
            'Private queued body',
            'private queued notification',
            'private-recipient@example.test',
            'private-notifiable@example.test',
        );

    $workerProfileId = (string) Str::uuid();
    app(BackgroundActivityStore::class)->recordOutcome(
        $queuedMail['correlation_key'],
        'sent',
        $workerProfileId,
        1,
    );
    $refreshed = app(ProfilePresenter::class)->present($stored);
    $refreshedSummary = app(ProfileSummaryPresenter::class)->present($refreshed);

    expect($refreshed['inspectors']['mail']['payload']['items'][0])
        ->status->toBe('sent')
        ->worker_profile_id->toBe($workerProfileId)
        ->attempts->toHaveCount(1)
        ->and($refreshedSummary['related_profile_ids'])->toContain($workerProfileId)
        ->and($refreshedSummary['background_pending'])->toBeTrue();
});

it('captures mail previews and notification shape by default', function () {
    $response = $this->get('/profiled-messages', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $mail = $profile['inspectors']['mail'];
    $notifications = $profile['inspectors']['notifications'];

    expect($mail['summary'])
        ->count->toBe(1)
        ->recipient_count->toBe(2)
        ->attachment_count->toBe(1)
        ->duration_ms->toBeFloat()
        ->and($mail['payload']['items'][0])
        ->recipient_count->toBe(2)
        ->attachment_count->toBe(1)
        ->has_text->toBeTrue()
        ->status->toBe('sent')
        ->mailer->toBe('array')
        ->transport->toBe('array')
        ->transport_message_id->toBeString()
        ->callsite->file->toBe('tests/Support/DefinesTestApplication.php')
        ->stack->not->toBeEmpty()
        ->preview->subject->toBe('private subject')
        ->preview->from->toBe(['private-sender@example.test'])
        ->preview->to->toBe(['private-recipient@example.test'])
        ->preview->cc->toBe(['private-copy@example.test'])
        ->preview->text->toBe('private body')
        ->preview->attachments_omitted->toBe(0)
        ->preview->attachments->toHaveCount(1)
        ->preview->attachments->{0}->size_bytes->toBe(18)
        ->preview->attachments->{0}->body_base64->toBe(base64_encode('private attachment'))
        ->and($notifications['summary'])
        ->count->toBe(2)
        ->notification_count->toBe(1)
        ->failed_notification_count->toBe(1)
        ->sent_count->toBe(1)
        ->failed_count->toBe(1)
        ->duration_ms->toBeGreaterThanOrEqual(0)
        ->and($notifications['payload']['items'][0])
        ->status->toBe('sent')
        ->channel->toBe('mail')
        ->response->toBe(['private response'])
        ->notification_data->privateValue->toBe('private notification data')
        ->callsite->file->toBe('tests/Support/DefinesTestApplication.php')
        ->notification_source->file->toBe('tests/Fixtures/Notifications/ProfiledNotification.php')
        ->and($notifications['payload']['items'][1])
        ->status->toBe('failed')
        ->channel->toBe('slack')
        ->failure_data->toBe(['private' => 'failure data'])
        ->group_id->toBe($notifications['payload']['items'][0]['group_id'])
        ->and(json_encode([$mail, $notifications]))->toContain(base64_encode('private attachment'));
});

it('groups notification channel attempts and keeps delivery evidence', function () {
    $response = $this->get('/profiled-notifications-rich', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $mail = $profile['inspectors']['mail'];
    $notifications = $profile['inspectors']['notifications'];
    $items = $notifications['payload']['items'];

    expect($notifications['summary'])
        ->count->toBe(3)
        ->delivery_count->toBe(3)
        ->notification_count->toBe(2)
        ->failed_notification_count->toBe(1)
        ->sent_count->toBe(2)
        ->failed_count->toBe(1)
        ->duration_ms->toBeGreaterThan(0)
        ->and($items)->toHaveCount(3)
        ->and($items[0])
        ->status->toBe('sent')
        ->channel->toBe('mail')
        ->queueable->toBeTrue()
        ->notifiable_id->toBe(1042)
        ->notifiable_name->toBe('Elise Martin')
        ->destination->toBe('elise@example.test')
        ->mail_message_id->toBe($mail['payload']['items'][0]['transport_message_id'])
        ->notification_data->privateValue->toBe('Kyoto autumn')
        ->notification_data->not->toHaveKeys([
            'connection',
            'queue',
            'delay',
            'afterCommit',
            'middleware',
            'chained',
        ])
        ->notification_source->file->toBe('tests/Fixtures/Notifications/ProfiledNotification.php')
        ->callsite->file->toBe('tests/Support/DefinesTestApplication.php')
        ->and($items[1])
        ->status->toBe('failed')
        ->channel->toBe('profiled-sms')
        ->destination->toBe('+32 470 12 34 56')
        ->exception_class->toBe(RuntimeException::class)
        ->exception_message->toBe('Traveler phone number is not verified.')
        ->exception_location->file->toBe('tests/Fixtures/Notifications/ProfiledNotificationChannel.php')
        ->group_id->toBe($items[0]['group_id'])
        ->and($items[2])
        ->status->toBe('sent')
        ->channel->toBe('profiled-push')
        ->destination->toBe('device:journey-1042')
        ->response->toBe([
            'provider' => 'Profiled Push',
            'message_id' => 'push-1042',
        ])
        ->group_id->not->toBe($items[0]['group_id']);
});

it('captures crowded named mail recipients for compact inspection', function () {
    $response = $this->get('/profiled-mail-rich', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $recipients = $profile['inspectors']['mail']['payload']['items'][0]['preview']['to'];

    expect($recipients)
        ->toHaveCount(6)
        ->and(implode(' ', $recipients))->toContain(
            'Taylor Reed',
            'taylor@example.test',
            'Alexandra Montgomery',
            'alexandra.montgomery@example.test',
            'Arthur Moreau',
            'arthur@example.test',
        );
});

it('captures direct Redis commands and removes cache command duplicates', function () {
    $response = $this->get('/profiled-redis', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $redis = $profile['inspectors']['redis'];
    $cache = $profile['inspectors']['cache'];
    $storeAware = property_exists(CacheEvent::class, 'storeName');
    $flushAware = class_exists(CacheFlushed::class);
    $failureAware = class_exists(CommandFailed::class);
    $expectedCommands = ['GET'];
    $expectedDuration = 1.25;

    if (! $storeAware) {
        $expectedCommands[] = 'SETEX';
        $expectedDuration += 0.4;
    }

    if (! $flushAware) {
        $expectedCommands[] = 'FLUSHDB';
        $expectedDuration += 0.5;
    }

    if ($failureAware) {
        $expectedCommands[] = 'HGET';
    }

    expect($redis['summary'])
        ->count->toBe(count($expectedCommands))
        ->duration_ms->toBe(round($expectedDuration, 2))
        ->failed_count->toBe($failureAware ? 1 : 0)
        ->and(array_column($redis['payload']['items'], 'command'))->toBe($expectedCommands)
        ->and(array_column($cache['payload']['items'], 'operation'))->toBe(
            $flushAware ? ['write', 'flush'] : ['write'],
        )
        ->and($redis['payload']['items'][0])
        ->command->toBe('GET')
        ->connection->toBe('default')
        ->key_count->toBe(1)
        ->key_hashes->toBe([substr(hash('sha256', 'private-direct-key'), 0, 16)])
        ->keys->toBe(['private-direct-key'])
        ->key_policy->toBe('full')
        ->and($cache['payload']['items'][0])
        ->tag_count->toBe(2)
        ->tag_hashes->toBe([
            substr(hash('sha256', 'tenant:private-clinic'), 0, 16),
            substr(hash('sha256', 'patient:private-patient'), 0, 16),
        ])
        ->key->toBe('private-cache-key')
        ->tags->toBe(['tenant:private-clinic', 'patient:private-patient'])
        ->and($cache['payload']['items'][0]['value'])->toBe('private-cache-value')
        ->and(json_encode($redis))->not->toContain(
            'private-cache-value',
            'private-field',
            'private Redis failure',
        );

    $failedRedis = collect($redis['payload']['items'])->firstWhere('command', 'HGET');

    if ($failureAware) {
        expect($failedRedis)
            ->failed->toBeTrue()
            ->exception_class->toBe(RuntimeException::class);
    } else {
        expect($failedRedis)->toBeNull();
    }
});

it('captures application call sites from real Redis client success and failure events', function () {
    $response = $this->get('/profiled-redis-client', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $items = $profile['inspectors']['redis']['payload']['items'];
    $read = new ReflectionMethod(ProfiledRedisCaller::class, 'read');

    expect($items[0])
        ->command->toBe('GET')
        ->connection->toBe('default')
        ->keys->toBe(['private-client-key'])
        ->not->toHaveKeys(['parameters', 'result', 'value'])
        ->and($items[0]['callsite'])
        ->file->toBe('tests/Fixtures/Redis/ProfiledRedisCaller.php')
        ->and($items[0]['callsite']['line'])
        ->toBeGreaterThanOrEqual($read->getStartLine())
        ->toBeLessThanOrEqual($read->getEndLine())
        ->and(json_encode($items))->not->toContain('private Redis result', 'private-client-field');

    if (class_exists(CommandFailed::class)) {
        $readHash = new ReflectionMethod(ProfiledRedisCaller::class, 'readHash');

        expect($items)->toHaveCount(2)
            ->and($items[1])
            ->command->toBe('HGET')
            ->failed->toBeTrue()
            ->exception_class->toBe(RuntimeException::class)
            ->keys->toBe(['private-client-hash'])
            ->not->toHaveKeys(['parameters', 'result', 'value'])
            ->and($items[1]['callsite'])
            ->file->toBe('tests/Fixtures/Redis/ProfiledRedisCaller.php')
            ->and($items[1]['callsite']['line'])
            ->toBeGreaterThanOrEqual($readHash->getStartLine())
            ->toBeLessThanOrEqual($readHash->getEndLine());
    } else {
        expect($items)->toHaveCount(1);
    }
});

it('keeps direct Redis commands when a non Redis cache store emits a similar operation', function () {
    $response = $this->get('/profiled-redis-independent-cache', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $redis = $profile['inspectors']['redis'];
    $cache = $profile['inspectors']['cache'];

    expect($redis['summary']['count'])->toBe(1)
        ->and($redis['payload']['items'][0])
        ->command->toBe('GET')
        ->key_hashes->toBe([substr(hash('sha256', 'private-direct-key'), 0, 16)])
        ->keys->toBe(['private-direct-key'])
        ->and($cache['summary']['misses'])->toBe(1);
});

it('hashes bounded cache and Redis keys under the explicit hash policy', function () {
    config()->set('newdebugbar.collection.key_policy', 'hash');

    $response = $this->get('/profiled-redis', ['Accept' => 'text/html'])->assertOk();
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $cacheWrite = collect($profile['inspectors']['cache']['payload']['items'])->firstWhere('operation', 'write');
    $cacheFlush = collect($profile['inspectors']['cache']['payload']['items'])->firstWhere('operation', 'flush');
    $redisGet = collect($profile['inspectors']['redis']['payload']['items'])->firstWhere('command', 'GET');

    expect($cacheWrite)
        ->key_policy->toBe('hash')
        ->key->toBeNull()
        ->tags->toBe([])
        ->and($redisGet)
        ->key_policy->toBe('hash')
        ->keys->toBe([])
        ->and(json_encode([$cacheWrite, $redisGet]))->not->toContain(
            'private-cache-key',
            'private-direct-key',
            'private-clinic',
            'private-patient',
        );

    if (class_exists(CacheFlushed::class)) {
        expect($cacheFlush)
            ->key_policy->toBe('hash')
            ->tags->toBe([]);
    } else {
        expect($cacheFlush)->toBeNull();
    }
});

it('isolates mutable collector state between application lifecycles', function () {
    $first = app(ProfileManager::class);

    $this->get('/profiled', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile');

    app()->forgetScopedInstances();
    $second = app(ProfileManager::class);

    expect($second)->not->toBe($first)
        ->and($second->isCollecting())->toBeFalse();

    $this->get('/profiled-next', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile');

    $profiles = collect(File::files(config('newdebugbar.storage.path')))
        ->map(fn ($file) => json_decode(File::get($file->getPathname()), true, flags: JSON_THROW_ON_ERROR));

    expect($profiles)->toHaveCount(2)
        ->and($profiles->pluck('inspectors.request.payload.path')->sort()->values()->all())
        ->toBe(['/profiled', '/profiled-next']);
});

it('captures nested input without retaining uploaded files', function () {
    $response = $this->post('/profiled-input', [
        'clinic' => [
            'name' => 'Example Clinic',
            'document' => UploadedFile::fake()->create('private.pdf'),
        ],
    ], ['Accept' => 'text/html'])->assertOk();

    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));

    expect($profile['inspectors']['request']['payload']['input'])->toBe([
        'clinic' => ['name' => 'Example Clinic'],
    ]);
});

it('profiles partial models without requiring their primary key', function () {
    Model::preventAccessingMissingAttributes();

    try {
        $this->get('/profiled-partial-model')
            ->assertOk()
            ->assertSee('Partial model');
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }

    $files = File::files(config('newdebugbar.storage.path'));
    $profile = json_decode(File::get($files[0]->getPathname()), true, flags: JSON_THROW_ON_ERROR);
    $models = $profile['inspectors']['models']['payload']['items'];
    $partialModel = collect($models)->first(
        fn (array $model): bool => $model['model'] === ProfiledModel::class
            && $model['event'] === 'retrieved',
    );

    expect($partialModel)->not->toBeNull()
        ->and($partialModel['event'])->toBe('retrieved')
        ->and($partialModel['key'])->toBeNull();
});

it('captures structured log channels timing context and related exceptions', function () {
    $response = $this->get('/profiled-logs', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile');
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);
    $logs = $profile['inspectors']['logs'];
    $audit = collect($logs['payload']['items'])->firstWhere('message', 'Audit channel accepted the refresh.');
    $failed = collect($logs['payload']['items'])->firstWhere('message', 'Rail reservation refresh failed.');

    expect($logs['summary'])
        ->count->toBe(26)
        ->errors->toBe(2)
        ->attention_count->toBe(5)
        ->group_count->toBe(24)
        ->repeated_count->toBe(2)
        ->levels->toBe([
            'debug' => 19,
            'info' => 1,
            'notice' => 1,
            'warning' => 3,
            'error' => 1,
            'critical' => 1,
        ])
        ->and($audit)
        ->channel->toBe('newdebugbar-audit')
        ->context_fields->toHaveCount(2)
        ->and($failed)
        ->channel->not->toBeNull()
        ->context->toBe(['trip_id' => 1])
        ->related_exception->toMatchArray([
            'class' => RuntimeException::class,
            'message' => 'The rail partner rejected reservation KYO-441.',
        ])
        ->occurred_at->toMatch('/^\d{4}-\d{2}-\d{2}T/')
        ->and($failed['context'])->not->toHaveKey('exception')
        ->and($profile['inspectors']['exceptions']['summary']['count'])->toBe(1)
        ->and($logs['payload']['groups'][3]['repeat_count'])->toBe(3)
        ->and(array_column($logs['payload']['groups'][3]['occurrences'], 'sequence'))->toBe([4, 5, 6]);
});

it('does not create package deprecation logs when no queue job context is active', function () {
    $response = $this->get('/profiled-rich', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $messages = array_column($profile['inspectors']['logs']['payload']['items'] ?? [], 'message');

    expect(implode("\n", $messages))->not->toContain('Using null as an array offset');
});

<?php

namespace NewDebugBar\Tests\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Auth\GenericUser;
use Illuminate\Cache\Events\CacheEvent;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\Events\CacheFlushed;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Redis\Events\CommandFailed;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Tests\Fixtures\Events\ProfiledApplicationEvent;
use NewDebugBar\Tests\Fixtures\Events\ProfiledApplicationListener;
use NewDebugBar\Tests\Fixtures\Events\ProfiledQueuedApplicationListener;
use NewDebugBar\Tests\Fixtures\HostCounter;
use NewDebugBar\Tests\Fixtures\HostCounterGroup;
use NewDebugBar\Tests\Fixtures\HostSessionRotator;
use NewDebugBar\Tests\Fixtures\HostValidationForm;
use NewDebugBar\Tests\Fixtures\Jobs\ProfiledAfterResponseMailJob;
use NewDebugBar\Tests\Fixtures\Jobs\ProfiledFailingJob;
use NewDebugBar\Tests\Fixtures\Jobs\ProfiledJob;
use NewDebugBar\Tests\Fixtures\Mail\ProfiledMailable;
use NewDebugBar\Tests\Fixtures\Models\Client;
use NewDebugBar\Tests\Fixtures\Models\JobActivity;
use NewDebugBar\Tests\Fixtures\Models\ProfiledModel;
use NewDebugBar\Tests\Fixtures\Models\ProofVersion;
use NewDebugBar\Tests\Fixtures\Models\StudioJob;
use NewDebugBar\Tests\Fixtures\Models\User;
use NewDebugBar\Tests\Fixtures\Notifications\ProfiledNotifiable;
use NewDebugBar\Tests\Fixtures\Notifications\ProfiledNotification;
use NewDebugBar\Tests\Fixtures\Notifications\ProfiledNotificationChannel;
use NewDebugBar\Tests\Fixtures\Policies\ProfiledAuthorizationPolicy;
use NewDebugBar\Tests\Fixtures\Redis\ProfiledRedisCaller;
use NewDebugBar\Tests\Fixtures\Redis\ProfiledRedisConnection;
use NewDebugBar\Tests\Fixtures\VerifyLivewireRequestForgery;

trait DefinesTestApplication
{
    protected function defineRoutes($router): void
    {
        Livewire::component('host-counter', HostCounter::class);
        Livewire::component('host-counter-group', HostCounterGroup::class);
        Livewire::component('host-session-rotator', HostSessionRotator::class);
        Livewire::component('host-validation-form', HostValidationForm::class);
        Livewire::addLocation(viewPath: dirname(__DIR__).'/Fixtures/views/components');
        $router->pushMiddlewareToGroup('web', VerifyLivewireRequestForgery::class);

        foreach ([StudioJob::class, Client::class, ProofVersion::class, JobActivity::class, User::class] as $modelClass) {
            new $modelClass;
        }

        $profiledPage = function (string $title, string $nextPath, string $nextLabel) {
            foreach ([1, 2, 3] as $number) {
                DB::select('select ? as number', [$number]);
            }
            Cache::put('dashboard', 'ready', 60);
            Cache::get('dashboard');
            Cache::get('missing');
            Event::dispatch('application.ready', [['safe' => true]]);
            Event::dispatch('eloquent.retrieved: '.ProfiledModel::class, [new ProfiledModel]);
            Log::info('Profiled request completed', ['authorization' => 'hidden']);

            return response(<<<HTML
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>{$title}</title></head>
                    <body>
                        <main>
                            <h1 data-testid="host-page">{$title}</h1>
                            <a href="{$nextPath}" wire:navigate data-testid="host-navigation">{$nextLabel}</a>
                        </main>
                    </body>
                </html>
                HTML);
        };

        $router->middleware(ProfileRequest::class)->get(
            '/profiled',
            fn () => $profiledPage('First request', '/profiled-next', 'Next request'),
        );

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-next',
            fn () => $profiledPage('Second request', '/profiled', 'Previous request'),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-timeline-long', function () {
            foreach (range(1, 110) as $number) {
                DB::select('select ? as timeline_number', [$number]);
            }

            return response(<<<'HTML'
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Long timeline</title></head>
                    <body><main><h1 data-testid="host-page">Long timeline</h1></main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-logs', function () {
            Log::debug('Preparing the journey workspace.', ['trip_id' => 1]);
            Log::channel('newdebugbar-audit')->info('Audit channel accepted the refresh.', [
                'trip_id' => 1,
                'actor' => ['type' => 'planner', 'id' => 7],
            ]);
            Log::notice("Partner response is delayed.\nThe cached itinerary remains available.");

            foreach (range(1, 3) as $attempt) {
                Log::warning('Rail reservation refresh needs attention.', ['trip_id' => 1, 'attempt' => 'final']);
            }

            Log::error('Rail reservation refresh failed.', [
                'trip_id' => 1,
                'exception' => new \RuntimeException('The rail partner rejected reservation KYO-441.'),
            ]);
            Log::critical(str_repeat('Critical itinerary integrity warning. ', 24), ['trip_id' => 1]);

            foreach (range(1, 18) as $index) {
                Log::debug('Journey step checked.', ['step' => $index]);
            }

            return response('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><main>Logs fixture</main></body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-queries-rich', function () {
            DB::statement('drop table if exists ndb_query_workspace');
            DB::statement('create table ndb_query_workspace (id integer primary key, name varchar(100), active integer)');
            DB::insert('insert into ndb_query_workspace (id, name, active) values (?, ?, ?), (?, ?, ?)', [
                1, 'Kyoto', 1, 2, 'Osaka', 0,
            ]);
            DB::update('update ndb_query_workspace set active = ? where id > ?', [1, 99]);

            foreach (range(1, 8) as $id) {
                DB::select('select * from ndb_query_workspace where id = ?', [$id]);
            }

            DB::select(<<<'SQL'
                select id as itinerary_identifier,
                       name as itinerary_name_with_a_deliberately_long_alias,
                       active as itinerary_activity_status
                from ndb_query_workspace
                where name like ?
                order by itinerary_name_with_a_deliberately_long_alias asc
                SQL, ['%o%']);

            config(['database.connections.query_replica' => config('database.connections.testing')]);
            DB::connection('query_replica')->select('select ? as connection_probe', ['replica']);

            $pdo = DB::connection()->getPdo();

            if (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction('ndb_test_pause', function (): int {
                    usleep(110_000);

                    return 1;
                });
                DB::select('select ndb_test_pause() as slow_probe');
            }

            DB::select('select id as explain_failure_probe from ndb_query_workspace where id = ?', [1]);
            DB::statement('drop table ndb_query_workspace');
            DB::purge();

            return response('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><h1>Rich query workspace</h1></body></html>');
        });

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-queries-empty',
            fn () => response('<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><h1>Empty query workspace</h1></body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-livewire', function () {
            $component = app('livewire')->mount('host-counter', key: 'host-counter-browser');

            return response(<<<HTML
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Livewire host</title></head>
                    <body><main><h1 data-testid="host-page">Livewire host</h1>{$component}</main></body>
                </html>
                HTML);
        });

        $router->middleware(['web', ProfileRequest::class])->get('/profiled-livewire-session-rotation', function (Request $request) {
            foreach ([1, 2, 3] as $number) {
                DB::select('select ? as number', [$number]);
            }

            $component = app('livewire')->mount('host-session-rotator', key: 'host-session-rotator-browser');

            $response = response(<<<HTML
                <!doctype html>
                <html>
                    <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <title>Session rotation host</title>
                    </head>
                    <body><main><h1 data-testid="host-page">Session rotation host</h1>{$component}</main></body>
                </html>
                HTML);

            return $request->query('expire') === '1'
                ? $response->withCookie(cookie(VerifyLivewireRequestForgery::COOKIE, '1', 5))
                : $response;
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-livewire-nested', function () {
            $component = app('livewire')->mount('host-counter-group', key: 'host-counter-group-browser');

            return response(<<<HTML
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Nested Livewire host</title></head>
                    <body><main><h1 data-testid="host-page">Nested Livewire host</h1>{$component}</main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-livewire-validation', function () {
            $component = app('livewire')->mount(
                'host-validation-form',
                ['dense' => true],
                key: 'host-validation-form-browser',
            );

            return response(<<<HTML
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Livewire validation</title></head>
                    <body><main><h1 data-testid="host-page">Livewire validation</h1>{$component}</main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-livewire-single-file', function () {
            $component = app('livewire')->mount('host-functional-status', key: 'host-functional-status-browser');

            return response('<!doctype html><html><body>'.$component.'</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-rich', function () use ($profiledPage) {
            Http::fake(['api.example.test/*' => Http::response(['private' => 'body'], 202)]);
            Http::get('https://api.example.test/v1/status?token=private&limit=5');
            Event::dispatch($this->queuedEvent('job-visual', new ProfiledJob('private')));
            Bus::dispatchSync(new ProfiledJob('private'));
            Mail::raw('private body', fn ($message) => $message
                ->from('sender@example.test')
                ->to('recipient@example.test')
                ->subject('private subject'));
            Event::dispatch(new NotificationSent(
                new ProfiledNotifiable('private@example.test'),
                new ProfiledNotification('private'),
                'mail',
            ));
            Event::dispatch(new CommandExecuted('get', ['private-direct-key'], 1.25, new ProfiledRedisConnection));

            return $profiledPage('Rich request', '/profiled', 'First request');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-cache-rich', function () {
            config()->set('cache.stores.secondary', ['driver' => 'array']);

            $primary = Cache::store('array');
            $secondary = Cache::store('secondary');
            $primary->put('trip:kyoto:option:1', 'stale option', 60);
            $primary->forget('trip:kyoto:option:1');
            $primary->get('trip:kyoto:option:1');
            $primary->get('trip:kyoto:option:1');
            $primary->put('trip:kyoto:weather', ['high' => 24, 'low' => 15], 900);
            $primary->get('trip:kyoto:weather');
            $primary->putMany([
                'trip:kyoto:summary' => 'A compact autumn itinerary',
                'trip:kyoto:rail-pass' => true,
            ], 3600);
            $primary->many(['trip:kyoto:summary', 'trip:kyoto:missing-note']);
            $secondary->put(
                'trip:kyoto:recommendations:'.str_repeat('long-key-segment:', 8).'end',
                'temples and gardens',
                120,
            );
            $secondary->put('trip:kyoto:stale-quote', 'stale quote', 60);
            $secondary->forget('trip:kyoto:stale-quote');
            $secondary->clear();

            if (class_exists(KeyWriteFailed::class)) {
                Event::dispatch(new KeyWriteFailed('secondary', 'trip:kyoto:failed-write', 'not retained', 60));
            }

            if (class_exists(KeyForgetFailed::class)) {
                Event::dispatch(new KeyForgetFailed('secondary', 'trip:kyoto:failed-forget'));
            }

            if (class_exists(CacheFailedOver::class)) {
                Event::dispatch(new CacheFailedOver('secondary', new \RuntimeException('Secondary cache unavailable.')));
            }

            return response(<<<'HTML'
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cache diagnostics</title></head>
                    <body><main><h1 data-testid="host-page">Cache diagnostics</h1></main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-cache-empty', fn () => response(<<<'HTML'
            <!doctype html>
            <html>
                <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Empty cache diagnostics</title></head>
                <body><main><h1 data-testid="host-page">Empty cache diagnostics</h1></main></body>
            </html>
            HTML));

        $router->middleware(ProfileRequest::class)->get('/profiled-models', function () {
            $retrievals = [
                StudioJob::class => [1, 5, 7, 2, 3, 4, 1, 5, 7, 2, 3, 1, 5, 7],
                Client::class => [1, 4, 2, 3, 1, 4, 2, 3, 1, 4],
                ProofVersion::class => [2, 8, 9, 1, 3, 2, 8, 9],
                JobActivity::class => request()->boolean('large') ? range(1, 40) : [1, 2, 3, 4, 5, 6, 7],
                User::class => [1, 2, 1, 2, 1],
            ];

            foreach ($retrievals as $modelClass => $keys) {
                foreach ($keys as $key) {
                    $model = new $modelClass;
                    $model->setConnection('testing');
                    $model->setRawAttributes(['id' => $key], true);
                    Event::dispatch('eloquent.retrieved: '.$modelClass, [$model]);
                }
            }

            if (request()->boolean('queries')) {
                $model = new JobActivity;
                $model->setConnection('testing');
                $model->setRawAttributes(['id' => 99], true);
                $modelEvent = 'eloquent.retrieved: '.JobActivity::class;
                [DB::select('select 99 as id'), Event::dispatch($modelEvent, [$model])];
            }

            if (request()->boolean('missing')) {
                $model = new ProfiledModel;
                $model->setConnection('testing');
                $model->setRawAttributes(['name' => 'Identifier unavailable'], true);
                Event::dispatch('eloquent.retrieved: '.ProfiledModel::class, [$model]);
            }

            if (request()->boolean('changes')) {
                $client = new Client;
                $client->setConnection('testing');
                $client->setRawAttributes(['id' => 4, 'status' => 'draft', 'api_token' => 'private-token'], true);
                $client->setAttribute('status', 'approved');
                $client->setAttribute('api_token', 'updated-private-token');
                $client->syncChanges();
                Event::dispatch('eloquent.updating: '.Client::class, [$client]);
                Event::dispatch('eloquent.updated: '.Client::class, [$client]);
                Event::dispatch('eloquent.saved: '.Client::class, [$client]);

                $proof = new ProofVersion;
                $proof->setConnection('testing');
                $proof->setRawAttributes(['id' => 10, 'label' => 'First proof']);
                $proof->syncChanges();
                Event::dispatch('eloquent.creating: '.ProofVersion::class, [$proof]);
                Event::dispatch('eloquent.created: '.ProofVersion::class, [$proof]);
                Event::dispatch('eloquent.saved: '.ProofVersion::class, [$proof]);

                $user = new User;
                $user->setConnection('testing');
                $user->setRawAttributes(['id' => 2], true);
                Event::dispatch('eloquent.deleting: '.User::class, [$user]);
                Event::dispatch('eloquent.deleted: '.User::class, [$user]);

                $activity = new JobActivity;
                $activity->setConnection('testing');
                $activity->setRawAttributes(['id' => 7], true);
                Event::dispatch('eloquent.deleting: '.JobActivity::class, [$activity]);
                Event::dispatch('eloquent.trashed: '.JobActivity::class, [$activity]);
                Event::dispatch('eloquent.deleted: '.JobActivity::class, [$activity]);
            }

            if (request()->boolean('compiled')) {
                $model = new JobActivity;
                $model->setConnection('testing');
                $model->setRawAttributes(['id' => 77], true);
                $modelEvent = 'eloquent.retrieved: '.JobActivity::class;

                return view('model-compiled', compact('model', 'modelEvent'));
            }

            return response(<<<'HTML'
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Model activity</title></head>
                    <body><main><h1 data-testid="host-page">Model activity</h1></main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-models-empty',
            fn () => response('<!doctype html><html><head><title>No model activity</title></head><body><main>No model activity</main></body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-context', function () {
            Gate::define('inspect-profile', fn (mixed $user, ProfiledModel $model): bool => $user === null && $model instanceof ProfiledModel);
            Gate::define('delete-profile', fn (): bool => false);
            Gate::allows('inspect-profile', [new ProfiledModel]);
            Gate::allows('delete-profile', [new ProfiledModel]);
            Event::listen(ProfiledApplicationEvent::class, ProfiledApplicationListener::class);
            Event::listen(ProfiledApplicationEvent::class, ProfiledApplicationListener::class);
            Event::listen(ProfiledApplicationEvent::class, ProfiledQueuedApplicationListener::class);
            Event::dispatch(new ProfiledApplicationEvent);
            DB::beginTransaction();
            DB::rollBack();
            $view = view('context', [
                'label' => 'Context view',
                'private_value' => 'view-data-value',
                'rows' => collect([
                    [
                        'reference' => 'NL-1042',
                        'ready' => true,
                        'version_count' => 2,
                    ],
                ]),
            ])->render();

            return response('<!doctype html><html><body>'.$view.'</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-authorization-rich', function () {
            $profile = new ProfiledModel;
            $profile->setRawAttributes(['id' => 84, 'name' => 'Kyoto autumn planning profile'], true);
            $studioJob = new StudioJob;
            $studioJob->setRawAttributes(['id' => 42, 'name' => 'Kyoto autumn workspace'], true);
            $planner = new User;
            $planner->setRawAttributes([
                'id' => 7,
                'name' => 'Mara Voss with an intentionally long diagnostic user name',
            ], true);

            Gate::policy(ProfiledModel::class, ProfiledAuthorizationPolicy::class);
            $policyGate = Gate::forUser(new GenericUser(['id' => 'planner-8']));
            $policyGate->allows('view', $profile);
            $policyGate->allows('refund', $profile);

            Gate::define(
                'create-studio-job',
                fn (mixed $user, string $class): bool => $user === null && $class === StudioJob::class,
            );
            Gate::allows('create-studio-job', StudioJob::class);

            Gate::define(
                'revise-an-intentionally-long-kyoto-autumn-workspace-ability',
                fn (mixed $user, StudioJob $job, string $scope, int $revision): bool => $user === $planner
                    && $job === $studioJob
                    && $scope === 'lodging-and-transport'
                    && $revision === 3,
            );
            Gate::forUser($planner)->allows(
                'revise-an-intentionally-long-kyoto-autumn-workspace-ability',
                [$studioJob, 'lodging-and-transport', 3],
            );

            Gate::define(
                'access-private-planning-notes',
                fn (mixed $user): AuthorizationResponse => AuthorizationResponse::deny(
                    'Guests cannot open private planning notes.',
                    'guest_private_notes',
                ),
            );
            Gate::allows('access-private-planning-notes');

            Gate::define('view-public-weather-note', fn (mixed $user): bool => true);
            Gate::allows('view-public-weather-note');

            return response('<!doctype html><html><body><h1>Rich authorization fixture</h1></body></html>');
        });

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-authorization-empty',
            fn () => response('<!doctype html><html><body><h1>Empty authorization fixture</h1></body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-events', function () {
            Event::listen(ProfiledApplicationEvent::class, ProfiledApplicationListener::class);
            Event::listen(ProfiledApplicationEvent::class, ProfiledApplicationListener::class);
            Event::listen(ProfiledApplicationEvent::class, ProfiledQueuedApplicationListener::class);
            Event::listen('App\\Events\\TripArchived', static fn () => null);

            Event::dispatch(new ProfiledApplicationEvent);
            Event::dispatch(new ProfiledApplicationEvent(
                trip: 'Long application event fixture',
                changes: ['status', 'travelers', 'lodging'],
            ));
            Event::dispatch('App\\Events\\TripArchived', [['tripId' => 42]]);

            $largeShape = array_fill_keys(
                array_map(static fn (int $index): string => 'field_'.$index, range(1, 30)),
                'private fixture value',
            );

            foreach (range(1, 8) as $dispatch) {
                Event::dispatch(
                    'App\\Events\\TravelPlanning\\KyotoAutumnItineraryRecalculationRequestedForEveryCollaborator',
                    [[...$largeShape, 'dispatch' => $dispatch]],
                );
            }

            Event::dispatch('App\\Events\\NoListenerWasRegistered');

            foreach (range(1, 12) as $number) {
                DB::select('select ? as event_fixture', [$number]);
            }

            return response(<<<'HTML'
                <!doctype html>
                <html>
                    <head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Event diagnostics</title></head>
                    <body><main><h1 data-testid="host-page">Event diagnostics</h1></main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-views', function () {
            $context = view('context', [
                'label' => 'Context view',
                'private_value' => 'view-data-value',
                'rows' => collect(),
            ])->render();
            $firstResponse = view('original-response', ['label' => 'First response'])->render();
            $secondResponse = view('original-response', ['label' => 'Second response'])->render();

            return response('<!doctype html><html><body>'.$context.$firstResponse.$secondResponse.'</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-private-query', function () {
            foreach (['private-alpha', 'private-beta', 'private-gamma'] as $value) {
                DB::select('select ? as private_value', [$value]);
            }

            Log::info('private timeline log message');

            return response('<!doctype html><html><body>Private query fixture</body></html>');
        });

        $router->middleware(['web', ProfileRequest::class])->post('/profiled-validation', function () {
            Validator::make(['email' => 'invalid'], [
                'email' => ['required', 'email'],
                'name' => ['required'],
            ])->validateWithBag('signup');

            return response('unreachable');
        });

        $router->middleware(['web', ProfileRequest::class])->get(
            '/profiled-session-validation',
            fn () => response('<!doctype html><html><body>Validation redirect target</body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get('/hostile-styles', function () {
            Event::listen(ProfiledApplicationEvent::class, ProfiledApplicationListener::class);
            Event::dispatch(new ProfiledApplicationEvent);
            foreach (['alpha', 'beta', 'gamma'] as $value) {
                DB::select('select ? as hostile_value', [$value]);
            }
            Cache::put('hostile-cache-key', ['ready' => true], 60);
            Cache::get('hostile-cache-key');
            Cache::get('hostile-cache-missing');
            Cache::put('hostile-cache-stale', 'stale', 60);
            Cache::forget('hostile-cache-stale');
            Gate::define('inspect-hostile-profile', fn (mixed $user, ProfiledModel $model): bool => $model instanceof ProfiledModel);
            Gate::define('delete-hostile-profile', fn (): bool => false);
            Gate::allows('inspect-hostile-profile', [new ProfiledModel]);
            Gate::allows('delete-hostile-profile', [new ProfiledModel]);
            $hostileModel = new Client;
            $hostileModel->setConnection('testing');
            $hostileModel->setRawAttributes(['id' => 7, 'status' => 'draft'], true);
            Event::dispatch('eloquent.retrieved: '.Client::class, [$hostileModel]);
            Event::dispatch('eloquent.retrieved: '.Client::class, [$hostileModel]);
            [DB::select('select 7 as hostile_model_id'), Event::dispatch('eloquent.retrieved: '.Client::class, [$hostileModel])];
            $hostileModel->setAttribute('status', 'approved');
            $hostileModel->syncChanges();
            Event::dispatch('eloquent.updating: '.Client::class, [$hostileModel]);
            Event::dispatch('eloquent.updated: '.Client::class, [$hostileModel]);
            Event::dispatch('eloquent.saved: '.Client::class, [$hostileModel]);
            Mail::raw('Hostile style mail body', fn ($message) => $message
                ->from('sender@example.test')
                ->to('recipient@example.test')
                ->subject('Hostile style mail')
                ->attachData('hostile attachment', 'hostile.txt', ['mime' => 'text/plain']));
            $queuedMailable = (new ProfiledMailable(
                subjectLine: 'Hostile queued mail',
                heading: 'Hostile queued heading',
                messageCopy: 'Hostile queued body',
            ))->to('queued@example.test');
            $queuedNotification = new ProfiledNotification('hostile queued notification');
            Event::dispatch($this->queuedEvent(
                'hostile-mail-job',
                new SendQueuedMailable($queuedMailable),
                queue: 'hostile-mail',
                delay: 0,
            ));
            Event::dispatch($this->queuedEvent(
                'hostile-notification-job',
                new SendQueuedNotifications(
                    collect([new ProfiledNotifiable('queued@example.test')]),
                    $queuedNotification,
                    ['mail'],
                ),
                queue: 'hostile-notifications',
                delay: 0,
            ));
            Event::dispatch($this->queuedEvent(
                'hostile-pending-job',
                new ProfiledJob('hostile pending payload'),
                queue: 'hostile-pending',
                delay: 60,
            ));
            $workerId = (string) Str::uuid();
            $background = app(BackgroundActivityStore::class);
            $background->recordOutcome($background->key('redis', 'hostile-mail', 'hostile-mail-job'), 'sent', $workerId, 1);
            $background->recordOutcome($background->key('redis', 'hostile-notifications', 'hostile-notification-job'), 'sent', $workerId, 1);
            Event::dispatch(new NotificationSent(
                new ProfiledNotifiable('recipient@example.test'),
                new ProfiledNotification('Hostile style notification'),
                'mail',
                ['message_id' => 'hostile-notification'],
            ));
            Http::fake([
                'hostile-http.test/*' => Http::response(['message' => 'Hostile upstream failure.'], 503),
            ]);
            Http::delete('https://hostile-http.test/v1/kyoto/'.str_repeat('long-path-', 8).'end');
            Event::dispatch(new CommandExecuted('get', ['hostile-direct-key'], 1.25, new ProfiledRedisConnection));
            Log::warning('Hostile style log needs attention.', [
                'trip_id' => 41,
                'actor' => ['type' => 'planner', 'id' => 7],
            ]);
            Log::error('Hostile style log failed.', [
                'trip_id' => 41,
                'exception' => new \RuntimeException(
                    'Hostile partner rejected the request.',
                    previous: new \LogicException('Hostile upstream cause.'),
                ),
            ]);
            if (request()->query('exceptions') === 'split') {
                app(ProfileManager::class)->recordException(new \DomainException('Hostile secondary failure.'));
            }
            view('context', [
                'label' => 'Hostile context view',
                'private_value' => 'hostile-view-data',
                'rows' => collect(),
            ])->render();

            return response(<<<'HTML'
                <!doctype html>
                <html data-theme="dark">
                    <head>
                        <style>
                            @layer base {
                                :root, [data-theme] {
                                    background-color: var(--root-bg);
                                    color: var(--color-base-content);
                                }

                                :where(:root, [data-theme]) {
                                    --root-bg: rgb(255, 255, 255);
                                    --color-base-content: rgb(0, 0, 0);
                                }
                            }

                            body { font-family: serif; }
                            button { background: rgb(255, 0, 0); border-radius: 0; color: rgb(0, 128, 0); height: 91px; }
                            button svg { width: 64px; height: 64px; }
                            a { background: rgb(255, 0, 255); color: rgb(0, 128, 0); height: 91px; text-decoration: underline 8px; }
                            h1, h2, h3, h4, h5, h6, p { margin: 31px; }
                            ul, ol, menu { list-style: square inside; margin: 31px; padding: 29px; }
                            details { background: rgb(255, 0, 0); border-left: 13px solid rgb(255, 0, 0); margin: 27px; padding: 24px; }
                            dl, dt, dd { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; }
                            input[type="search"], select { background: rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            pre, code { background: rgb(243, 243, 243); color: rgb(0, 0, 0); }
                            iframe { width: 17px; height: 19px; border: 9px solid rgb(255, 0, 0); }
                            summary { color: rgb(255, 0, 0); font-size: 42px; margin: 23px; }
                            [data-cache], [data-cache-item], [data-cache-result], [data-cache-filter], [data-cache-search], [data-cache-search-text] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            [data-http-client], [data-http-client-item], [data-method], [data-host], [data-status], [data-duration], [data-source] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-http-client-list-heading], [data-ndb-http-client-sort-heading] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            [data-mail] { border-left: 20px solid rgb(255, 0, 0); }
                            [data-ndb-queue-item], [data-ndb-notification-item], [data-ndb-event-item] { border-left: 20px solid rgb(255, 0, 0); }
                            [data-ndb-queue-status], [data-ndb-notification-status], [data-ndb-event-listener-outcome] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; }
                            [data-ndb-background-refresh], [data-ndb-queue-profile-link], [data-ndb-notification-profile-link], [data-ndb-mail-related-profile], [data-ndb-mail-open-related], [data-ndb-authorization-copy-handler-source], [data-ndb-authorization-copy-callsite] { background: rgb(255, 0, 255); border-radius: 0; color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-redis], [data-ndb-redis-item], [data-ndb-redis-detail], [data-ndb-redis-key-label] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); }
                            [data-ndb-redis-command], [data-ndb-redis-detail-status], [data-ndb-redis-key] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; }
                            [data-ndb-redis-copy-keys] { background: rgb(255, 0, 255); border-radius: 0; color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-mail-facts], [data-ndb-notification-facts], [data-ndb-event-facts] { background: rgb(255, 0, 0); border-top: 20px solid rgb(255, 0, 0); display: block; padding: 50px; }
                            [data-ndb-mail-fact], [data-ndb-notification-fact], [data-ndb-event-fact] { background: rgb(255, 0, 0); }
                            [data-ndb-mail-attachment-download] { background: rgb(255, 0, 255); color: rgb(0, 128, 0); height: 91px; text-decoration: underline 8px; }
                            [data-ndb-authorization-item] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); height: 91px; }
                            [data-ndb-authorization-result-label], [data-ndb-authorization-detail-result] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; }
                            [data-ndb-authorization-detail] { border-left: 20px solid rgb(255, 0, 0); }
                            [data-ndb-authorization-user], [data-ndb-authorization-arguments], [data-ndb-authorization-user-detail], [data-ndb-authorization-response], [data-ndb-authorization-detail-panel] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                            [data-ndb-query-workspace], [data-ndb-query-item], [data-ndb-query-detail] { border-left: 20px solid rgb(255, 0, 0); }
                            [data-ndb-query-type-badge], [data-ndb-query-list-driver] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-family: monospace; font-size: 42px; height: 91px; }
                            [data-ndb-query-item][data-ndb-repeated="true"], [data-ndb-query-item][data-ndb-slow="true"] { background: rgb(255, 0, 0); }
                            [data-ndb-query-filter], [data-ndb-query-search], [data-ndb-query-execution-select], [data-ndb-query-copy-sql], [data-ndb-query-copy-runnable], [data-ndb-query-detail-tab], [data-ndb-query-sort-heading] { height: 91px; }
                            [data-ndb-sort-indicator] { height: 64px; width: 64px; }
                            [data-notifications] { border-left: 20px solid rgb(255, 0, 0); }
                            [data-ndb-log-entry], [data-ndb-log-search-text] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); padding: 24px; }
                            [data-ndb-log-level-select] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-log-severity], [data-ndb-log-repeat-label] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; }
                            [data-ndb-log-detail-back] { background: rgb(255, 0, 255); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-log-details-title] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; }
                            [data-ndb-log-review-exception] { background: rgb(255, 0, 255); border-radius: 0; color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-log-context], [data-ndb-log-timing], [data-ndb-log-source], [data-ndb-log-related-exception] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); padding: 24px; }
                            [data-ndb-exception-cause] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); padding: 50px; }
                            [data-ndb-exception-context-action] { background: rgb(255, 0, 255); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-exception-layout], [data-ndb-exception-workspace], [data-ndb-exception-focused-workspace], [data-ndb-exception-focused-detail], [data-ndb-exception-list-panel], [data-ndb-exception-split-detail] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); display: block; overflow: visible; padding: 50px; }
                            [data-ndb-exception-detail-back] { background: rgb(255, 0, 255); color: rgb(0, 128, 0); height: 91px; padding: 50px; width: 91px; }
                            [data-ndb-event-detail-tab] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-event-listener-row] { background: rgb(255, 0, 0); padding: 50px; }
                            [data-ndb-event-timeline] { background: rgb(255, 0, 0); border-left: 13px solid rgb(255, 0, 0); padding: 24px; }
                            [data-ndb-model-workspace], [data-ndb-model-summary], [data-ndb-model-list], [data-ndb-model-list-heading], [data-ndb-model-group], [data-ndb-model-index], [data-ndb-model-sort-name], [data-ndb-model-sort-retrieved], [data-ndb-model-sort-writes], [data-ndb-model-sort-reloads], [data-ndb-model-detail-pane], [data-ndb-model-detail-empty], [data-ndb-model-detail], [data-ndb-model-header], [data-ndb-model-detail-panel], [data-ndb-model-records], [data-ndb-model-record], [data-ndb-model-write-table], [data-ndb-model-write-operation], [data-ndb-model-sources], [data-ndb-model-source], [data-ndb-model-source-gap], [data-ndb-model-compiled-source], [data-ndb-model-source-path], [data-ndb-model-retrieved-column], [data-ndb-model-write-column], [data-ndb-model-extra-column] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                            [data-ndb-model-sort-heading] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-model-search] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                            [data-ndb-model-search-value] { display: none; }
                            [data-ndb-model-detail-tab], [data-ndb-model-detail-back], [data-ndb-inspector-focus-back] { background: rgb(255, 0, 255); color: rgb(0, 128, 0); height: 91px; }
                            [data-ndb-inspector-focus-list], [data-ndb-inspector-focus-detail] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); padding: 50px; }
                            [data-ndb-view-workspace], [data-ndb-view-list-panel], [data-ndb-view-group], [data-ndb-view-detail-pane], [data-ndb-view-detail], [data-ndb-view-detail-content], [data-ndb-view-data-panel] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                            [data-ndb-view-search], [data-ndb-view-filter], [data-ndb-view-detail-back] { background: rgb(255, 0, 255); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                            [data-ndb-view-list-name], [data-ndb-view-detail-name] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-family: monospace; font-size: 42px; }
                            [data-ndb-inspector-source-link] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); height: 91px; padding: 50px; }
                            [data-ndb-inspector-source-fact], [data-ndb-inspector-stack] { background: rgb(255, 0, 0); border-left: 20px solid rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                            [data-ndb-notification-destination] { background: rgb(255, 0, 0); color: rgb(0, 128, 0); font-size: 42px; padding: 50px; }
                        </style>
                    </head>
                    <body>
                        <button data-testid="host-button">Host button</button>
                        <button data-testid="host-icon-button"><svg aria-hidden="true"></svg></button>
                        <code data-testid="host-code">Host code</code>
                    </body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/plain-json', fn () => response()->json(['ready' => true]));

        $router->match(['get', 'post', 'patch'], '/api/plain-json', fn () => response()->json(['source' => 'api']));

        $router->get('/ajax-fragment', fn () => response('<div data-fragment>Search result</div>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]));

        $router->get('/profile-redirect', fn () => redirect('/profiled'));

        $router->get('/streamed-response', fn () => response()->stream(
            static fn () => print 'streamed-body',
            200,
            ['Content-Type' => 'text/plain'],
        ));

        $router->get('/binary-response', fn () => response()->download(
            dirname(__DIR__).'/Fixtures/views/original-response.blade.php',
            'original-response.txt',
        ));

        $router->middleware(ProfileRequest::class)->get(
            '/html-without-head',
            fn () => response('<html><body>Headless page</body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get(
            '/html-without-body',
            fn () => response('<html><head><title>No body</title></head><main>No body</main></html>'),
        );

        $router->middleware(ProfileRequest::class)->get(
            '/plain-text',
            fn () => response('Plain text', 200, ['Content-Type' => 'text/plain']),
        );

        $router->middleware(ProfileRequest::class)->get(
            '/download',
            fn () => response('<html><body>Download</body></html>', 200, [
                'Content-Disposition' => 'Attachment; filename="debug.html"',
            ]),
        );

        $router->middleware(ProfileRequest::class)->get(
            '/failed-html',
            fn () => response('<html><body>Failed</body></html>', 422),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-partial-model', function () {
            $model = new ProfiledModel;
            $model->setRawAttributes(['name' => 'Partial model']);

            Event::dispatch('eloquent.retrieved: '.ProfiledModel::class, [$model]);

            return response('<!doctype html><html><body>Partial model</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-collector-failure',
            fn () => response('<!doctype html><html><body>Application response</body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-exception',
            fn () => throw new \RuntimeException('Application failed.'),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-reported-exception', function () {
            Http::fake(['api.example.test/*' => Http::response(['ready' => true])]);
            Http::get('https://api.example.test/v1/status');
            $component = app('livewire')->mount('host-functional-status', key: 'host-functional-exception');
            app(ProfileManager::class)->recordException(new \RuntimeException(
                'Reported failure.',
                previous: new \LogicException('Earlier itinerary failure.'),
            ));

            return response('<!doctype html><html><body>Reported failure'.$component.'</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-reported-exceptions', function () {
            app(ProfileManager::class)->recordException(new \RuntimeException('First reported failure.'));
            app(ProfileManager::class)->recordException(new \LogicException('Second reported failure.'));

            return response('<!doctype html><html><body>Reported failures</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-http-client', function () {
            Http::withToken('private-bearer')
                ->withHeaders(['X-Trace' => 'trace-1'])
                ->get('https://api.example.test/v1/patients?token=private-token&limit=5');

            try {
                Http::withHeaders(['Cookie' => 'session=private-cookie'])
                    ->post('https://down.example.test/v1/sync?api_key=private-key', [
                        'token' => 'private-body-token',
                        'patient' => 'visible-patient',
                    ]);
            } catch (ConnectionException) {
                // The application handled the failed dependency.
            }

            return response('<!doctype html><html><body>HTTP client</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get(
            '/profiled-http-client-empty',
            fn () => response('<!doctype html><html><body>HTTP client empty</body></html>'),
        );

        $router->middleware(ProfileRequest::class)->get('/profiled-http-client-sparse', function () {
            app(ProfileManager::class)->record('http_client', [
                'method' => 'GET',
                'url' => 'https://api.healthy.test/v1/status',
                'status' => 204,
                'reason' => 'No Content',
                'duration_ms' => 0.08,
                'failed' => false,
                'request' => [],
                'response' => [],
            ]);

            return response('<!doctype html><html><body>HTTP client sparse</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-http-client-rich', function () {
            $failedConnection = method_exists(Factory::class, 'failedConnection')
                ? Http::failedConnection('Connection refused')
                : fn ($request) => Create::rejectionFor(new ConnectException(
                    'Connection refused',
                    $request->toPsrRequest(),
                ));

            Http::fake([
                'api.recommendations.test/*' => function () {
                    usleep(275_000);

                    return Http::response(['recommendations' => ['debugging', 'profiling']], 200, [
                        'X-Upstream-Cache' => 'miss',
                    ]);
                },
                'api.healthy.test/*' => Http::response(null, 204),
                'api.redirect.test/*' => Http::response(null, 302, [
                    'Location' => 'https://api.redirect.test/v2/current',
                ]),
                'api.validation.test/*' => Http::response([
                    'message' => 'The submitted data was invalid.',
                    'errors' => ['email' => ['The email must be valid.']],
                ], 422),
                'api.rate-limit.test/*' => Http::response(['message' => 'Too many requests.'], 429, [
                    'Retry-After' => '30',
                ]),
                'api.error.test/*' => Http::response(['message' => 'Service unavailable.'], 503),
                'api.down.test/*' => $failedConnection,
            ]);

            Http::withHeaders(['X-Debug-Request' => 'recommendations'])
                ->get('https://api.recommendations.test/v2/personalized/homepage?locale=en');
            Http::get('https://api.healthy.test/v1/status');
            Http::withoutRedirecting()->get('https://api.redirect.test/v1/legacy');
            Http::patch('https://api.validation.test/v1/team-members/42', [
                'email' => 'not-an-email',
            ]);
            Http::get('https://api.rate-limit.test/v1/downloads/today');
            Http::delete('https://api.error.test/v1/stale-cache/very-long-resource-identifier');

            try {
                Http::post('https://api.down.test/v1/webhooks/deliver', [
                    'event' => 'profile.ready',
                ]);
            } catch (ConnectionException) {
                // The application handled the failed dependency.
            }

            return response(<<<'HTML'
                <!doctype html>
                <html>
                    <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <title>HTTP client diagnostics</title>
                    </head>
                    <body>
                        <main><h1 data-testid="host-page">HTTP client diagnostics</h1></main>
                    </body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-queue', function () {
            Event::dispatch($this->queuedEvent(
                'job-1',
                new ProfiledJob('private queued value'),
                providerId: 9001,
            ));
            Bus::dispatchSync(new ProfiledJob('private sync value'));

            try {
                Bus::dispatchSync(new ProfiledFailingJob('private failed value'));
            } catch (\RuntimeException) {
                // The application handled the failed synchronous job.
            }

            return response('<!doctype html><html><body>Queue</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-queue-attempts', function () {
            Event::dispatch($this->queuedEvent(
                'job-with-attempts',
                new ProfiledJob('private linked attempt value'),
                queue: 'attempts',
                delay: 0,
            ));

            $background = app(BackgroundActivityStore::class);
            $background->recordOutcome(
                $background->key('redis', 'attempts', 'job-with-attempts'),
                'completed',
                (string) Str::uuid(),
                1,
            );

            try {
                Bus::dispatchSync(new ProfiledFailingJob('private zero attempt value'));
            } catch (\RuntimeException) {
                // The application handled the failed synchronous job.
            }

            return response('<!doctype html><html><body>Queue attempts</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-queued-communications', function () {
            $mailable = (new ProfiledMailable(
                subjectLine: 'Private queued subject',
                heading: 'Private queued heading',
                messageCopy: 'Private queued body',
            ))->to('private-recipient@example.test');
            $notification = new ProfiledNotification('private queued notification');
            $notifiables = collect([new ProfiledNotifiable('private-notifiable@example.test')]);

            Event::dispatch($this->queuedEvent(
                'mail-job-1',
                new SendQueuedMailable($mailable),
                queue: 'mail-delayed',
                delay: 30,
            ));
            Event::dispatch($this->queuedEvent(
                'notification-job-1',
                new SendQueuedNotifications($notifiables, $notification, ['mail']),
                queue: 'notifications',
                delay: 0,
            ));

            return response('<!doctype html><html><body>Queued communications</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-after-response', function () {
            $deferred = static function (): void {
                usleep(80_000);
                DB::select('select 24 as deferred_mail');
                Mail::raw('Deferred body', fn ($message) => $message
                    ->to('deferred@example.test')
                    ->subject('Deferred mail'));
            };

            if (function_exists('defer')) {
                defer($deferred);
            } else {
                app()->terminating($deferred);
            }

            Bus::dispatchAfterResponse(new ProfiledAfterResponseMailJob);

            return response('<!doctype html><html><head><title>After response</title></head><body><main>Original response</main></body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-messages', function () {
            Mail::raw('private body', function ($message): void {
                $message
                    ->from('private-sender@example.test')
                    ->to('private-recipient@example.test')
                    ->cc('private-copy@example.test')
                    ->subject('private subject')
                    ->attachData('private attachment', 'private.txt');
            });

            $notifiable = new ProfiledNotifiable('private-recipient@example.test');
            $notification = new ProfiledNotification('private notification data');
            Event::dispatch(new NotificationSent($notifiable, $notification, 'mail', ['private response']));
            Event::dispatch(new NotificationFailed($notifiable, $notification, 'slack', ['private' => 'failure data']));

            return response('<!doctype html><html><body>Messages</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-notifications-rich', function () {
            app(ChannelManager::class)->extend(
                'profiled-sms',
                fn (): ProfiledNotificationChannel => new ProfiledNotificationChannel(fails: true),
            );
            app(ChannelManager::class)->extend(
                'profiled-push',
                fn (): ProfiledNotificationChannel => new ProfiledNotificationChannel,
            );

            $notifiable = new ProfiledNotifiable('elise@example.test');
            $notification = new ProfiledNotification(
                privateValue: 'Kyoto autumn',
                channels: ['mail', 'profiled-sms'],
                subjectLine: 'Your Kyoto journey is ready to review',
            );
            $notification->id = (string) Str::uuid();
            $failureWasDispatched = false;

            Event::listen(NotificationFailed::class, function (NotificationFailed $event) use ($notification, &$failureWasDispatched): void {
                if (($event->notification->id ?? null) === $notification->id && $event->channel === 'profiled-sms') {
                    $failureWasDispatched = true;
                }
            });

            try {
                Notification::sendNow($notifiable, $notification);
            } catch (\RuntimeException $exception) {
                if (! $failureWasDispatched) {
                    Event::dispatch(new NotificationFailed(
                        $notifiable,
                        $notification,
                        'profiled-sms',
                        ['exception' => $exception],
                    ));
                }
            }

            Notification::sendNow($notifiable, new ProfiledNotification(
                privateValue: 'Kyoto departure reminder',
                channels: ['profiled-push'],
                subjectLine: 'Your Kyoto departure is coming up',
            ));

            return response('<!doctype html><html><body>Notification delivery diagnostics</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-notifications-many-recipients', function () {
            app(ChannelManager::class)->extend(
                'profiled-push',
                fn (): ProfiledNotificationChannel => new ProfiledNotificationChannel,
            );

            Notification::sendNow(
                new ProfiledNotifiable([
                    'elise@example.test' => 'Elise Martin',
                    'theo@example.test' => 'Theo Laurent',
                    'mara@example.test' => 'Mara Bell',
                    'sora@example.test' => 'Sora Tanaka',
                    'nina@example.test' => 'Nina Dubois',
                    'arthur@example.test' => 'Arthur Moreau',
                    'camille@example.test' => 'Camille Bernard',
                    'yuki@example.test' => 'Yuki Nakamura',
                ], id: 2048, name: 'Kyoto review team'),
                new ProfiledNotification(
                    privateValue: 'Kyoto review team digest',
                    subjectLine: 'Kyoto review team digest',
                ),
            );

            $travelers = collect([
                ['Mara Bell', 2101],
                ['Alexander Montgomery-Sinclair', 2102],
                ['Sora Tanaka', 2103],
                ['Nina Dubois', 2104],
                ['Arthur Moreau', 2105],
                ['Camille Bernard', 2106],
                ['Yuki Nakamura', 2107],
                ['Noah Williams', 2108],
            ])->map(fn (array $traveler): ProfiledNotifiable => new ProfiledNotifiable(
                privateAddress: mb_strtolower(str_replace([' ', '-'], ['', ''], $traveler[0])).'@example.test',
                id: $traveler[1],
                name: $traveler[0],
            ))->all();

            Notification::sendNow($travelers, new ProfiledNotification(
                privateValue: 'Kyoto traveler reminder',
                channels: ['profiled-push'],
                subjectLine: 'Your Kyoto departure is coming up',
            ));

            return response('<!doctype html><html><body>Many notification recipients</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-mail-rich', function () {
            Mail::to([
                ['email' => 'taylor@example.test', 'name' => 'Taylor Reed'],
                ['email' => 'alexandra.montgomery@example.test', 'name' => 'Alexandra Montgomery'],
                ['email' => 'mara@example.test', 'name' => 'Mara Bell'],
                ['email' => 'sora@example.test', 'name' => 'Sora Tanaka'],
                ['email' => 'nina@example.test', 'name' => 'Nina Dubois'],
                ['email' => 'arthur@example.test', 'name' => 'Arthur Moreau'],
            ])->send(new ProfiledMailable(
                subjectLine: 'Payment receipt #NS-1042',
                heading: 'Payment received',
                messageCopy: 'Thanks, Taylor. Your workspace subscription is paid and ready for the next billing period.',
                detailLabel: 'Total paid',
                detailValue: '$49.00',
                actionLabel: 'View receipt',
                attachment: [
                    'name' => 'receipt-NS-1042.pdf',
                    'body' => '%PDF-1.4 profiled receipt',
                    'mime' => 'application/pdf',
                ],
            ));
            Mail::to('alex@example.test')->send(new ProfiledMailable(
                subjectLine: 'Welcome to Northstar',
                heading: 'Your workspace is ready',
                messageCopy: 'Invite your team, connect your first project, and start tracking the work that matters.',
                detailLabel: 'Workspace',
                detailValue: 'Acme Studio',
                actionLabel: 'Open workspace',
            ));
            Mail::to('morgan@example.test')->send(new ProfiledMailable(
                subjectLine: 'Weekly account digest',
                heading: 'Your week at a glance',
                messageCopy: 'Three projects shipped, two reviews are waiting, and there were no failed deployments.',
                detailLabel: 'Reporting period',
                detailValue: 'August 17–23',
                includeHtml: false,
            ));

            return response(<<<'HTML'
                <!doctype html>
                <html>
                    <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <title>Mail diagnostics</title>
                    </head>
                    <body><main><h1 data-testid="host-page">Mail diagnostics</h1></main></body>
                </html>
                HTML);
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-redis', function () {
            $connection = new ProfiledRedisConnection;
            Event::dispatch(new CommandExecuted('get', ['private-direct-key'], 1.25, $connection));
            Event::dispatch(new CommandExecuted('setex', ['private-cache-key', 60, 'private-cache-value'], 0.4, $connection));
            Event::dispatch($this->keyWrittenEvent());
            Event::dispatch(new CommandExecuted('flushdb', [], 0.5, $connection));

            if (class_exists(CacheFlushed::class)) {
                Event::dispatch(new CacheFlushed('redis', ['tenant:private-clinic']));
            }

            if (class_exists(CommandFailed::class)) {
                Event::dispatch(new CommandFailed('hget', ['private-hash', 'private-field'], new \RuntimeException('private Redis failure'), $connection));
            }

            return response('<!doctype html><html><body>Redis</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-redis-client', function () {
            $connection = new ProfiledRedisConnection(app('events'));
            $caller = new ProfiledRedisCaller;
            $caller->read($connection);

            try {
                $caller->readHash($connection);
            } catch (\RuntimeException) {
                // The failed command is expected and recorded by Laravel's Redis connection.
            }

            return response('<!doctype html><html><body>Redis client calls</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-redis-independent-cache', function () {
            Event::dispatch(new CommandExecuted('get', ['private-direct-key'], 1.25, new ProfiledRedisConnection));
            Cache::get('independent-array-cache-key');

            return response('<!doctype html><html><body>Independent cache</body></html>');
        });

        $router->middleware(ProfileRequest::class)->get('/profiled-redis-protected', function () {
            $originalPolicy = config('newdebugbar.collection.key_policy');
            config()->set('newdebugbar.collection.key_policy', 'hash');

            try {
                Event::dispatch(new CommandExecuted('get', ['private-protected-key'], 1.25, new ProfiledRedisConnection));
            } finally {
                config()->set('newdebugbar.collection.key_policy', $originalPolicy);
            }

            return response('<!doctype html><html><body>Protected Redis</body></html>');
        });

        $router->middleware(ProfileRequest::class)->post(
            '/profiled-input',
            fn (Request $request) => response('<!doctype html><html><body>'.$request->input('clinic.name').'</body></html>'),
        );
    }

    private function queuedEvent(
        string $id,
        object $job,
        string $queue = 'emails',
        int $delay = 5,
        string|int|null $providerId = null,
    ): JobQueued {
        if (method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        }

        if (method_exists($job, 'delay')) {
            $job->delay($delay);
        }

        $payload = json_encode(['uuid' => $id, 'private' => 'queued payload'], JSON_THROW_ON_ERROR);
        $providerId ??= $id;

        if (property_exists(JobQueued::class, 'queue')) {
            return new JobQueued('redis', $queue, $providerId, $job, $payload, $delay);
        }

        return new JobQueued('redis', $providerId, $job, $payload);
    }

    private function keyWrittenEvent(): KeyWritten
    {
        $arguments = [
            'private-cache-key',
            'private-cache-value',
            60,
            ['tenant:private-clinic', 'patient:private-patient'],
        ];

        if (property_exists(CacheEvent::class, 'storeName')) {
            array_unshift($arguments, 'redis');
        }

        return new KeyWritten(...$arguments);
    }
}

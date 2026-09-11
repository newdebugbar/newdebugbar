<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\Models\ProfiledModel;
use NewDebugBar\Tests\Fixtures\Policies\ProfiledAuthorizationPolicy;

it('preserves class-string authorization targets', function () {
    Route::middleware(ProfileRequest::class)->get('/profiled-class-authorization', function () {
        Gate::define(
            'create-profile',
            fn (mixed $user, string $model): bool => $user === null && $model === ProfiledModel::class,
        );
        Gate::allows('create-profile', ProfiledModel::class);

        return response('Profiled class authorization');
    });

    $response = $this->get('/profiled-class-authorization')->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);

    expect($profile['inspectors']['authorization']['payload']['items'][0])
        ->ability->toBe('create-profile')
        ->result->toBe('allowed')
        ->argument_types->toBe([ProfiledModel::class])
        ->arguments->toBe([[
            'position' => 1,
            'kind' => 'class',
            'type' => ProfiledModel::class,
        ]]);
});

it('captures user identity and bounded model and value arguments', function () {
    Route::middleware(ProfileRequest::class)->get('/profiled-argument-authorization', function () {
        Gate::define(
            'revise-profile',
            fn (mixed $user, ProfiledModel $model, string $scope, int $revision): bool => $user?->getAuthIdentifier() === 'planner-7'
                && $model->getKey() === 42
                && $scope === 'private-note'
                && $revision === 3,
        );
        $model = new ProfiledModel;
        $model->setRawAttributes(['id' => 42, 'name' => 'Kyoto autumn'], true);
        Gate::forUser(new GenericUser(['id' => 'planner-7']))
            ->allows('revise-profile', [$model, 'private-note', 3]);

        return response('Profiled argument authorization');
    });

    $response = $this->get('/profiled-argument-authorization')->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);
    $decision = $profile['inspectors']['authorization']['payload']['items'][0];

    expect($decision)
        ->result->toBe('allowed')
        ->user->toMatchArray([
            'type' => GenericUser::class,
            'identifier_name' => 'id',
            'identifier' => 'planner-7',
        ])
        ->handler->toBe('callback')
        ->handler_kind->toBe('callback')
        ->handler_name->toBe('Gate callback')
        ->handler_source->file->toBe('tests/Feature/EventRegistrarTest.php')
        ->arguments->toBe([
            [
                'position' => 1,
                'kind' => 'model',
                'type' => ProfiledModel::class,
                'identifier' => 42,
                'route_key_name' => 'id',
                'route_key' => 42,
                'name' => 'Kyoto autumn',
            ],
            [
                'position' => 2,
                'kind' => 'value',
                'type' => 'string',
                'value' => 'private-note',
            ],
            [
                'position' => 3,
                'kind' => 'value',
                'type' => 'int',
                'value' => 3,
            ],
        ])
        ->stack->not->toBeEmpty()
        ->and($decision)->not->toHaveKeys(['actor', 'user_type']);
});

it('identifies a named gate callback when its model also has a policy', function () {
    Route::middleware(ProfileRequest::class)->get('/profiled-callback-with-policy', function () {
        Gate::policy(ProfiledModel::class, ProfiledAuthorizationPolicy::class);
        Gate::define(
            'coordinate-profile',
            fn (mixed $user, ProfiledModel $model): bool => $user?->getAuthIdentifier() === 'planner-7'
                && $model->getKey() === 42,
        );
        $model = new ProfiledModel;
        $model->setRawAttributes(['id' => 42], true);
        Gate::forUser(new GenericUser(['id' => 'planner-7']))->allows('coordinate-profile', $model);

        return response('Profiled callback with policy');
    });

    $response = $this->get('/profiled-callback-with-policy')->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);

    expect($profile['inspectors']['authorization']['payload']['items'][0])
        ->handler->toBe('callback')
        ->handler_kind->toBe('callback')
        ->handler_name->toBe('Gate callback')
        ->handler_source->file->toBe('tests/Feature/EventRegistrarTest.php');
});

it('normalizes policy responses and preserves their reasons and source', function () {
    Route::middleware(ProfileRequest::class)->get('/profiled-policy-authorization', function () {
        Gate::policy(ProfiledModel::class, ProfiledAuthorizationPolicy::class);
        $model = new ProfiledModel;
        $model->setRawAttributes(['id' => 84], true);
        $gate = Gate::forUser(new GenericUser(['id' => 'planner-8']));
        $gate->allows('view', $model);
        $gate->allows('refund', $model);

        return response('Profiled policy authorization');
    });

    $response = $this->get('/profiled-policy-authorization')->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);
    [$allowed, $denied] = $profile['inspectors']['authorization']['payload']['items'];

    expect($allowed)
        ->result->toBe('allowed')
        ->result_message->toBe('The actor may view this profile.')
        ->result_code->toBe('profile_visible')
        ->result_status->toBeNull()
        ->handler->toBe(ProfiledAuthorizationPolicy::class.'@view')
        ->handler_kind->toBe('policy')
        ->handler_source->file->toBe('tests/Fixtures/Policies/ProfiledAuthorizationPolicy.php')
        ->and($denied)
        ->result->toBe('denied')
        ->result_message->toBe('The profile is outside the actor workspace.')
        ->result_code->toBe('profile_scope')
        ->result_status->toBe(404)
        ->handler->toBe(ProfiledAuthorizationPolicy::class.'@refund')
        ->handler_kind->toBe('policy');
});

it('traces every Blade authorization decision to its source directive', function () {
    Route::middleware(ProfileRequest::class)->get('/profiled-blade-authorization', function () {
        Gate::define('inspect-profile', fn (mixed $user, ProfiledModel $model): bool => $user === null && $model instanceof ProfiledModel);
        Gate::define('delete-profile', fn (): bool => false);

        return view('authorization-context', ['model' => new ProfiledModel]);
    });

    $response = $this->get('/profiled-blade-authorization')->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);
    $items = $profile['inspectors']['authorization']['payload']['items'];

    expect($items)->toHaveCount(2)
        ->and($items[0]['ability'])->toBe('inspect-profile')
        ->and($items[0]['callsite'])->toMatchArray([
            'file' => 'tests/Fixtures/views/authorization-context.blade.php',
            'line' => 1,
        ])
        ->and($items[1]['ability'])->toBe('delete-profile')
        ->and($items[1]['callsite'])->toMatchArray([
            'file' => 'tests/Fixtures/views/authorization-context.blade.php',
            'line' => 5,
        ]);
});

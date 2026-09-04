<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use NewDebugBar\Livewire\DebugBar;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\RequestContext;
use NewDebugBar\Tests\Fixtures\Events\ProfiledApplicationEvent;
use NewDebugBar\Tests\Fixtures\Events\ProfiledApplicationListener;
use NewDebugBar\Tests\Fixtures\Events\ProfiledQueuedApplicationListener;
use NewDebugBar\Tests\Fixtures\Models\ProfiledModel;

it('captures Laravel decisions sources transactions and view data', function () {
    $response = $this->get('/profiled-context', ['Accept' => 'text/html'])->assertOk();
    $stored = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $profile = app(ProfilePresenter::class)->present($stored);

    expect($profile['sections']['authorization']['payload']['items'][0])
        ->ability->toBe('inspect-profile')
        ->result->toBe('allowed')
        ->handler->toBe('callback')
        ->argument_types->toBe([ProfiledModel::class])
        ->and($profile['sections']['queries']['summary'])
        ->transaction_count->toBe(2)
        ->rollback_count->toBe(1)
        ->and(array_column($profile['sections']['queries']['payload']['transactions'], 'kind'))
        ->toBe(['begin', 'rollback'])
        ->and($profile['sections']['views']['payload']['items'][0])
        ->data->label->toBe('Context view')
        ->data->private_value->toBe('view-data-value')
        ->data->rows->toBe([[
            'reference' => 'NL-1042',
            'ready' => true,
            'version_count' => 2,
        ]])
        ->render_order->toBe(1)
        ->source->file->toBe('tests/Fixtures/views/context.blade.php')
        ->and($profile['sections'])->not->toHaveKey('messages');

    $event = collect($profile['sections']['events']['payload']['items'])
        ->firstWhere('name', ProfiledApplicationEvent::class);

    expect($event)->not->toBeNull()
        ->and($event['broadcast'])->toBeFalse()
        ->and($event['listeners'][0]['name'])->toBe(ProfiledApplicationListener::class.'@handle')
        ->and($event['listeners'][0]['source']['file'])->toBe('tests/Fixtures/Events/ProfiledApplicationListener.php')
        ->and($event['listeners'][0]['registrations'])->toBe(2)
        ->and($event['listeners'][0]['outcome'])->toBe('completed')
        ->and($event['listeners'][1]['name'])->toBe(ProfiledQueuedApplicationListener::class.'@handle')
        ->and($event['listeners'][1]['registrations'])->toBe(1)
        ->and($event['listeners'][1]['outcome'])->toBe('queued')
        ->and($event['payload_shape'][0]['type'])->toBe(ProfiledApplicationEvent::class)
        ->and($event['payload_shape'][0]['fields'])->toBe(['trip', 'changes'])
        ->and($event['callsite']['file'])->toBe('tests/Support/DefinesTestApplication.php')
        ->and($event['callsite']['line'])->toBeGreaterThan(0)
        ->and(json_encode($event))->not->toContain('kyoto-autumn', 'itinerary', 'bookings');
});

it('captures validation field and rule names with the rendered redirect status', function () {
    $response = $this->from('/form')->post('/profiled-validation');

    $response->assertRedirect('/form')->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $validation = $profile['sections']['validation']['payload']['items'][0];

    expect($validation)
        ->source->toBe('exception')
        ->fields->toBe(['email', 'name'])
        ->rules->email->toContain('Email')
        ->rules->name->toContain('Required')
        ->messages->name->toContain('The name field is required.')
        ->error_bag->toBe('signup')
        ->exception_class->toBe(ValidationException::class)
        ->exception_message->not->toBeEmpty()
        ->exception_status->toBe(422)
        ->response_status->toBe(302)
        ->callsite->file->toBe('tests/Support/DefinesTestApplication.php')
        ->and($profile['sections']['exceptions']['summary']['count'])->toBe(0);

    $component = Livewire::test(DebugBar::class, ['profileId' => $response->headers->get('X-NewDebugBar-Profile')])
        ->call('loadSection', 'validation')
        ->assertSet('selectedSection', 'validation')
        ->assertSet('profile.sections.validation.payload.items.0.fields', ['email', 'name'])
        ->assertSet('profile.sections.validation.payload.items.0.error_bag', 'signup')
        ->assertSet('profile.sections.validation.payload.items.0.exception_status', 422)
        ->assertSet('profile.sections.validation.payload.items.0.response_status', 302)
        ->assertSet('profile.sections.validation.payload.items.0.messages', $validation['messages'])
        ->assertDispatched('newdebugbar-section-loaded', section: 'validation');

    expect($component->effects)->not->toHaveKey('html')
        ->and($component->effects['islandFragments'] ?? [])->toHaveCount(1);
});

it('carries redirected validation messages into the next profiled page', function () {
    config(['session.driver' => 'array']);
    $errors = new ViewErrorBag;
    $errors->put('checkout', new MessageBag([
        'email' => ['The email has already been taken.'],
        'team' => ['The selected team is invalid.'],
    ]));

    $response = $this->withSession(['errors' => $errors])
        ->get('/profiled-session-validation')
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $validation = $profile['sections']['validation']['payload']['items'][0];

    expect($validation)
        ->source->toBe('session')
        ->from_previous_request->toBeTrue()
        ->fields->toBe(['email', 'team'])
        ->rules->toBe(['email' => [], 'team' => []])
        ->messages->email->toBe(['The email has already been taken.'])
        ->error_bag->toBe('checkout')
        ->not->toHaveKey('response_status')
        ->and($profile['sections']['validation']['summary']['count'])->toBe(1);

    $component = Livewire::test(DebugBar::class, ['profileId' => $response->headers->get('X-NewDebugBar-Profile')])
        ->call('loadSection', 'validation')
        ->assertSet('selectedSection', 'validation')
        ->assertSet('profile.sections.validation.payload.items.0.from_previous_request', true)
        ->assertSet('profile.sections.validation.payload.items.0.rules', ['email' => [], 'team' => []])
        ->assertSet('profile.sections.validation.payload.items.0.messages', $validation['messages'])
        ->assertDispatched('newdebugbar-section-loaded', section: 'validation');

    expect($component->effects)->not->toHaveKey('html')
        ->and($component->effects['islandFragments'] ?? [])->toHaveCount(1);
});

it('shows authentication and session shape without identity or values', function () {
    $request = Request::create('/account');
    $user = new GenericUser(['id' => 42, 'email' => 'private@example.test']);
    $request->setUserResolver(fn () => $user);
    $session = new Store('context-test', new ArraySessionHandler(120));
    $session->start();
    $session->put('clinic_id', 99);
    $session->flash('notice', 'private flash value');
    $errors = new ViewErrorBag;
    $errors->put('signup', new MessageBag(['email' => ['Private error message']]));
    $session->put('errors', $errors);
    $request->setLaravelSession($session);

    $context = app(RequestContext::class);
    $authentication = $context->authentication($request, ['auth:web']);
    $shape = $context->session($request);

    expect($authentication)
        ->guard->toBe('web')
        ->authenticated->toBeTrue()
        ->model->toBe(GenericUser::class)
        ->identifier->toStartWith('hmac:')
        ->identifier->not->toContain('42', 'private@example.test')
        ->and($shape)
        ->present->toBeTrue()
        ->keys->toContain('clinic_id', 'notice', 'errors')
        ->flash_keys->toContain('notice')
        ->error_bag_present->toBeTrue()
        ->error_bags->toBe(['signup'])
        ->and(json_encode([$authentication, $shape]))
        ->not->toContain('private@example.test', 'private flash value', 'Private error message', '99');
});

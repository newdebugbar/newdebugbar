<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use NewDebugBar\Contracts\Collector;
use NewDebugBar\Http\Controllers\AssetController;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\AssetUrl;
use NewDebugBar\Support\BarInjector;
use NewDebugBar\Support\Redactor;
use NewDebugBar\Support\RequestEligibility;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('preserves Laravel original response metadata while injecting HTML', function () {
    $view = view()->file(__DIR__.'/../../Fixtures/views/original-response.blade.php', [
        'label' => 'Original response',
    ]);
    $response = response($view);

    app(BarInjector::class)->inject($response, (string) Str::uuid());

    expect($response->getOriginalContent())->toBe($view)
        ->and($response->getContent())->toContain('id="newdebugbar"');
});

it('loads the toolbar state before Livewire starts Alpine', function () {
    $response = response('<!doctype html><html><body>Application response</body></html>');

    app(BarInjector::class)->inject($response, (string) Str::uuid());

    $content = $response->getContent();
    $debugBarScript = strpos($content, '/__newdebugbar/assets/newdebugbar.js');
    $toolbar = strpos($content, 'id="newdebugbar"');
    $livewireScript = strpos($content, 'data-update-uri=');

    expect($debugBarScript)->toBeInt()
        ->and($toolbar)->toBeInt()
        ->and($livewireScript)->toBeInt()
        ->and($debugBarScript)->toBeLessThan($toolbar)
        ->and($toolbar)->toBeLessThan($livewireScript);
});

it('injects the toolbar when the Livewire update route exists before boot', function () {
    $updateUri = app('livewire')->getUpdateUri();

    // Cached routes are already registered when Livewire's request mechanism boots.
    app()->forgetInstance(HandleRequests::class);
    app(HandleRequests::class)->boot();

    $response = response('<!doctype html><html><head></head><body>Application response</body></html>');
    app(BarInjector::class)->inject($response, (string) Str::uuid());

    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $html = new DOMXPath($document);

    expect($html->query('//*[@id="newdebugbar"]')->length)->toBe(1)
        ->and($html->query('//script[@data-update-uri]')->length)->toBe(1)
        ->and(parse_url($html->evaluate('string(//script/@data-update-uri)'), PHP_URL_PATH))->toBe($updateUri);
});

it('serves its compiled assets through local package routes', function () {
    $response = $this->get('/__newdebugbar/assets/newdebugbar.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $stylesheet = File::get($response->baseResponse->getFile()->getPathname());

    expect($stylesheet)
        ->not->toContain('@layer theme')
        ->not->toContain('@layer utilities', '@keyframes pulse{')
        ->toContain('@keyframes ndb-debug-bar-pulse{');
});

it('injects assets into an html document that has no head', function () {
    $this->get('/html-without-head', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertSee('<html><head><style id="newdebugbar-critical-css"', false)
        ->assertSee('id="newdebugbar"', false);
});

it('leaves response types that cannot host the bar untouched', function (string $path, int $status) {
    $response = $this->get($path, ['Accept' => 'text/html'])
        ->assertStatus($status)
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertDontSee('id="newdebugbar"', false);

    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));

    expect($profile)->not->toBeNull()
        ->and($profile['sections']['request']['payload']['request_type'])->toBe(match ($path) {
            '/download' => 'download',
            default => 'full_page',
        });
})->with([
    'html without a body' => ['/html-without-body', 200],
    'plain text' => ['/plain-text', 200],
    'download' => ['/download', 200],
]);

it('profiles an html error response', function () {
    $this->get('/failed-html', ['Accept' => 'text/html'])
        ->assertUnprocessable()
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertSee('id="newdebugbar"', false);
});

it('preserves a profile when the application throws', function () {
    $response = $this->get('/profiled-exception', ['Accept' => 'text/html'])
        ->assertInternalServerError()
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertSee('id="newdebugbar"', false);

    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));

    expect($profile['sections']['request']['summary']['status'])->toBe(500)
        ->and($profile['sections']['exceptions']['summary']['count'])->toBe(1)
        ->and($profile['sections']['exceptions']['payload']['items'][0]['class'])->toBe(RuntimeException::class)
        ->and($profile['sections']['exceptions']['payload']['items'][0]['file'])->toBe('tests/Support/DefinesTestApplication.php')
        ->and($profile['sections']['exceptions']['payload']['items'][0])->not->toHaveKey('trace')
        ->and($profile['sections']['exceptions']['payload']['items'][0]['frames']['application'])->not->toBeEmpty()
        ->and($profile['sections']['exceptions']['payload']['items'][0]['source']['lines'])->not->toBeEmpty()
        ->and($profile['sections']['exceptions']['payload']['items'][0]['causes'])->toBe([])
        ->and($profile['sections']['exceptions']['payload']['items'][0]['chain_truncated'])->toBeFalse()
        ->and(app(ProfileManager::class)->isCollecting())->toBeFalse();
});

it('rejects unknown and unsafe package assets', function () {
    $this->get('/__newdebugbar/assets/unknown.txt')->assertNotFound();

    expect(fn () => app(AssetController::class)('../composer.json'))
        ->toThrow(NotFoundHttpException::class)
        ->and(fn () => app(AssetUrl::class)->for('../composer.json'))
        ->toThrow(RuntimeException::class);
});

it('profiles JSON without changing its body or injecting the toolbar', function () {
    $response = $this->getJson('/plain-json')
        ->assertOk()
        ->assertExactJson(['ready' => true])
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertDontSee('id="newdebugbar"', false);

    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));

    expect($profile['sections']['request']['payload']['request_type'])->toBe('json')
        ->and($profile['sections']['request']['payload']['response_size_bytes'])
        ->toBe(strlen(json_encode(['ready' => true])));
});

it('profiles API AJAX redirect streamed and binary responses without body injection', function () {
    $api = $this->getJson('/api/plain-json')
        ->assertOk()
        ->assertExactJson(['source' => 'api'])
        ->assertHeader('X-NewDebugBar-Profile');

    $ajax = $this->get('/ajax-fragment', [
        'Accept' => 'text/html',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertContent('<div data-fragment>Search result</div>')
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertDontSee('id="newdebugbar"', false);

    $redirect = $this->get('/profile-redirect', ['Accept' => 'text/html'])
        ->assertRedirect('/profiled')
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertDontSee('id="newdebugbar"', false);

    $stream = $this->get('/streamed-response', ['Accept' => 'text/plain'])
        ->assertOk()
        ->assertHeader('X-NewDebugBar-Profile')
        ->assertStreamedContent('streamed-body');

    $binary = $this->get('/binary-response')
        ->assertOk()
        ->assertDownload('original-response.txt')
        ->assertHeader('X-NewDebugBar-Profile');

    $profiles = collect([
        'json' => $api,
        'ajax' => $ajax,
        'redirect' => $redirect,
        'stream' => $stream,
        'download' => $binary,
    ])->map(fn ($response) => app(ProfileStore::class)->get(
        $response->headers->get('X-NewDebugBar-Profile'),
    ));

    expect($profiles->map(fn (array $profile): string => $profile['sections']['request']['payload']['request_type'])->all())
        ->toBe([
            'json' => 'json',
            'ajax' => 'ajax',
            'redirect' => 'redirect',
            'stream' => 'stream',
            'download' => 'download',
        ]);
});

it('returns the application response when a collector fails', function () {
    app('livewire')->flushState();

    $manager = new ProfileManager(
        [new CollectorThatFailsDuringSummary],
        $this->app->make(Redactor::class),
    );
    $this->app->instance(ProfileManager::class, $manager);

    $this->get('/profiled-collector-failure')
        ->assertOk()
        ->assertContent('<!doctype html><html><body>Application response</body></html>')
        ->assertHeaderMissing('X-NewDebugBar-Profile');

    expect($manager->isCollecting())->toBeFalse();
});

it('discards request state when profiling setup fails', function () {
    $manager = app(ProfileManager::class);
    $middleware = new ProfileRequest(
        $manager,
        app(RequestEligibility::class),
    );
    $request = Request::create('/setup-failure', 'POST', [
        'value' => new StringableThatFails,
    ], server: ['HTTP_ACCEPT' => 'text/html']);

    $response = $middleware->handle(
        $request,
        fn () => new Response('<html><body>Application response</body></html>'),
    );

    expect($response->getContent())->toBe('<html><body>Application response</body></html>')
        ->and($manager->isCollecting())->toBeFalse();
});

final class CollectorThatFailsDuringSummary implements Collector
{
    public function key(): string
    {
        return 'failing';
    }

    public function label(): string
    {
        return 'Failing';
    }

    public function reset(): void {}

    public function record(array $item): void {}

    public function summary(): array
    {
        throw new RuntimeException('Collector failed.');
    }

    public function payload(): array
    {
        return ['items' => []];
    }
}

final class StringableThatFails
{
    public function __toString(): string
    {
        throw new RuntimeException('String conversion failed.');
    }
}

<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\LazyCollection;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Mcp\NewDebugBarServer;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\CountingArrayable;
use NewDebugBar\Tests\Fixtures\Models\ProfiledModel;
use NewDebugBar\Tests\Support\McpResponse;

it('captures stored model and collection values without serialising the included scope', function () {
    $catalog = new CountingArrayable(['one', 'two', 'three']);
    $cards = EloquentCollection::make([
        (new ProfiledModel)->setRawAttributes(['id' => 7, 'name' => 'First card'], true),
        (new ProfiledModel)->setRawAttributes(['id' => 8, 'name' => 'Second card'], true),
    ]);

    Route::middleware(ProfileRequest::class)->get('/profiled-includes', fn () => view('summary-host', [
        'label' => 'Included cards',
        'cards' => $cards,
        'catalog' => $catalog,
        'stream' => LazyCollection::times(3, fn (): string => throw new RuntimeException('Lazy collections must not be iterated while profiling.')),
        'nested' => ['first' => $cards->first()],
    ]));

    $response = $this->get('/profiled-includes')->assertOk()
        ->assertSee('First card')
        ->assertSee('Second card')
        ->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $views = collect($profile['inspectors']['views']['payload']['items']);
    $cardViews = $views->where('name', 'summary-card');

    // Blade's @include forwards the host scope, so each card carries the full collections again.
    expect($catalog->toArrayCalls)->toBe(0)
        ->and($cardViews)->toHaveCount(2)
        ->and($views->firstWhere('name', 'summary-host')['data'])->toMatchArray([
            'label' => 'Included cards',
            'cards' => [['id' => 7, 'name' => 'First card'], ['id' => 8, 'name' => 'Second card']],
            'catalog' => '['.CountingArrayable::class.']',
            'stream' => '['.LazyCollection::class.']',
            'nested' => ['first' => ['id' => 7, 'name' => 'First card']],
        ])
        ->and($cardViews->first()['data'])->toMatchArray([
            'card' => ['id' => 7, 'name' => 'First card'],
            'cards' => [['id' => 7, 'name' => 'First card'], ['id' => 8, 'name' => 'Second card']],
            'catalog' => '['.CountingArrayable::class.']',
        ]);
});

it('keeps a view whose strict Eloquent model was loaded without its primary key', function () {
    $previous = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $row = (new ProfiledModel)->newFromBuilder(['name' => 'Selected column']);
        Route::middleware(ProfileRequest::class)->get('/profiled-missing-view-key', fn () => view('original-response', [
            'label' => 'Page still renders',
            'row' => $row,
        ]));

        $response = $this->get('/profiled-missing-view-key')->assertOk()->assertSee('Page still renders')
            ->assertHeader('X-NewDebugBar-Profile');
        $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
        $views = collect($profile['inspectors']['views']['payload']['items'])->where('name', 'original-response');

        expect($views)->toHaveCount(1)
            ->and($views->first()['data']['row'])->toBe(['name' => 'Selected column']);
    } finally {
        Model::preventAccessingMissingAttributes($previous);
    }
});

it('captures each render at its current values without evaluating model accessors', function () {
    $row = new class extends Model
    {
        protected $appends = ['computed'];

        public function getIdAttribute(mixed $value): never
        {
            throw new RuntimeException('Profiling must not read the ID accessor.');
        }

        public function getComputedAttribute(): never
        {
            throw new RuntimeException('Profiling must not evaluate appended attributes.');
        }
    };
    $row->setRawAttributes(['id' => 7, 'name' => 'First value'], true);
    Route::middleware(ProfileRequest::class)->get('/profiled-changing-view-data', function () use ($row) {
        $first = view('original-response', ['label' => 'First render', 'row' => $row])->render();
        $row->name = 'Second value';
        $second = view('original-response', ['label' => 'Second render', 'row' => $row])->render();

        return response($first.$second);
    });

    $response = $this->get('/profiled-changing-view-data')->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($response->headers->get('X-NewDebugBar-Profile'));
    $views = collect($profile['inspectors']['views']['payload']['items'])->where('name', 'original-response')->values();

    expect($views)->toHaveCount(2)
        ->and($views[0]['data']['row'])->toBe(['id' => 7, 'name' => 'First value'])
        ->and($views[1]['data']['row'])->toBe(['id' => 7, 'name' => 'Second value']);
});

it('exposes retained collection values through the exact MCP data tool', function () {
    $response = $this->get('/profiled-context')->assertOk()->assertHeader('X-NewDebugBar-Profile');
    $profileId = $response->headers->get('X-NewDebugBar-Profile');
    $profile = app(ProfileStore::class)->get($profileId);
    $index = collect($profile['inspectors']['views']['payload']['items'])->search(fn (array $view): bool => $view['name'] === 'context');
    expect($index)->not->toBeFalse();

    $result = McpResponse::structuredContent(NewDebugBarServer::tool(GetDebugProfileData::class, [
        'profile_id' => $profileId,
        'path' => '/inspectors/views/payload/items/'.$index.'/data/rows/0/reference',
    ])->assertOk());

    expect($result['data']['value'])->toBe('NL-1042');
});

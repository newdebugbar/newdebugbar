<?php

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\LazyCollection;
use NewDebugBar\Http\Middleware\ProfileRequest;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Fixtures\CountingArrayable;
use NewDebugBar\Tests\Fixtures\Models\ProfiledModel;

it('labels models and collections in view data instead of serialising them for every include', function () {
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
            'cards' => '['.EloquentCollection::class.': 2 items]',
            'catalog' => '['.CountingArrayable::class.']',
            'stream' => '['.LazyCollection::class.']',
            'nested' => ['first' => '['.ProfiledModel::class.'#7]'],
        ])
        ->and($cardViews->first()['data'])->toMatchArray([
            'card' => '['.ProfiledModel::class.'#7]',
            'cards' => '['.EloquentCollection::class.': 2 items]',
            'catalog' => '['.CountingArrayable::class.']',
        ]);
});

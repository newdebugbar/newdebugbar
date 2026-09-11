<?php

namespace NewDebugBar\Tests\Support;

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;

final class DebugBarAssertions
{
    /** Livewire's HTML assertions inspect the root, not returned island fragments. */
    public static function sectionResponse(Testable $component): TestResponse
    {
        expect($component->effects)->not->toHaveKey('html')
            ->and($component->effects['islandFragments'] ?? [])->toHaveCount(1);

        return new TestResponse(new Response($component->effects['islandFragments'][0]));
    }
}

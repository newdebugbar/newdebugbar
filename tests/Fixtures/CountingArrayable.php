<?php

namespace NewDebugBar\Tests\Fixtures;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Counts how often the profiler serialises it.
 *
 * @implements Arrayable<int, string>
 */
final class CountingArrayable implements Arrayable
{
    public int $toArrayCalls = 0;

    /** @param array<int, string> $items */
    public function __construct(private readonly array $items) {}

    /** @return array<int, string> */
    public function toArray(): array
    {
        $this->toArrayCalls++;

        return $this->items;
    }
}

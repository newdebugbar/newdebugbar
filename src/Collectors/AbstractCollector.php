<?php

namespace NewDebugBar\Collectors;

use NewDebugBar\Contracts\Collector;
use NewDebugBar\Support\Redactor;

/** Redacts and bounds captured items while preserving complete summary totals. */
abstract class AbstractCollector implements Collector
{
    /** @var array<int, array<string, mixed>> */
    protected array $items = [];

    protected int $dropped = 0;

    /** @var array<string, int|float> */
    protected array $totals = [];

    public function __construct(
        protected readonly Redactor $redactor,
        protected readonly int $maxItems,
    ) {}

    public function reset(): void
    {
        $this->items = [];
        $this->dropped = 0;
        $this->totals = [];
    }

    public function record(array $item): void
    {
        $this->track($item);

        if ($this->retainedCount() >= $this->maxItems) {
            $this->dropped++;

            return;
        }

        $this->items[] = $this->cleanItem($item);
    }

    public function summary(): array
    {
        $retained = count($this->items);

        return [
            'count' => $retained + $this->dropped,
            'retained_count' => $retained,
            'dropped_count' => $this->dropped,
            'truncated' => $this->dropped > 0,
        ];
    }

    public function payload(): array
    {
        return [
            'items' => $this->items,
        ];
    }

    /** @param array<string, mixed> $item */
    protected function track(array $item): void {}

    /** @param array<string, mixed> $item @return array<string, mixed> */
    protected function cleanItem(array $item): array
    {
        /** @var array<string, mixed> $clean */
        $clean = $this->redactor->clean($item);

        return $clean;
    }

    protected function retainedCount(): int
    {
        return count($this->items);
    }
}

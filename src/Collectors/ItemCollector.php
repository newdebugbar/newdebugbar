<?php

namespace NewDebugBar\Collectors;

use NewDebugBar\Support\Redactor;

/** Collects bounded items for a named inspector without custom metrics. */
final class ItemCollector extends AbstractCollector
{
    public function __construct(
        Redactor $redactor,
        int $maxItems,
        private readonly string $collectorKey,
        private readonly string $collectorLabel,
    ) {
        parent::__construct($redactor, $maxItems);
    }

    public function key(): string
    {
        return $this->collectorKey;
    }

    public function label(): string
    {
        return $this->collectorLabel;
    }
}

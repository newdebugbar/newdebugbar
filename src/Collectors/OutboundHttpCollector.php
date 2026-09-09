<?php

namespace NewDebugBar\Collectors;

/** Correlates Laravel HTTP client starts with bounded completion facts. */
final class OutboundHttpCollector extends AbstractCollector
{
    /** @var array<int, int> */
    private array $startedAt = [];

    public function key(): string
    {
        return 'http_client';
    }

    public function label(): string
    {
        return 'HTTP Client';
    }

    public function reset(): void
    {
        parent::reset();
        $this->startedAt = [];
    }

    public function record(array $item): void
    {
        $requestId = (int) ($item['request_id'] ?? 0);
        $phase = $item['phase'] ?? 'completed';
        unset($item['request_id'], $item['phase']);

        if ($phase === 'sending') {
            if ($requestId > 0) {
                $this->startedAt[$requestId] = hrtime(true);
            }

            return;
        }

        if (! isset($item['duration_ms']) || $item['duration_ms'] === null) {
            $startedAt = $this->startedAt[$requestId] ?? null;
            $item['duration_ms'] = $startedAt === null ? 0.0 : round((hrtime(true) - $startedAt) / 1_000_000, 2);
        }

        unset($this->startedAt[$requestId]);
        parent::record($item);
    }

    public function summary(): array
    {
        return [
            ...parent::summary(),
            'duration_ms' => round($this->totals['duration_ms'] ?? 0, 2),
            'failed_count' => (int) ($this->totals['failed_count'] ?? 0),
        ];
    }

    protected function track(array $item): void
    {
        $this->totals['duration_ms'] = ($this->totals['duration_ms'] ?? 0) + (float) ($item['duration_ms'] ?? 0);
        $this->totals['failed_count'] = ($this->totals['failed_count'] ?? 0) + (($item['failed'] ?? false) ? 1 : 0);
    }
}

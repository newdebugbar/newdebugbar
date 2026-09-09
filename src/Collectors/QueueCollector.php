<?php

namespace NewDebugBar\Collectors;

/** Records queued dispatches and correlates synchronous job execution. */
final class QueueCollector extends AbstractCollector
{
    /** @var array<int, int> */
    private array $startedAt = [];

    public function key(): string
    {
        return 'queue';
    }

    public function label(): string
    {
        return 'Queue';
    }

    public function reset(): void
    {
        parent::reset();
        $this->startedAt = [];
    }

    public function record(array $item): void
    {
        $executionId = (int) ($item['execution_id'] ?? 0);
        $phase = $item['phase'] ?? null;
        unset($item['execution_id'], $item['phase']);

        if ($phase === 'processing') {
            if ($executionId > 0) {
                $this->startedAt[$executionId] = hrtime(true);
            }

            return;
        }

        if (in_array($phase, ['processed', 'failed'], true)) {
            $startedAt = $this->startedAt[$executionId] ?? null;
            $item['duration_ms'] = $startedAt === null ? 0.0 : round((hrtime(true) - $startedAt) / 1_000_000, 2);
            unset($this->startedAt[$executionId]);
        }

        parent::record($item);
    }

    public function summary(): array
    {
        return [
            ...parent::summary(),
            'queued_count' => (int) ($this->totals['queued_count'] ?? 0),
            'executed_count' => (int) ($this->totals['executed_count'] ?? 0),
            'failed_count' => (int) ($this->totals['failed_count'] ?? 0),
            'duration_ms' => round($this->totals['duration_ms'] ?? 0, 2),
        ];
    }

    protected function track(array $item): void
    {
        $kind = $item['kind'] ?? null;
        $this->totals['queued_count'] = ($this->totals['queued_count'] ?? 0) + ($kind === 'queued' ? 1 : 0);
        $this->totals['executed_count'] = ($this->totals['executed_count'] ?? 0) + (in_array($kind, ['executed', 'failed'], true) ? 1 : 0);
        $this->totals['failed_count'] = ($this->totals['failed_count'] ?? 0) + ($kind === 'failed' ? 1 : 0);
        $this->totals['duration_ms'] = ($this->totals['duration_ms'] ?? 0) + (float) ($item['duration_ms'] ?? 0);
    }
}

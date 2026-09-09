<?php

namespace NewDebugBar\Collectors;

/** Pairs notification delivery events and keeps bounded evidence for each channel attempt. */
final class NotificationCollector extends AbstractCollector
{
    /** @var array<string, int> */
    private array $startedAt = [];

    /** @var array<string, array<string, mixed>> */
    private array $pending = [];

    /** @var array<string, true> */
    private array $notificationGroups = [];

    /** @var array<string, true> */
    private array $failedGroups = [];

    public function key(): string
    {
        return 'notifications';
    }

    public function label(): string
    {
        return 'Notifications';
    }

    public function reset(): void
    {
        parent::reset();
        $this->startedAt = [];
        $this->pending = [];
        $this->notificationGroups = [];
        $this->failedGroups = [];
    }

    public function record(array $item): void
    {
        $attemptId = (string) ($item['attempt_id'] ?? '');
        $phase = (string) ($item['phase'] ?? $item['status'] ?? 'sent');
        unset($item['attempt_id'], $item['phase']);

        if ($phase === 'sending') {
            if ($attemptId !== '') {
                $this->startedAt[$attemptId] = hrtime(true);
                $this->pending[$attemptId] = $item;
            }

            return;
        }

        $startedAt = $this->startedAt[$attemptId] ?? null;
        $item = [
            ...$item,
            ...($this->pending[$attemptId] ?? []),
        ];
        $item['duration_ms'] = $startedAt === null
            ? round((float) ($item['duration_ms'] ?? 0), 2)
            : round((hrtime(true) - $startedAt) / 1_000_000, 2);
        unset(
            $item['notification_object_id'],
            $item['notifiable_object_id'],
            $this->startedAt[$attemptId],
            $this->pending[$attemptId],
        );

        parent::record($item);
    }

    public function summary(): array
    {
        $summary = parent::summary();

        return [
            ...$summary,
            'delivery_count' => $summary['count'],
            'notification_count' => count($this->notificationGroups),
            'failed_notification_count' => count($this->failedGroups),
            'sent_count' => (int) ($this->totals['sent_count'] ?? 0),
            'failed_count' => (int) ($this->totals['failed_count'] ?? 0),
            'duration_ms' => round((float) ($this->totals['duration_ms'] ?? 0), 2),
        ];
    }

    protected function track(array $item): void
    {
        $status = $item['status'] ?? null;
        $groupId = (string) ($item['group_id'] ?? $item['correlation_key'] ?? '');

        if ($groupId !== '') {
            $this->notificationGroups[$groupId] = true;

            if ($status === 'failed') {
                $this->failedGroups[$groupId] = true;
            }
        }

        $this->totals['sent_count'] = ($this->totals['sent_count'] ?? 0) + ($status === 'sent' ? 1 : 0);
        $this->totals['failed_count'] = ($this->totals['failed_count'] ?? 0) + ($status === 'failed' ? 1 : 0);
        $this->totals['duration_ms'] = ($this->totals['duration_ms'] ?? 0) + (float) ($item['duration_ms'] ?? 0);
    }
}

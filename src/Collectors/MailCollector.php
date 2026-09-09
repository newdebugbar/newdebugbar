<?php

namespace NewDebugBar\Collectors;

/** Captures mail shape and bounded previews with retained attachment data. */
final class MailCollector extends AbstractCollector
{
    /** @var array<int, int> */
    private array $startedAt = [];

    /** @var array<int, array<string, mixed>> */
    private array $pending = [];

    public function key(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return 'Mail';
    }

    public function reset(): void
    {
        parent::reset();
        $this->startedAt = [];
        $this->pending = [];
    }

    public function record(array $item): void
    {
        $messageId = (int) ($item['message_id'] ?? 0);
        $phase = $item['phase'] ?? 'sent';
        unset($item['message_id'], $item['phase']);

        if ($phase === 'sending') {
            if ($messageId > 0) {
                $this->startedAt[$messageId] = hrtime(true);
                $this->pending[$messageId] = $item;
            }

            return;
        }

        $startedAt = $this->startedAt[$messageId] ?? null;
        $item = [
            ...$item,
            ...($this->pending[$messageId] ?? []),
        ];
        $item['duration_ms'] = $startedAt === null ? 0.0 : round((hrtime(true) - $startedAt) / 1_000_000, 2);
        unset($this->startedAt[$messageId], $this->pending[$messageId]);

        parent::record($item);
    }

    public function summary(): array
    {
        return [
            ...parent::summary(),
            'recipient_count' => (int) ($this->totals['recipient_count'] ?? 0),
            'attachment_count' => (int) ($this->totals['attachment_count'] ?? 0),
            'duration_ms' => round($this->totals['duration_ms'] ?? 0, 2),
        ];
    }

    protected function cleanItem(array $item): array
    {
        $preview = $item['preview'] ?? null;
        unset($item['preview']);
        $clean = parent::cleanItem($item);

        if (is_array($preview)) {
            $clean['preview'] = $preview;
        }

        return $clean;
    }

    protected function track(array $item): void
    {
        $this->totals['recipient_count'] = ($this->totals['recipient_count'] ?? 0) + (int) ($item['recipient_count'] ?? 0);
        $this->totals['attachment_count'] = ($this->totals['attachment_count'] ?? 0) + (int) ($item['attachment_count'] ?? 0);
        $this->totals['duration_ms'] = ($this->totals['duration_ms'] ?? 0) + (float) ($item['duration_ms'] ?? 0);
    }
}

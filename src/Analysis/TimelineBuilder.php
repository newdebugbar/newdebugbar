<?php

namespace NewDebugBar\Analysis;

/** Builds a searchable event sequence with geometry for an honest waterfall view. */
final class TimelineBuilder
{
    /** @param array<string, mixed> $profile @return list<array<string, mixed>> */
    public function build(array $profile): array
    {
        $duration = (float) ($profile['metrics']['duration_ms'] ?? 0);
        $runtimeType = $profile['sections']['request']['payload']['runtime_type'] ?? null;
        $subject = is_string($runtimeType) ? str($runtimeType)->title()->toString() : 'Request';
        $timeline = [[
            'id' => 'request-start',
            'section' => 'request',
            'section_label' => 'Request',
            'kind' => 'milestone',
            'label' => $subject.' started',
            'source' => null,
            'at_ms' => 0.0,
            'start_ms' => null,
            'duration_ms' => null,
        ]];

        foreach ($profile['sections'] ?? [] as $section => $data) {
            if (in_array($section, ['overview', 'request', 'timeline'], true)) {
                continue;
            }

            $streams = [['name' => 'item', 'items' => $data['payload']['items'] ?? []]];

            if ($section === 'queries') {
                $streams[] = ['name' => 'transaction', 'items' => $data['payload']['transactions'] ?? []];
            }

            foreach ($streams as $stream) {
                foreach ($stream['items'] as $index => $item) {
                    if (! is_array($item) || ! isset($item['at_ms'])) {
                        continue;
                    }

                    $spanDuration = isset($item['duration_ms']) && is_numeric($item['duration_ms'])
                        ? max(0, (float) $item['duration_ms'])
                        : null;
                    $hasDuration = $spanDuration !== null && $spanDuration > 0;
                    $timeline[] = [
                        'id' => $stream['name'] === 'item'
                            ? $section.'-'.$index
                            : $section.'-'.$stream['name'].'-'.$index,
                        'section' => $section,
                        'section_label' => $this->sectionLabel($section),
                        'kind' => $hasDuration ? 'span' : 'point',
                        'label' => $this->label($section, $item),
                        'source' => $this->source($item),
                        'at_ms' => round((float) $item['at_ms'], 3),
                        'start_ms' => $hasDuration ? round(max(0, (float) $item['at_ms'] - $spanDuration), 3) : null,
                        'duration_ms' => $hasDuration ? round($spanDuration, 2) : null,
                    ];
                }
            }
        }

        $timeline[] = [
            'id' => 'request-end',
            'section' => 'request',
            'section_label' => 'Request',
            'kind' => 'milestone',
            'label' => $subject.' finished',
            'source' => null,
            'at_ms' => round($duration, 3),
            'start_ms' => null,
            'duration_ms' => null,
        ];

        usort($timeline, fn (array $left, array $right): int => $left['at_ms'] <=> $right['at_ms']
            ?: $this->kindOrder($left['kind']) <=> $this->kindOrder($right['kind']));

        $scale = max(0.001, ...array_column($timeline, 'at_ms'));

        return array_map(function (array $item) use ($scale): array {
            $item['at_percent'] = $this->percentage($item['at_ms'], $scale);
            $item['start_percent'] = $item['start_ms'] === null
                ? null
                : $this->percentage($item['start_ms'], $scale);
            $item['duration_percent'] = $item['duration_ms'] === null
                ? null
                : round(min(100, max(0, ($item['duration_ms'] / $scale) * 100)), 3);

            return $item;
        }, $timeline);
    }

    /** @param array<string, mixed> $profile @return array<string, int> */
    public function omittedSources(array $profile): array
    {
        $omitted = [];

        foreach ($profile['sections'] ?? [] as $section => $data) {
            $dropped = (int) ($data['summary']['dropped_count'] ?? 0)
                + (int) ($data['summary']['storage_omitted_items'] ?? 0);

            if ($dropped > 0) {
                $omitted[(string) $section] = $dropped;
            }

            $transactionDropped = (int) ($data['summary']['transaction_dropped_count'] ?? 0);

            if ($transactionDropped > 0) {
                $omitted['query_transactions'] = $transactionDropped;
            }
        }

        foreach ($profile['storage']['omitted_sections'] ?? [] as $section) {
            $omitted[$section] ??= max(1, (int) ($profile['sections'][$section]['summary']['retained_count'] ?? 0));
        }

        return $omitted;
    }

    /** @param array<string, mixed> $item */
    private function label(string $section, array $item): string
    {
        $label = match ($section) {
            'queries' => ($item['kind'] ?? null) !== null
                ? 'Transaction '.($item['kind'] ?? 'event').' '.($item['connection'] ?? '')
                : ($item['normalized_sql'] ?? $item['sql'] ?? 'Query'),
            'http_client' => trim(($item['method'] ?? '').' '.($item['url'] ?? 'HTTP request')),
            'queue' => trim(($item['kind'] ?? '').' '.($item['job'] ?? 'Job')),
            'mail' => 'Mail sent'.(($item['mailable'] ?? null) ? ' '.$item['mailable'] : ''),
            'notifications' => trim(($item['status'] ?? '').' '.($item['notification'] ?? 'Notification')),
            'redis' => trim(($item['command'] ?? 'Redis').' '.($item['connection'] ?? '')),
            'models' => trim(($item['event'] ?? '').' '.($item['model'] ?? 'Model')),
            'cache' => trim(($item['operation'] ?? 'Cache').' '.($item['key_hash'] ?? '')),
            'views' => $item['name'] ?? 'View rendered',
            'events' => $item['name'] ?? 'Event dispatched',
            'logs' => strtoupper((string) ($item['level'] ?? 'log')).' '.($item['message'] ?? ''),
            'exceptions' => $item['class'] ?? 'Exception',
            'authorization' => trim(ucfirst((string) ($item['result'] ?? 'checked')).' '.($item['ability'] ?? 'authorization')),
            'validation' => $this->validationLabel($item),
            default => $item['name'] ?? $item['event'] ?? $item['operation'] ?? ucfirst($section),
        };

        $label = (string) $label;

        return mb_strlen($label) > 140 ? mb_substr($label, 0, 139).'…' : $label;
    }

    private function sectionLabel(string $section): string
    {
        return match ($section) {
            'http_client' => 'HTTP Client',
            'redis' => 'Redis',
            'livewire' => 'Livewire',
            default => str($section)->replace('_', ' ')->title()->toString(),
        };
    }

    /** @param array<string, mixed> $item */
    private function validationLabel(array $item): string
    {
        $fields = array_values(array_filter(
            array_map('strval', is_array($item['fields'] ?? null) ? $item['fields'] : []),
            static fn (string $field): bool => $field !== '',
        ));

        if ($fields === []) {
            return 'Validation failed';
        }

        $visible = implode(', ', array_slice($fields, 0, 3));

        return count($fields) > 3
            ? 'Validation failed: '.$visible.' and '.(count($fields) - 3).' more'
            : 'Validation failed: '.$visible;
    }

    /** @param array<string, mixed> $item @return array{file: string, line: int}|null */
    private function source(array $item): ?array
    {
        foreach ([$item['callsite'] ?? null, $item['source'] ?? null] as $source) {
            if (is_array($source) && is_string($source['file'] ?? null) && $source['file'] !== '') {
                return [
                    'file' => $source['file'],
                    'line' => max(1, (int) ($source['line'] ?? 1)),
                ];
            }
        }

        if (is_string($item['file'] ?? null) && $item['file'] !== '') {
            return [
                'file' => $item['file'],
                'line' => max(1, (int) ($item['line'] ?? 1)),
            ];
        }

        return null;
    }

    private function kindOrder(string $kind): int
    {
        return match ($kind) {
            'milestone' => 0,
            'span' => 1,
            default => 2,
        };
    }

    private function percentage(float $value, float $scale): float
    {
        return round(min(100, max(0, ($value / $scale) * 100)), 3);
    }
}

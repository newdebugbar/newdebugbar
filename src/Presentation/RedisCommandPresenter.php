<?php

namespace NewDebugBar\Presentation;

use Illuminate\Support\Str;
use NewDebugBar\Support\DurationFormatter;

/** Prepares retained redis evidence for the inspector and MCP. */
final class RedisCommandPresenter
{
    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    public function present(array $items): array
    {
        $formatDuration = DurationFormatter::format(...);

        return collect($items)->values()->map(function (array $item, int $index) use ($formatDuration): array {
            $failed = (bool) ($item['failed'] ?? false);
            $command = strtoupper((string) ($item['command'] ?? 'COMMAND'));
            $connection = is_string($item['connection'] ?? null) && $item['connection'] !== ''
                ? $item['connection']
                : 'default';
            $duration = is_numeric($item['duration_ms'] ?? null) ? max(0, (float) $item['duration_ms']) : 0.0;
            $at = is_numeric($item['at_ms'] ?? null) ? max(0, (float) $item['at_ms']) : null;
            $afterResponse = is_numeric($item['after_response_ms'] ?? null)
                ? max(0, (float) $item['after_response_ms'])
                : null;
            $keys = array_values(array_filter(
                (array) ($item['keys'] ?? []),
                static fn (mixed $key): bool => is_scalar($key),
            ));
            $keys = array_map('strval', $keys);
            $hashes = array_values(array_filter(
                (array) ($item['key_hashes'] ?? []),
                static fn (mixed $hash): bool => is_string($hash) && $hash !== '',
            ));
            $keyCount = max(0, (int) ($item['key_count'] ?? count($keys)));
            $retainedCount = max(0, (int) ($item['key_retained'] ?? max(count($keys), count($hashes))));
            $droppedCount = max(0, (int) ($item['key_dropped'] ?? max(0, $keyCount - $retainedCount)));
            $rawCallsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;
            $callsiteLine = is_numeric($rawCallsite['line'] ?? null)
                && (int) $rawCallsite['line'] > 0
                    ? (int) $rawCallsite['line']
                    : null;
            $callsite = $rawCallsite !== null
                && is_string($rawCallsite['file'] ?? null)
                && $rawCallsite['file'] !== ''
                    ? [
                        'file' => $rawCallsite['file'],
                        'line' => $callsiteLine,
                    ]
                    : null;
            $keyLabel = match (true) {
                $keys !== [] && $keyCount > 1 => $keys[0].' and '.number_format($keyCount - 1).' more',
                $keys !== [] => $keys[0],
                $keyCount > 0 => number_format($keyCount).' protected '.Str::plural('key', $keyCount),
                default => 'No key metadata retained',
            };

            return [
                'execution' => $index + 1,
                'search' => mb_strtolower(implode(' ', array_filter([
                    $command, $connection, ...$keys, ...$hashes, $item['exception_class'] ?? null,
                ], static fn (mixed $value): bool => is_scalar($value)))),
                'command' => $command,
                'connection' => $connection,
                'duration_ms' => $duration,
                'duration_label' => $failed ? '—' : $formatDuration($duration),
                'failed' => $failed,
                'status_label' => $failed ? 'Failed' : 'Completed',
                'exception_class' => is_string($item['exception_class'] ?? null) ? $item['exception_class'] : null,
                'at_ms' => $at,
                'at_label' => $at === null ? '—' : $formatDuration($at),
                'lifecycle' => is_string($item['lifecycle'] ?? null) ? $item['lifecycle'] : 'request',
                'phase_label' => ($item['lifecycle'] ?? null) === 'after_response' ? 'After response' : 'During request',
                'after_response_ms' => $afterResponse,
                'after_response_label' => $afterResponse === null ? null : $formatDuration($afterResponse),
                'key_count' => $keyCount,
                'key_dropped' => $droppedCount,
                'key_label' => $keyLabel,
                'keys' => $keys,
                'key_hashes' => $hashes,
                'callsite' => $callsite,
                'source_label' => $callsite === null
                    ? null
                    : $callsite['file'].($callsite['line'] === null ? '' : ':'.$callsite['line']),
            ];
        })->all();
    }
}

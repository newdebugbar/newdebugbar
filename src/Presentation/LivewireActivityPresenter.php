<?php

namespace NewDebugBar\Presentation;

/** Normalizes captured Livewire activity and links each server render to its lifecycle operation. */
final class LivewireActivityPresenter
{
    /** @param array<int, array<string, mixed>> $items @param array<string, mixed> $summary @return array<int, array<string, mixed>> */
    public function present(array $items, array $summary): array
    {
        $profileId = ($summary['request_type'] ?? null) === 'livewire' ? ($summary['id'] ?? null) : null;
        $records = [];
        $owners = [];

        foreach (array_values($items) as $index => $item) {
            $componentId = (string) ($item['component_id'] ?? '');
            $kind = $item['type'] ?? 'activity';
            $records[] = [
                'id' => $item['id'] ?? 'server-livewire-'.($index + 1),
                'sequence' => $index + 1,
                'componentId' => $componentId,
                'componentName' => $item['component_name'] ?? '',
                'componentTitle' => $item['component_title'] ?? 'Livewire component',
                'title' => $item['name'] ?? 'Livewire activity',
                'kind' => $kind,
                'status' => $item['status'] ?? 'complete',
                'occurredAt' => null,
                'startedAt' => $item['at_ms'] ?? null,
                'requestAtMs' => $item['at_ms'] ?? null,
                'finishedAt' => null,
                'durationMs' => $item['duration_ms'] ?? null,
                'initialRenderDurationMs' => null,
                'profileIds' => array_values(array_unique([
                    ...(array) ($item['profile_ids'] ?? []),
                    ...($profileId === null ? [] : [$profileId]),
                ])),
                'actions' => empty($item['method']) ? [] : [[
                    'name' => $item['method'],
                    'params' => $item['params'] ?? [],
                    'metadata' => $item['metadata'] ?? [],
                ]],
                'changes' => empty($item['property']) ? [] : [[
                    'path' => $item['property'],
                    'before' => $item['before'] ?? null,
                    'submitted' => $item['submitted'] ?? null,
                    'server' => $item['server'] ?? null,
                ]],
                'events' => empty($item['event']) ? [] : [[
                    'name' => $item['event'],
                    'params' => $item['params'] ?? [],
                    'mode' => $item['mode'] ?? 'unknown',
                    'declaredTarget' => $item['declared_target'] ?? null,
                    'observedRecipientIds' => [],
                ]],
                'effects' => empty($item['effect']) ? [] : [$item['effect'] => true],
                'phases' => [],
                'error' => $item['message'] ?? null,
                'callsite' => $item['callsite'] ?? null,
                'serverRenderIds' => [],
                'serverRenderDurationMs' => null,
            ];

            if ($kind !== 'render') {
                $owners[$componentId] = $index;

                continue;
            }

            if (! isset($owners[$componentId])) {
                continue;
            }

            $owner = &$records[$owners[$componentId]];
            $owner['serverRenderIds'][] = $records[$index]['id'];
            $duration = $item['duration_ms'] ?? null;

            if (is_numeric($duration)) {
                $owner['serverRenderDurationMs'] = ($owner['serverRenderDurationMs'] ?? 0) + (float) $duration;

                if ($owner['kind'] === 'mount' && $owner['initialRenderDurationMs'] === null) {
                    $owner['initialRenderDurationMs'] = (float) $duration;
                }
            }

            unset($owner);
        }

        return $records;
    }
}

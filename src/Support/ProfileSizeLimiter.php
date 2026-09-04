<?php

namespace NewDebugBar\Support;

use RuntimeException;

/**
 * Keeps stored profiles within one fixed JSON byte budget without damaging downloads.
 * It drops bulky mail values, then record tails and payloads, while preserving capture totals.
 * Retained counts describe the records still stored; capture drops and storage omissions stay separate.
 */
final class ProfileSizeLimiter
{
    public const MAX_BYTES = 10_000_000;

    private const MAX_OMISSION_PATHS = 1_000;

    /** @param array<string, mixed> $profile */
    public function encode(array $profile): string
    {
        $json = $this->json($profile);

        if (strlen($json) <= self::MAX_BYTES) {
            return $json;
        }

        $profile['storage'] = [
            'truncated' => true,
            'max_bytes' => self::MAX_BYTES,
            'original_bytes' => strlen($json),
            'omitted_values' => [],
            'omitted_value_count' => 0,
            'omitted_items' => [],
            'omitted_item_count' => 0,
            'omitted_sections' => [],
            'omitted_section_count' => 0,
            'omitted_paths_truncated' => 0,
        ];

        foreach (['eml', 'attachments', 'bodies'] as $kind) {
            $candidates = $this->mailValues($profile, $kind);

            if ($candidates === []) {
                continue;
            }

            [$profile, $json] = $this->fit($profile, count($candidates), function (array $candidate, int $remove) use ($candidates): array {
                foreach (array_slice($candidates, 0, $remove) as $value) {
                    $this->omitMailValue($candidate, $value);
                }

                return $candidate;
            });

            if (strlen($json) <= self::MAX_BYTES) {
                return $json;
            }
        }

        foreach ($this->lists($profile) as $list) {
            [$profile, $json] = $this->fit($profile, $list['count'], function (array $candidate, int $remove) use ($list): array {
                $keep = $list['count'] - $remove;

                foreach ($list['keys'] as $key) {
                    $candidate['sections'][$list['section']]['payload'][$key] = array_slice(
                        $candidate['sections'][$list['section']]['payload'][$key], 0, $keep,
                    );
                }

                $this->record($candidate, 'items', $this->pointer(['sections', $list['section'], 'payload', $list['keys'][0]]), $remove);
                $this->markSection($candidate, $list['section'], $remove);

                return $candidate;
            });

            if (strlen($json) <= self::MAX_BYTES) {
                return $json;
            }
        }

        $sections = [];

        foreach ($profile['sections'] ?? [] as $key => $section) {
            if (is_array($section) && ($section['payload'] ?? []) !== []) {
                $sections[] = ['key' => $key, 'bytes' => strlen($this->json($section['payload']))];
            }
        }

        usort($sections, fn (array $left, array $right): int => $right['bytes'] <=> $left['bytes']);

        foreach ($sections as $section) {
            $key = $section['key'];
            $profile['sections'][$key]['payload'] = [];
            $this->record($profile, 'sections', (string) $key);
            $this->markSection($profile, (string) $key);
            $json = $this->json($profile);

            if (strlen($json) <= self::MAX_BYTES) {
                return $json;
            }
        }

        throw new RuntimeException('The debug profile metadata exceeds the storage byte limit.');
    }

    /**
     * Finds the smallest batch that fits with logarithmic whole-profile encoding work.
     *
     * @param  array<string, mixed>  $profile
     * @return array{array<string, mixed>, string}
     */
    private function fit(array $profile, int $count, callable $remove): array
    {
        $best = $remove($profile, $count);
        $json = $this->json($best);

        if (strlen($json) > self::MAX_BYTES) {
            return [$best, $json];
        }

        $low = 1;
        $high = $count - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $candidate = $remove($profile, $middle);
            $encoded = $this->json($candidate);

            if (strlen($encoded) <= self::MAX_BYTES) {
                $best = $candidate;
                $json = $encoded;
                $high = $middle - 1;
            } else {
                $low = $middle + 1;
            }
        }

        return [$best, $json];
    }

    /** @param array<string, mixed> $profile @return list<array<string, mixed>> */
    private function mailValues(array $profile, string $kind): array
    {
        $values = [];

        foreach ($profile['sections']['mail']['payload']['items'] ?? [] as $index => $item) {
            $preview = is_array($item) ? ($item['preview'] ?? []) : [];

            if (! is_array($preview)) {
                continue;
            }

            if ($kind === 'attachments') {
                foreach ($preview['attachments'] ?? [] as $attachment => $details) {
                    if (is_array($details) && is_string($details['body_base64'] ?? null)) {
                        $values[] = ['item' => $index, 'attachment' => $attachment, 'field' => 'body_base64', 'bytes' => strlen($this->json($details['body_base64']))];
                    }
                }

                continue;
            }

            foreach ($kind === 'eml' ? ['eml'] : ['html', 'text'] as $field) {
                if (is_string($preview[$field] ?? null)) {
                    $values[] = ['item' => $index, 'field' => $field, 'bytes' => strlen($this->json($preview[$field]))];
                }
            }
        }

        usort($values, fn (array $left, array $right): int => $right['bytes'] <=> $left['bytes']);

        return $values;
    }

    /** @param array<string, mixed> $profile @param array<string, mixed> $value */
    private function omitMailValue(array &$profile, array $value): void
    {
        $preview = &$profile['sections']['mail']['payload']['items'][$value['item']]['preview'];
        $path = ['sections', 'mail', 'payload', 'items', $value['item'], 'preview'];

        if (isset($value['attachment'])) {
            $preview['attachments'][$value['attachment']]['body_base64'] = null;
            $preview['attachments'][$value['attachment']]['body_omitted_reason'] = 'profile_budget';
            $preview['attachments_omitted'] = (int) ($preview['attachments_omitted'] ?? 0) + 1;
            array_push($path, 'attachments', $value['attachment'], 'body_base64');
        } else {
            $preview[$value['field']] = null;
            $preview[$value['field'].'_omitted_reason'] = 'profile_budget';
            $path[] = $value['field'];
        }

        $preview['truncated'] = true;
        $this->record($profile, 'values', $this->pointer($path));
        $this->markSection($profile, 'mail');
    }

    /** @param array<string, mixed> $profile @return list<array<string, mixed>> */
    private function lists(array $profile): array
    {
        $lists = [];

        foreach ($profile['sections'] ?? [] as $section => $details) {
            $payload = is_array($details) ? ($details['payload'] ?? []) : [];

            if (! is_array($payload)) {
                continue;
            }

            foreach ($payload as $key => $items) {
                if (! is_array($items) || ! array_is_list($items) || $items === [] || ($section === 'livewire' && $key === 'items' && is_array($payload['activity'] ?? null))) {
                    continue;
                }

                $keys = [$key];
                $count = count($items);
                $bytes = strlen($this->json($items));

                if ($section === 'livewire' && $key === 'activity' && is_array($payload['items'] ?? null)) {
                    $keys[] = 'items';
                    $count = max($count, count($payload['items']));
                    $bytes += strlen($this->json($payload['items']));
                }

                $lists[] = ['section' => (string) $section, 'keys' => $keys, 'count' => $count, 'bytes' => $bytes];
            }
        }

        usort($lists, fn (array $left, array $right): int => $right['bytes'] <=> $left['bytes']);

        return $lists;
    }

    /** @param array<string, mixed> $profile */
    private function record(array &$profile, string $kind, string $path, int $count = 1): void
    {
        $storage = &$profile['storage'];
        $singular = ['values' => 'value', 'items' => 'item', 'sections' => 'section'][$kind];
        $storage['omitted_'.$singular.'_count'] += $count;
        $recorded = count($storage['omitted_values']) + count($storage['omitted_items']) + count($storage['omitted_sections']);

        if ($recorded >= self::MAX_OMISSION_PATHS) {
            $storage['omitted_paths_truncated']++;

            return;
        }

        if ($kind === 'items') {
            $storage['omitted_items'][$path] = $count;
        } else {
            $storage['omitted_'.$kind][] = $path;
        }
    }

    /** @param array<string, mixed> $profile */
    private function markSection(array &$profile, string $section, int $items = 0): void
    {
        $summary = &$profile['sections'][$section]['summary'];

        if ($summary === null) {
            $summary = [];
        }

        if (is_array($summary)) {
            $summary['storage_truncated'] = true;
            $summary['storage_omitted_items'] = (int) ($summary['storage_omitted_items'] ?? 0) + $items;
            $payload = $profile['sections'][$section]['payload'] ?? [];

            if (array_key_exists('retained_count', $summary) && $summary['retained_count'] !== '[redacted]') {
                $summary['retained_count'] = $section === 'livewire'
                    ? count($payload['components'] ?? []) + count($payload['activity'] ?? $payload['items'] ?? [])
                    : count($payload['items'] ?? []);
            }

            if ($section === 'queries' && array_key_exists('transaction_retained_count', $summary) && $summary['transaction_retained_count'] !== '[redacted]') {
                $summary['transaction_retained_count'] = count($payload['transactions'] ?? []);
            }
        }
    }

    /** @param list<string|int> $path */
    private function pointer(array $path): string
    {
        return '/'.implode('/', array_map(fn (string|int $part): string => str_replace(['~', '/'], ['~0', '~1'], (string) $part), $path));
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}

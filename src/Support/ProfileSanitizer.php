<?php

namespace NewDebugBar\Support;

/**
 * Applies the same redaction rules to every profile before storage.
 * Keeps diagnostic envelopes intact and removes alternate copies of masked evidence.
 */
final class ProfileSanitizer
{
    public function __construct(private readonly Redactor $redactor) {}

    public function clean(array $profile): array
    {
        $sections = $profile['sections'] ?? null;
        $identity = array_intersect_key($profile, ['id' => true, 'schema_version' => true]);
        unset($profile['sections']);
        $profile = [...$this->redactor->redact($profile), ...$identity];

        if (! is_array($sections)) {
            return $profile;
        }

        foreach ($sections as $key => &$section) {
            if (! is_array($section)) {
                continue;
            }

            // Section names such as "authorization" describe diagnostics, not credentials.
            $original = $section['payload'] ?? [];
            $payload = $original;
            $session = $key === 'request' && is_array($payload) ? ($payload['session'] ?? null) : null;
            if (is_array($session)) {
                unset($payload['session']);
            }

            $section = $this->redactor->redact(array_diff_key($section, ['payload' => true]), 'sections.'.$key);
            $payload = $this->data($payload, (string) $key);

            if (is_array($payload)) {
                if (is_array($session)) {
                    // RequestContext retains only session shape, never session values.
                    $payload['session'] = $this->redactor->matchesConfiguredPath('request.session')
                        || $this->redactor->matchesConfiguredPath('sections.request.payload.session')
                        ? '[redacted]'
                        : $this->data($session, 'request.session');
                }

                if ($key === 'request') {
                    $payload = $this->request($payload, is_array($original) ? $original : []);
                }

                if (is_array($payload['items'] ?? null)) {
                    foreach ($payload['items'] as $index => &$item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $before = $original['items'][$index] ?? [];

                        if ($key === 'queries' && (($before['bindings'] ?? null) !== ($item['bindings'] ?? null)
                            || ($before['sql'] ?? null) !== ($item['sql'] ?? null)
                            || $this->containsRedaction($item['bindings'] ?? [])
                            || ($item['sql'] ?? null) === '[redacted]')) {
                            unset($item['runnable_sql']);
                            $item['runnable_available'] = false;
                            $item['bindings_complete'] = false;
                            if (($before['sql'] ?? null) !== ($item['sql'] ?? null)) {
                                $item['source_preserved'] = false;
                            }
                        }

                        if ($key === 'http_client' && is_string($item['url'] ?? null)) {
                            $item['url'] = (new SafeUrl($this->redactor))->clean($item['url'], 'sections.http_client.payload.items.'.$index);
                            $item['url'] = (new SafeUrl($this->redactor))->clean($item['url'], 'http_client.items.'.$index);
                        }

                        if ($key === 'mail' && is_array($item['preview'] ?? null)) {
                            $item = $this->mail($item, is_array($before) ? $before : []);
                        }

                        if (in_array($key, ['authorization', 'models'], true)) {
                            $item = $this->namedValues($item, $key.'.items.'.$index);
                        }
                    }
                    unset($item);
                }

                if ($key === 'livewire') {
                    $payload = $this->livewire($payload, is_array($original) ? $original : []);
                }

                foreach (['items', 'activity', 'components', 'transactions'] as $list) {
                    if (($payload[$list] ?? null) === '[redacted]') {
                        $payload[$list] = [];
                        $payload[$list.'_redacted'] = true;
                    } elseif (is_array($payload[$list] ?? null)) {
                        foreach ($payload[$list] as $index => $record) {
                            if ($record === '[redacted]') {
                                $payload[$list][$index] = ['redacted' => true];
                            }
                        }
                    }
                }
            }

            $section['payload'] = $payload;
        }
        unset($section);

        $profile['sections'] = $sections;

        return $profile;
    }

    private function data(mixed $value, string $path): mixed
    {
        // Only the first dot separates the section from the payload path.
        $parts = explode('.', $path, 2);
        $physical = 'sections.'.$parts[0].'.payload'.(isset($parts[1]) ? '.'.$parts[1] : '');

        return $this->redactor->redact($this->redactor->redact($value, $physical), $path);
    }

    private function request(array $payload, array $original): array
    {
        if (array_key_exists('query', $payload) && array_key_exists('input', $payload)) {
            $this->synchronizeCopies($payload['query'], $payload['input'], $original['query'] ?? [], $original['input'] ?? []);
        }
        if (($payload['url'] ?? null) === '[redacted]') {
            $payload['path'] = '[redacted]';

            return $payload;
        }
        if (! is_string($payload['url'] ?? null)) {
            return $payload;
        }

        $url = (new SafeUrl($this->redactor))->clean($payload['url'], 'sections.request.payload');
        $url = (new SafeUrl($this->redactor))->clean($url, 'request');
        $parts = explode('?', $url, 2);
        if (array_key_exists('query', $payload)) {
            $url = $parts[0];
            if (is_array($payload['query']) && $payload['query'] !== []) {
                $url .= '?'.http_build_query($payload['query'], '', '&', PHP_QUERY_RFC3986);
            }
        }

        if ($this->containsRedaction($payload['parameters'] ?? []) || ($payload['path'] ?? null) === '[redacted]') {
            // The raw parameter may already be masked. Hide its whole path copy.
            $payload['path'] = '[redacted]';
            $url = preg_replace('~^(https?://[^/]+)(?:/[^?]*)?~', '$1/[redacted]', $url) ?? '[redacted]';
        }
        $payload['url'] = $url;

        return $payload;
    }

    private function synchronizeCopies(mixed &$left, mixed &$right, mixed $originalLeft, mixed $originalRight): void
    {
        if ($originalLeft === $originalRight && ($left === '[redacted]' || $right === '[redacted]')) {
            $left = $right = '[redacted]';
        } elseif ($left === '[redacted]' && is_array($right) && is_array($originalLeft) && is_array($originalRight)) {
            foreach (array_intersect_key($right, $originalLeft, $originalRight) as $key => $value) {
                $mask = '[redacted]';
                $this->synchronizeCopies($mask, $right[$key], $originalLeft[$key], $originalRight[$key]);
            }
        } elseif ($right === '[redacted]' && is_array($left) && is_array($originalLeft) && is_array($originalRight)) {
            $this->synchronizeCopies($right, $left, $originalRight, $originalLeft);
        } elseif (is_array($left) && is_array($right) && is_array($originalLeft) && is_array($originalRight)) {
            foreach (array_intersect_key($left, $right, $originalLeft, $originalRight) as $key => $value) {
                $this->synchronizeCopies($left[$key], $right[$key], $originalLeft[$key], $originalRight[$key]);
            }
        }
    }

    private function mail(array $item, array $before): array
    {
        $preview = $item['preview'];
        $old = is_array($before['preview'] ?? null) ? $before['preview'] : [];
        if (($item['subject'] ?? null) !== ($before['subject'] ?? null)) {
            $preview['subject'] = $item['subject'] ?? null;
        } elseif (($preview['subject'] ?? null) !== ($old['subject'] ?? null) && array_key_exists('subject', $item)) {
            $item['subject'] = $preview['subject'] ?? null;
        }

        foreach (is_array($preview['attachments'] ?? null) ? $preview['attachments'] : [] as $index => $attachment) {
            if (is_array($attachment) && ($attachment['body_base64'] ?? null) === '[redacted]') {
                $preview['attachments'][$index]['body_base64'] = null;
                $hadBody = is_string($old['attachments'][$index]['body_base64'] ?? null);
                $preview['attachments'][$index]['body_omitted_reason'] = $hadBody
                    ? 'redacted'
                    : ($old['attachments'][$index]['body_omitted_reason'] ?? 'redacted');
                if ($hadBody) {
                    $preview['attachments_omitted'] = (int) ($preview['attachments_omitted'] ?? 0) + 1;
                }
            }
        }

        if ($preview !== $old && is_string($preview['eml'] ?? null)) {
            $preview['eml'] = null;
            $preview['eml_omitted_reason'] = is_string($old['eml'] ?? null)
                ? 'redacted_fields'
                : ($old['eml_omitted_reason'] ?? 'redacted_fields');
        }
        $item['preview'] = $preview;

        return $item;
    }

    private function livewire(array $payload, array $original): array
    {
        $activity = ($payload['items'] ?? null) === '[redacted]' || ($payload['activity'] ?? null) === '[redacted]'
            ? []
            : ($original['activity'] ?? $original['items'] ?? []);
        if (($payload['items'] ?? null) === '[redacted]' || ($payload['activity'] ?? null) === '[redacted]') {
            $payload['activity_redacted'] = true;
        }
        if (is_array($activity)) {
            $activity = $this->data($this->data($activity, 'livewire.items'), 'livewire.activity');
            foreach ($activity as &$item) {
                if (! is_array($item) || ! is_string($item['property'] ?? null)) {
                    continue;
                }
                foreach (['before', 'submitted', 'server'] as $field) {
                    if (array_key_exists($field, $item)) {
                        $item[$field] = $this->propertyValue($item[$field], $item['property']);
                    }
                }
            }
            unset($item);
            foreach (['items', 'activity'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $payload[$field] = $activity;
                }
            }
        }

        if (is_array($payload['components'] ?? null)) {
            foreach ($payload['components'] as &$component) {
                if (! is_array($component) || ! is_array($component['properties'] ?? null)) {
                    continue;
                }
                foreach ($component['properties'] as &$property) {
                    if (! is_array($property) || ! is_string($property['path'] ?? null)) {
                        continue;
                    }
                    $value = $this->propertyValue($property['server_value'] ?? null, $property['path']);
                    $property['server_value'] = $value;
                    if ($this->containsRedaction($value)) {
                        $property['writable'] = $property['array_leaf_writable'] = $property['write_allowed'] = false;
                        $property['write_reason'] = 'redacted';
                    }
                }
                unset($property);
            }
            unset($component);
        }

        return $payload;
    }

    private function propertyValue(mixed $value, string $name): mixed
    {
        if ($name === '[redacted]') {
            return '[redacted]';
        }
        $path = 'livewire.properties';
        foreach (explode('.', $name) as $segment) {
            $path .= '.'.$segment;
            if ($this->redactor->isSensitivePath($path)
                || $this->redactor->matchesConfiguredPath('sections.livewire.payload.'.substr($path, strlen('livewire.')))) {
                return '[redacted]';
            }
        }

        return $this->data($value, 'livewire.properties.'.$name);
    }

    private function namedValues(array $value, string $path): array
    {
        foreach (['identifier_name' => 'identifier', 'route_key_name' => 'route_key', 'key_name' => 'key'] as $name => $field) {
            if (is_string($value[$name] ?? null) && ($value[$name] === '[redacted]' || $this->redactor->isSensitivePath($path.'.'.$value[$name]))) {
                if ($field === 'route_key' && ($value['identifier'] ?? null) === ($value[$field] ?? null)) {
                    $value['identifier'] = '[redacted]';
                }
                $value[$field] = '[redacted]';
            }
        }
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = $this->namedValues($child, $path.'.'.$key);
            }
        }

        return $value;
    }

    private function containsRedaction(mixed $value): bool
    {
        if (! is_array($value)) {
            return $value === '[redacted]';
        }
        foreach ($value as $item) {
            if ($this->containsRedaction($item)) {
                return true;
            }
        }

        return false;
    }
}

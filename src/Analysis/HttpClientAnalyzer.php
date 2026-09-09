<?php

namespace NewDebugBar\Analysis;

use NewDebugBar\Support\DurationFormatter;

/** Turns captured outbound HTTP requests into actionable, copyable evidence. */
final class HttpClientAnalyzer
{
    public function __construct(private readonly float $slowRequestMs = 250) {}

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return array{summary: array<string, int|float>, items: list<array<string, mixed>>}
     */
    public function analyze(array $requests): array
    {
        $items = [];

        foreach (array_values($requests) as $index => $request) {
            $url = (string) ($request['url'] ?? '');
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '—');
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
            $query = parse_url($url, PHP_URL_QUERY);
            $duration = is_numeric($request['duration_ms'] ?? null)
                ? round(max(0, (float) $request['duration_ms']), 2)
                : null;
            $status = is_numeric($request['status'] ?? null) ? (int) $request['status'] : null;
            $failed = (bool) ($request['failed'] ?? false) || ($status !== null && $status >= 400);
            $slow = $duration !== null && $duration >= $this->slowRequestMs;
            $reason = trim((string) ($request['reason'] ?? ''));
            $method = strtoupper(trim((string) ($request['method'] ?? '')));
            $method = $method === '' ? '—' : $method;
            $durationLabel = DurationFormatter::format($duration);
            $redirect = $status !== null && $status >= 300 && $status < 400;
            $redirectLocation = $this->headerValue($request['response']['headers'] ?? null, 'location');

            $items[] = [
                ...$request,
                'execution' => $index + 1,
                'method' => $method,
                'host' => $host,
                'path' => $path,
                'query' => is_string($query) && $query !== '' ? $query : null,
                'duration_ms' => $duration,
                'status' => $status,
                'failed' => $failed,
                'slow' => $slow,
                'redirect' => $redirect,
                'attention' => $failed || $slow,
                'status_label' => $this->statusLabel($status, $reason, $failed),
                'list_status_label' => $status === null ? ($failed ? 'Failed' : '—') : (string) $status,
                'duration_label' => $durationLabel,
                'timing_summary' => $this->timingSummary($durationLabel, $slow),
                'request_body_size_label' => $this->byteLabel($request['request']['body_size_bytes'] ?? null),
                'response_body_size_label' => $this->byteLabel($request['response']['body_size_bytes'] ?? null),
                'redirect_location' => $redirectLocation,
                'response_summary' => $this->responseSummary(
                    $request['response'] ?? null,
                    $request['exception_message'] ?? null,
                    $redirectLocation,
                ),
                'meaning' => $this->meaning($status, $failed, $slow, $redirect),
                'what_happened' => $this->whatHappened($host, $status, $reason, $failed, $slow, $duration),
                'why_it_matters' => $this->whyItMatters($status, $failed, $slow, $redirect),
                'check_next' => $this->checkNext($status, $failed, $slow, $redirect),
                'curl' => $this->curl($method, $url, $request['request'] ?? null),
                'search' => mb_strtolower(implode(' ', array_filter([
                    $method,
                    $url,
                    $host,
                    $path,
                    $status === null ? null : (string) $status,
                    $reason,
                    $request['exception_class'] ?? null,
                    $request['exception_message'] ?? null,
                    $request['callsite']['file'] ?? null,
                    $redirectLocation,
                ], fn (mixed $value): bool => is_scalar($value) && (string) $value !== ''))),
            ];
        }

        return [
            'summary' => [
                'retained_count' => count($items),
                'failed_count' => count(array_filter($items, fn (array $item): bool => $item['failed'])),
                'slow_count' => count(array_filter($items, fn (array $item): bool => $item['slow'])),
                'attention_count' => count(array_filter($items, fn (array $item): bool => $item['attention'])),
                'slow_threshold_ms' => $this->slowRequestMs,
            ],
            'items' => $items,
        ];
    }

    private function statusLabel(?int $status, string $reason, bool $failed): string
    {
        if ($status !== null) {
            return trim($status.' '.$reason);
        }

        return $failed ? 'Connection failed' : 'No response';
    }

    private function timingSummary(string $durationLabel, bool $slow): string
    {
        if (! $slow) {
            return $durationLabel;
        }

        return sprintf(
            '%s, above the %s threshold',
            $durationLabel,
            DurationFormatter::format($this->slowRequestMs),
        );
    }

    private function responseSummary(mixed $response, mixed $exceptionMessage, ?string $redirectLocation): string
    {
        if (is_scalar($exceptionMessage) && trim((string) $exceptionMessage) !== '') {
            return trim((string) $exceptionMessage);
        }

        if (! is_array($response)) {
            return 'No response was captured.';
        }

        if ($redirectLocation !== null) {
            return 'Redirected to '.$redirectLocation.'.';
        }

        $body = $response['body'] ?? null;

        if (is_array($body)) {
            foreach (['message', 'error', 'detail', 'reason'] as $key) {
                if (is_scalar($body[$key] ?? null) && trim((string) $body[$key]) !== '') {
                    return trim((string) $body[$key]);
                }
            }

            return $body === [] ? 'The response body was empty.' : 'A response body was captured.';
        }

        if (is_scalar($body) && trim((string) $body) !== '') {
            return trim((string) $body);
        }

        return 'No response body was returned.';
    }

    private function whatHappened(
        string $host,
        ?int $status,
        string $reason,
        bool $failed,
        bool $slow,
        ?float $duration,
    ): string {
        if ($status !== null) {
            $statusLabel = trim($status.' '.$reason);

            if ($slow && $duration !== null) {
                return sprintf('%s returned HTTP %s in %s.', $host, $statusLabel, DurationFormatter::format($duration));
            }

            return sprintf('%s returned HTTP %s.', $host, $statusLabel);
        }

        if ($failed) {
            return sprintf('The request to %s failed before a response arrived.', $host);
        }

        if ($slow) {
            return sprintf('%s responded in %s.', $host, DurationFormatter::format($duration));
        }

        return sprintf('The request to %s completed.', $host);
    }

    private function meaning(?int $status, bool $failed, bool $slow, bool $redirect): string
    {
        if ($status !== null && $status >= 500) {
            return 'The upstream service could not complete this request.';
        }

        if ($status !== null && $status >= 400) {
            return 'The upstream service rejected this request.';
        }

        if ($failed) {
            return 'No response reached the application.';
        }

        if ($slow) {
            return 'The upstream service responded more slowly than expected.';
        }

        if ($redirect) {
            return 'The upstream service redirected the request.';
        }

        return 'The upstream service completed this request.';
    }

    private function whyItMatters(?int $status, bool $failed, bool $slow, bool $redirect): string
    {
        if ($status !== null && $status >= 500) {
            return 'The upstream service could not complete the request, so the caller must handle an unavailable dependency.';
        }

        if ($status !== null && $status >= 400) {
            return 'The upstream service rejected the request, so retrying it unchanged is unlikely to help.';
        }

        if ($failed) {
            return 'No HTTP response reached the application, so the dependent work may be incomplete.';
        }

        if ($slow) {
            return sprintf('It exceeded the %s threshold and added avoidable time to this request.', DurationFormatter::format($this->slowRequestMs));
        }

        if ($redirect) {
            return 'The caller received a redirect instead of the requested representation.';
        }

        return 'The upstream service completed the request normally.';
    }

    private function checkNext(?int $status, bool $failed, bool $slow, bool $redirect): string
    {
        if ($status === 401 || $status === 403) {
            return 'Check the credentials, scopes, and access rules used for this request.';
        }

        if ($status === 404) {
            return 'Check the endpoint path, HTTP method, and remote resource identifier.';
        }

        if ($status === 422) {
            return 'Inspect the response body and compare the submitted payload with the remote validation rules.';
        }

        if ($status === 429) {
            return 'Inspect rate-limit headers, retry timing, and backoff behavior.';
        }

        if ($status !== null && $status >= 500) {
            return 'Confirm endpoint health, timeout, and retry behavior.';
        }

        if ($status !== null && $status >= 400) {
            return 'Inspect the response body, then confirm the request method, URL, headers, and payload.';
        }

        if ($failed) {
            return 'Check DNS, network access, the endpoint, and timeout settings.';
        }

        if ($slow) {
            return 'Inspect the response size, endpoint work, timeout, and whether this call can leave the request path.';
        }

        if ($redirect) {
            return 'If the redirect was unexpected, inspect the Location header and the client redirect settings.';
        }

        return 'No follow-up is needed.';
    }

    private function byteLabel(mixed $bytes): string
    {
        if (! is_numeric($bytes)) {
            return '—';
        }

        $bytes = max(0, (int) $bytes);

        return match (true) {
            $bytes >= 1024 * 1024 => $this->number($bytes / (1024 * 1024)).' MB',
            $bytes >= 1024 => $this->number($bytes / 1024).' KB',
            default => number_format($bytes).' B',
        };
    }

    private function headerValue(mixed $headers, string $name): ?string
    {
        if (! is_array($headers)) {
            return null;
        }

        foreach ($headers as $header => $values) {
            if (strtolower((string) $header) !== strtolower($name)) {
                continue;
            }

            foreach ((array) $values as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return null;
    }

    private function curl(string $method, string $url, mixed $request): string
    {
        $evidence = is_array($request) ? $request : [];
        $parts = [
            'curl',
            '--request '.$this->shellArgument($method),
            $this->shellArgument($url),
        ];

        foreach ((array) ($evidence['headers'] ?? []) as $name => $values) {
            if (in_array(strtolower((string) $name), ['content-length', 'host'], true)) {
                continue;
            }

            foreach ((array) $values as $value) {
                $parts[] = '--header '.$this->shellArgument($name.': '.$value);
            }
        }

        $body = $evidence['body'] ?? null;

        if ($body !== null && $body !== '') {
            $encoded = is_string($body)
                ? $body
                : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($encoded)) {
                $parts[] = '--data-raw '.$this->shellArgument($encoded);
            }
        }

        return implode(" \\\n  ", $parts);
    }

    private function shellArgument(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

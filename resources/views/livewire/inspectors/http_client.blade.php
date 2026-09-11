{{-- Normalizes HTTP Client data, then delegates the workspace to focused view components. --}}
@php
    $hasEvidence = static function (mixed $value): bool {
        if ($value === null || $value === '') {
            return false;
        }

        return ! is_array($value) || $value !== [];
    };
    $httpItems = collect($inspector['payload']['items'] ?? [])
        ->map(function (array $item) use ($hasEvidence): array {
            $callsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;
            $callsiteFile = trim((string) ($callsite['file'] ?? ''));
            $callsiteLine = is_numeric($callsite['line'] ?? null) ? (int) $callsite['line'] : null;
            $request = is_array($item['request'] ?? null) ? $item['request'] : [];
            $response = is_array($item['response'] ?? null) ? $item['response'] : [];
            $item['stack'] = array_values(is_array($item['stack'] ?? null) ? $item['stack'] : []);

            $item['callsite_label'] = $callsiteFile === ''
                ? null
                : $callsiteFile.($callsiteLine === null ? '' : ':'.$callsiteLine);
            $item['has_source'] = $item['callsite_label'] !== null || $item['stack'] !== [];
            $item['request_has_headers'] = $hasEvidence($request['headers'] ?? null);
            $item['request_has_body'] = $hasEvidence($request['body'] ?? null);
            $item['response_has_headers'] = $hasEvidence($response['headers'] ?? null);
            $item['response_has_body'] = $hasEvidence($response['body'] ?? null);

            return $item;
        })
        ->values()
        ->all();
    $httpSummary = $inspector['summary'] ?? [];
@endphp

<div data-ndb-http-client x-init="refresh()" class="ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col">
    <script type="application/json" data-ndb-http-client-payload>
        {{ base64_encode(json_encode($httpItems, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)) }}
    </script>

    @if ($httpItems !== [])
        <x-newdebugbar::http-client-workspace :items="$httpItems" :summary="$httpSummary" />
    @else
        <x-newdebugbar::empty-state
            centered
            data-ndb-http-client-empty
            label="No outbound HTTP requests were captured for this request."
            description="Requests made through Laravel's HTTP client will appear here with their response, timing, and source."
        />
    @endif
</div>

{{-- Normalizes Cache data, then delegates the workspace to focused view components. --}}
@php
    $cacheItems = array_values($inspector['payload']['items'] ?? []);
    $cacheSummary = $inspector['summary'] ?? [];
@endphp

<div data-ndb-cache x-init="refresh()" class="ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col">
    <script type="application/json" data-ndb-cache-payload>
        {{ base64_encode(json_encode($cacheItems, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)) }}
    </script>

    @if ($cacheItems !== [])
        <x-newdebugbar::cache-workspace :items="$cacheItems" :summary="$cacheSummary" />
    @else
        <x-newdebugbar::empty-state
            centered
            data-ndb-cache-empty
            label="No cache operations were captured for this request."
            description="Reads, writes, deletes, and store flushes will appear here when Laravel emits them."
        />
    @endif
</div>

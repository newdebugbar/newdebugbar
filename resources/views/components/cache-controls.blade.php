@props([
    'summary',
    'itemCount',
])

@php
    $count = (int) ($summary['retained_count'] ?? $itemCount);
    $reads = (int) ($summary['reads'] ?? 0);
    $hits = (int) ($summary['hits'] ?? 0);
    $misses = (int) ($summary['misses'] ?? 0);
    $writes = (int) ($summary['writes'] ?? 0);
    $deletes = (int) ($summary['forgets'] ?? 0) + (int) ($summary['flushes'] ?? 0);
    $failures = (int) ($summary['failures'] ?? 0);
    $flushes = (int) ($summary['flushes'] ?? 0);
    $repeatedMisses = (int) ($summary['repeated_miss_count'] ?? 0);
    $hitRate = (float) ($summary['hit_rate'] ?? 0);
    $duration = (float) ($summary['duration_ms'] ?? 0);
    $filters = array_filter([
        'all' => ['All', $count],
        'reads' => ['Reads', $reads],
        'writes' => ['Writes', (int) ($summary['filter_counts']['writes'] ?? $writes)],
        'deletes' => ['Deletes', (int) ($summary['filter_counts']['deletes'] ?? $deletes)],
        'failed' => ['Failed', (int) ($summary['filter_counts']['failed'] ?? $failures)],
    ], fn (array $filter, string $key): bool => $key === 'all' || $filter[1] > 0, ARRAY_FILTER_USE_BOTH);
    $needsAttention = $failures > 0 || $flushes > 0 || $repeatedMisses > 0;
    $attentionParts = [];

    if ($failures > 0) {
        $attentionParts[] = number_format($failures).' failed '.\Illuminate\Support\Str::plural('operation', $failures);
    }

    if ($repeatedMisses > 0) {
        $attentionParts[] = number_format($repeatedMisses).' repeatedly missed '.\Illuminate\Support\Str::plural('key', $repeatedMisses);
    }

    if ($flushes > 0) {
        $attentionParts[] = number_format($flushes).' store '.\Illuminate\Support\Str::plural('flush', $flushes);
    }
@endphp

<div data-ndb-cache-summary class="ndb:flex ndb:items-start ndb:justify-between ndb:gap-3 ndb:sm:gap-4">
    <div class="ndb:min-w-0">
        <p class="ndb:text-xs ndb:font-bold ndb:text-zinc-700 ndb:dark:text-zinc-200">
            {{ number_format($count) }} {{ \Illuminate\Support\Str::plural('operation', $count) }}
            <span
                x-show.important="visibleCacheCount !== cacheOperations.length"
                class="ndb:ml-1 ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
            >
                <span data-ndb-cache-visible-count x-text="visibleCacheCount"></span>
                shown
            </span>
        </p>
        <p class="ndb:mt-0.5 ndb:text-xs ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
            {{ \NewDebugBar\Support\DurationFormatter::format($duration) }} total
        </p>
    </div>
    @if ($reads > 0)
        <div class="ndb:shrink-0 ndb:text-right">
            <p @class([
                'ndb:text-xs ndb:font-bold ndb:tabular-nums',
                'ndb:text-amber-700 ndb:dark:text-amber-300' => (bool) ($summary['high_miss_rate'] ?? false),
                'ndb:text-zinc-700 ndb:dark:text-zinc-200' => ! (bool) ($summary['high_miss_rate'] ?? false),
            ])>
                {{ number_format($hitRate, 1) }}% hit rate
            </p>
            <p class="ndb:mt-0.5 ndb:text-xs ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
                {{ number_format($hits) }} {{ \Illuminate\Support\Str::plural('hit', $hits) }}, {{ number_format($misses) }} {{ \Illuminate\Support\Str::plural('miss', $misses) }}
            </p>
        </div>
    @endif
</div>

@if ($needsAttention)
    <p
        data-ndb-cache-attention
        role="status"
        @class([
            'ndb:flex ndb:items-start ndb:gap-2 ndb:text-xs ndb:font-medium ndb:leading-4',
            'ndb:text-red-700 ndb:dark:text-red-300' => $failures > 0,
            'ndb:text-amber-700 ndb:dark:text-amber-300' => $failures === 0,
        ])
    >
        <x-newdebugbar::icon name="warning" size="3.5" class="ndb:mt-px" />
        <span>
            <strong class="ndb:font-bold">Cache needs attention.</strong>
            {{ ucfirst(implode(', ', $attentionParts)) }}.
        </span>
    </p>
@endif

<x-newdebugbar::inspector-list-controls :show-search="$itemCount >= 5">
    <x-slot:search>
        <x-newdebugbar::search-field
            label="Search cache operations"
            placeholder="Search keys or stores"
            data-ndb-cache-search
            x-model="cacheSearch"
            @input.debounce.100ms="applyCacheView()"
        />
    </x-slot:search>

    <x-slot:filter>
        <x-newdebugbar::select-field
            label="Filter cache operations"
            data-ndb-cache-filter
            x-model="cacheFilter"
            @change="setCacheFilter($event.target.value)"
        >
            @foreach ($filters as $filter => [$label, $count])
                <option value="{{ $filter }}">{{ $label }} ({{ $count }})</option>
            @endforeach
        </x-newdebugbar::select-field>
    </x-slot:filter>
</x-newdebugbar::inspector-list-controls>

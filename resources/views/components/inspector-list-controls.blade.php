@props([
    'showSearch',
    'layout' => 'responsive',
])

@php
    if (! in_array($layout, ['responsive', 'compact'], true)) {
        throw new \InvalidArgumentException("Unknown inspector list controls layout [{$layout}].");
    }

    $hasLeading = isset($leading);
    $hasFilter = isset($filter);
    $hasSecondaryFilter = isset($secondaryFilter);
    $isCompact = $layout === 'compact';
@endphp

<div
    data-ndb-inspector-list-controls
    {{
        $attributes->class([
            'ndb:grid ndb:items-start ndb:gap-x-2 ndb:gap-y-3',
            'ndb:grid-cols-2' => $hasSecondaryFilter && $isCompact,
            'ndb:grid-cols-2 ndb:sm:grid-cols-[minmax(0,1fr)_minmax(8.75rem,0.35fr)_minmax(8.75rem,0.35fr)]' => $hasSecondaryFilter && ! $isCompact,
            'ndb:grid-cols-[minmax(0,1fr)_8.75rem]' => $hasFilter && ! $hasSecondaryFilter,
            'ndb:grid-cols-1' => ! $hasFilter && ! $hasSecondaryFilter,
        ])
    }}
>
    @isset($leading)
        <div @class([
            'ndb:min-w-0 ndb:self-center',
            'ndb:col-span-full' => $showSearch,
        ])>
            {{ $leading }}
        </div>
    @endisset

    @if ($showSearch && isset($search))
        <div @class([
            'ndb:min-w-0',
            'ndb:col-span-full' => ! $hasFilter && ! $hasSecondaryFilter,
            'ndb:col-span-2' => $hasSecondaryFilter,
            'ndb:sm:col-span-1' => $hasSecondaryFilter && ! $isCompact,
        ])>
            {{ $search }}
        </div>
    @endif

    @isset($filter)
        <div @class(['ndb:min-w-0', 'ndb:col-span-2' => ! $showSearch && ! $hasLeading])>{{ $filter }}</div>
    @endisset

    @isset($secondaryFilter)
        <div class="ndb:min-w-0">{{ $secondaryFilter }}</div>
    @endisset
</div>

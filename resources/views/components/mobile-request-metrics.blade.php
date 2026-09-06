@props(['scope'])

@php
    $metrics = [
        [
            'key' => 'queries',
            'section' => 'queries',
            'label' => 'Queries',
            'shortLabel' => 'QRY',
            'value' => 'summary.query_count',
            'ariaLabel' => 'Open query details',
        ],
        [
            'key' => 'duration',
            'section' => 'request',
            'label' => 'Time',
            'shortLabel' => 'Time',
            'value' => 'summary.duration_label',
            'ariaLabel' => 'Open request timing',
        ],
        [
            'key' => 'memory',
            'section' => null,
            'label' => 'Peak MB',
            'shortLabel' => 'MB',
            'value' => 'summary.peak_memory_mb',
        ],
    ];
@endphp

<div
    data-ndb-mobile-request-metrics="{{ $scope }}"
    role="group"
    aria-label="Request metrics"
    {{ $attributes->class('ndb:grid ndb:w-[8.25rem] ndb:flex-none ndb:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)_minmax(0,1fr)] ndb:items-stretch ndb:min-[360px]:w-36') }}
>
    @foreach ($metrics as $metric)
        @if ($metric['section'])
            <button
                type="button"
                data-ndb-mobile-toolbar-metric="{{ $metric['key'] }}"
                data-ndb-mobile-toolbar-metric-scope="{{ $scope }}"
                @click="inspectorOpen ? selectSection(@js($metric['section'])) : openInspector(@js($metric['section']))"
                aria-label="{{ $metric['ariaLabel'] }}"
                class="ndb:relative ndb:flex ndb:min-h-11 ndb:min-w-0 ndb:flex-col ndb:items-center ndb:justify-center ndb:rounded-lg ndb:transition-colors ndb:hover:bg-zinc-100/80 ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/10"
            >
        @else
            <div
                data-ndb-mobile-toolbar-metric="{{ $metric['key'] }}"
                data-ndb-mobile-toolbar-metric-scope="{{ $scope }}"
                class="ndb:relative ndb:flex ndb:min-h-11 ndb:min-w-0 ndb:flex-col ndb:items-center ndb:justify-center"
            >
        @endif
        <span
            data-ndb-mobile-toolbar-summary="{{ $metric['key'] }}"
            class="ndb:block ndb:max-w-full ndb:truncate ndb:text-xs ndb:font-bold ndb:leading-4 ndb:tabular-nums {{ $metric['key'] === 'duration' ? 'ndb:tracking-[-0.04em] ndb:max-[359px]:tracking-[-0.1em]' : '' }}"
            x-text="{{ $metric['value'] }}"
        ></span>
        <span
            data-ndb-mobile-toolbar-metric-label="{{ $metric['key'] }}"
            class="ndb:block ndb:max-w-full ndb:truncate ndb:text-xs ndb:font-semibold ndb:leading-[14px] ndb:uppercase ndb:tracking-normal ndb:text-zinc-400"
        >{{ $metric['shortLabel'] }}</span>
        @if ($metric['section'])
        </button>
        @else
        </div>
        @endif
    @endforeach
</div>

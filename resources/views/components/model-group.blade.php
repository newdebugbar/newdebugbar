{{-- Renders one model context as a compact selection row. --}}
@props(['group', 'index'])

@php
    $shortName = class_basename($group['model']);
    $retrievalCount = (int) ($group['load_count'] ?? 0);
    $changeCount = (int) ($group['change_count'] ?? 0);
    $repeatCount = (int) ($group['repeated_load_count'] ?? 0);
    $connection = is_string($group['connection'] ?? null) && $group['connection'] !== '' ? $group['connection'] : '—';
    $table = is_string($group['table'] ?? null) && $group['table'] !== '' ? $group['table'] : '—';
@endphp

<button
    type="button"
    data-ndb-model-group
    data-ndb-model-index="{{ $index }}"
    data-ndb-model-short-name="{{ $shortName }}"
    data-ndb-model-sort-name="{{ \Illuminate\Support\Str::lower($shortName) }}"
    data-ndb-model-sort-retrieved="{{ $retrievalCount }}"
    data-ndb-model-sort-writes="{{ $changeCount }}"
    data-ndb-model-sort-reloads="{{ $repeatCount }}"
    data-ndb-model-search-value="{{ \Illuminate\Support\Str::lower($group['model'].' '.$connection.' '.$table) }}"
    wire:key="model-group-{{ $index }}"
    aria-controls="newdebugbar-model-detail"
    @click="selectModelGroup({{ $index }})"
    :aria-pressed="modelSelected === {{ $index }}"
    :class="modelSelected === {{ $index }}
        ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
        : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
    class="ndb:grid ndb:h-auto ndb:w-full ndb:min-w-0 ndb:cursor-pointer ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-center ndb:gap-x-3 ndb:gap-y-1 ndb:border-l-0 ndb:bg-transparent ndb:px-3 ndb:py-2.5 ndb:text-left ndb:text-xs ndb:text-zinc-950 ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-white ndb:sm:grid-cols-[minmax(7rem,1fr)_3.5rem_2.75rem_3.75rem] ndb:sm:gap-x-2 ndb:sm:gap-y-2 ndb:sm:py-3"
>
    <span class="ndb:col-start-1 ndb:row-start-1 ndb:min-w-0">
        <span data-ndb-model-name class="ndb:block ndb:truncate ndb:text-xs ndb:font-bold">{{ $shortName }}</span>
        <span
            class="ndb:mt-0.5 ndb:block ndb:truncate ndb:text-xs ndb:text-zinc-400"
            title="{{ $connection }} connection, {{ $table }} table"
        >
            {{ $connection }}, {{ $table }}
        </span>
    </span>

    <span class="ndb:col-span-2 ndb:row-start-2 ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:gap-x-4 ndb:gap-y-1 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400 ndb:sm:hidden">
        <span>
            <span class="ndb:text-zinc-400">Retrieved</span>
            <strong class="ndb:font-semibold ndb:tabular-nums ndb:text-zinc-700 ndb:dark:text-zinc-300">{{ number_format($retrievalCount) }}</strong>
        </span>
        <span>
            <span class="ndb:text-zinc-400">Writes</span>
            <strong class="ndb:font-semibold ndb:tabular-nums ndb:text-zinc-700 ndb:dark:text-zinc-300">{{ number_format($changeCount) }}</strong>
        </span>
        <span>
            <span class="ndb:text-zinc-400">Reloads</span>
            <strong
                @class([
                    'ndb:font-semibold ndb:tabular-nums',
                    'ndb:text-amber-700 ndb:dark:text-amber-300' => $repeatCount > 0,
                    'ndb:text-zinc-700 ndb:dark:text-zinc-300' => $repeatCount === 0,
                ])
            >{{ number_format($repeatCount) }}</strong>
        </span>
    </span>

    <span
        data-ndb-model-retrieved-column
        class="ndb:col-start-2 ndb:row-start-1 ndb:hidden ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-700 ndb:dark:text-zinc-300 ndb:sm:block"
    >{{ number_format($retrievalCount) }}</span>
    <span
        data-ndb-model-write-column
        class="ndb:col-start-3 ndb:row-start-1 ndb:hidden ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-700 ndb:dark:text-zinc-300 ndb:sm:block"
    >{{ number_format($changeCount) }}</span>
    <span
        data-ndb-model-extra-column
        @class([
            'ndb:col-start-4 ndb:row-start-1 ndb:hidden ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:sm:block',
            'ndb:text-amber-700 ndb:dark:text-amber-300' => $repeatCount > 0,
            'ndb:text-zinc-500 ndb:dark:text-zinc-400' => $repeatCount === 0,
        ])
    >{{ number_format($repeatCount) }}</span>
</button>

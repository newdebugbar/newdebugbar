@props(['item'])

<button
    type="button"
    data-ndb-cache-item="{{ $item['execution'] }}"
    data-ndb-cache-execution="{{ $item['execution'] }}"
    data-ndb-cache-category="{{ $item['category'] }}"
    data-ndb-cache-failed="{{ ($item['failed'] ?? false) ? 'true' : 'false' }}"
    data-ndb-cache-search-text="{{ $item['search'] }}"
    @click="selectCacheOperation({{ $item['execution'] }})"
    :aria-pressed="cacheSelected === {{ $item['execution'] }}"
    :class="cacheSelected === {{ $item['execution'] }}
        ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
        : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
    class="ndb:grid ndb:h-auto ndb:w-full ndb:grid-cols-[4rem_minmax(0,1fr)_4.75rem] ndb:items-center ndb:gap-x-2 ndb:gap-y-0.5 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
>
    <x-newdebugbar::inspector-operation-badge
        wide
        data-ndb-cache-operation
        class="ndb:col-start-1 ndb:row-span-2 ndb:row-start-1 ndb:self-center"
    >
        {{ $item['operation_label'] }}
    </x-newdebugbar::inspector-operation-badge>
    <span
        data-ndb-cache-key
        :title="{{ \Illuminate\Support\Js::from($item['key_label']) }}"
        class="ndb:col-start-2 ndb:row-start-1 ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-semibold ndb:text-zinc-800 ndb:dark:text-zinc-200"
    >{{ $item['key_label'] }}</span>
    <span
        data-ndb-cache-result
        @class([
            'ndb:col-start-3 ndb:row-start-1 ndb:w-full ndb:text-right ndb:text-xs ndb:font-bold',
            'ndb:text-red-600 ndb:dark:text-red-300' => $item['failed'] ?? false,
            'ndb:text-amber-600 ndb:dark:text-amber-300' => ! ($item['failed'] ?? false) && in_array($item['result'], ['miss', 'flushed'], true),
            'ndb:text-zinc-500 ndb:dark:text-zinc-400' => ! ($item['failed'] ?? false) && ! in_array($item['result'], ['miss', 'flushed'], true),
        ])
    >{{ $item['result_label'] }}</span>
    <span class="ndb:col-start-2 ndb:row-start-2 ndb:min-w-0 ndb:truncate ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
        {{ $item['store_label'] }}
    </span>
    <span
        data-ndb-cache-list-duration
        class="ndb:col-start-3 ndb:row-start-2 ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400"
    >{{ $item['duration_label'] }}</span>
</button>

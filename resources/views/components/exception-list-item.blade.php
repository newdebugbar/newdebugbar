@props(['exception', 'index'])

<button
    type="button"
    data-ndb-exception-item="{{ $index }}"
    wire:key="exception-list-{{ $index }}"
    @click="selectException({{ $index }})"
    :aria-pressed="selectedException === {{ $index }}"
    :class="selectedException === {{ $index }}
        ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
        : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
    class="ndb:block ndb:h-auto ndb:w-full ndb:min-w-0 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:sm:py-3"
>
    <code class="ndb:block ndb:min-w-0 ndb:truncate ndb:font-mono ndb:text-xs ndb:font-bold">
        {{ $exception['class'] }}
    </code>
    <span class="ndb:mt-1 ndb:block ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-semibold">
        {{ $exception['message'] ?: 'No exception message' }}
    </span>
    <span class="ndb:mt-1 ndb:block ndb:min-w-0 ndb:truncate ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
        {{ $exception['file'] }}:{{ $exception['line'] }}
    </span>
</button>

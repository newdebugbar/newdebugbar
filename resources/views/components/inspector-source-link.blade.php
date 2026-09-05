@props(['copy' => null])

<button
    type="button"
    data-ndb-inspector-source-link
    @if ($copy !== null)
        x-on:click="copyText(@js($copy))"
    @endif
    {{
        $attributes->class(
            'ndb:inline-flex ndb:h-auto ndb:min-h-0 ndb:max-w-full ndb:items-center ndb:border-0 ndb:bg-transparent ndb:p-0 ndb:text-left ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:underline ndb:decoration-zinc-300 ndb:decoration-1 ndb:underline-offset-2 ndb:transition-colors ndb:hover:bg-transparent ndb:hover:text-zinc-950 ndb:hover:decoration-zinc-600 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:decoration-zinc-600 ndb:dark:hover:bg-transparent ndb:dark:hover:text-white ndb:dark:hover:decoration-zinc-300',
        )
    }}
>
    @isset($value)
        <span {{ $value->attributes->class('ndb:min-w-0 ndb:truncate') }}>{{ $value }}</span>
    @else
        <span class="ndb:min-w-0 ndb:truncate">{{ $slot }}</span>
    @endisset
</button>

@props(['inspector' => null])

@if ($inspector)
    <button
        type="button"
        @click="openInspector(@js($inspector))"
        {{ $attributes->class('ndb:self-stretch ndb:items-center ndb:gap-2 ndb:rounded-xl ndb:px-2.5 ndb:py-1.5 ndb:text-left ndb:transition ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/10') }}
    >
        {{ $slot }}
    </button>
@else
    <div {{ $attributes->class('ndb:self-stretch ndb:items-center ndb:gap-2 ndb:px-2.5 ndb:py-1.5 ndb:text-left') }}>
        {{ $slot }}
    </div>
@endif

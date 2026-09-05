@props(['icon'])

<button
    type="button"
    {{ $attributes->class('ndb:inline-flex ndb:h-auto ndb:min-h-9 ndb:shrink-0 ndb:items-center ndb:gap-2 ndb:rounded-lg ndb:px-2 ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:transition-colors ndb:hover:bg-zinc-100 ndb:hover:text-zinc-900 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-400 ndb:dark:hover:bg-zinc-800 ndb:dark:hover:text-zinc-100') }}
>
    <x-newdebugbar::icon :name="$icon" size="3.5" />
    {{ $slot }}
</button>

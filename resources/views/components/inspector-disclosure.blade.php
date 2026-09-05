@props([
    'label',
    'resetOn' => null,
])

<details
    data-ndb-inspector-disclosure
    x-data="{ newdebugbarDisclosureOpen: false }"
    @toggle="
        newdebugbarDisclosureOpen = $el.open;
        if ($el.open) $nextTick(() => window.newDebugBarHighlight?.($el));
    "
    @if ($resetOn !== null)
        x-effect="{{ $resetOn }}; $el.open = false; newdebugbarDisclosureOpen = false"
    @endif
    {{ $attributes->class('ndb:group/disclosure ndb:min-w-0 ndb:border-t ndb:border-zinc-200/90 ndb:dark:border-zinc-800') }}
>
    <summary class="ndb:flex ndb:min-h-11 ndb:cursor-pointer ndb:list-none ndb:items-center ndb:gap-3 ndb:rounded-md ndb:py-3 ndb:text-sm ndb:font-medium ndb:text-zinc-700 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:[&::-webkit-details-marker]:hidden">
        <x-newdebugbar::icon
            name="chevron-down"
            size="3.5"
            class="ndb:shrink-0 ndb:-rotate-90 ndb:text-zinc-400 ndb:transition-transform ndb:group-open/disclosure:rotate-0 ndb:motion-reduce:transition-none"
        />
        <span class="ndb:min-w-0 ndb:flex-1">
            @isset($summary)
                {{ $summary }}
            @else
                {{ $label }}
            @endisset
        </span>
        @isset($count)
            <span {{ $count->attributes->class('ndb:shrink-0 ndb:text-xs ndb:font-normal ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400') }}>{{ $count }}</span>
        @endisset
    </summary>
    <template x-if="newdebugbarDisclosureOpen">
        <div data-ndb-inspector-disclosure-content class="ndb:min-w-0 ndb:pb-4 ndb:text-sm">{{ $slot }}</div>
    </template>
</details>

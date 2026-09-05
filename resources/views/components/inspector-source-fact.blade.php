@props([
    'code' => false,
    'label' => null,
])

<div
    data-ndb-inspector-source-fact
    {{ $attributes->class('ndb:grid ndb:min-w-0 ndb:gap-1 ndb:border-0 ndb:bg-transparent ndb:px-0 ndb:py-3 ndb:text-sm ndb:text-zinc-700 ndb:@sm:grid-cols-[8rem_minmax(0,1fr)] ndb:@sm:items-baseline ndb:@sm:gap-4 ndb:dark:text-zinc-200') }}
>
    @isset($term)
        <dt {{ $term->attributes->class('ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400') }}>
            {{ $term }}
        </dt>
    @else
        <dt class="ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400">{{ $label }}</dt>
    @endisset
    <dd class="ndb:min-w-0 ndb:break-words ndb:text-sm ndb:leading-5 ndb:text-zinc-700 ndb:dark:text-zinc-200">
        @isset($value)
            @if ($code)
                <code {{ $value->attributes->class('ndb:block ndb:min-w-0 ndb:break-all ndb:font-mono ndb:text-xs') }}>{{ $value }}</code>
            @else
                <span {{ $value->attributes->class('ndb:block ndb:min-w-0') }}>{{ $value }}</span>
            @endif
        @else
            @if ($code)
                <code class="ndb:block ndb:min-w-0 ndb:break-all ndb:font-mono ndb:text-xs">{{ $slot }}</code>
            @else
                {{ $slot }}
            @endif
        @endisset
    </dd>
</div>

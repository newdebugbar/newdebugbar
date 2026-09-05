@props(['label'])
@aware(['layout' => 'grid'])

@php
    $inline = $layout === 'inline';
    $valueClasses = 'ndb:min-w-0 ndb:break-words ndb:bg-transparent ndb:text-sm ndb:text-zinc-800 ndb:dark:text-zinc-200'.($inline ? '' : ' ndb:mt-1');
@endphp

<div
    data-ndb-inspector-fact
    {{ $attributes->class(['ndb:min-w-0 ndb:bg-transparent', 'ndb:flex ndb:flex-wrap ndb:items-baseline ndb:gap-x-2 ndb:gap-y-1' => $inline]) }}
>
    <dt class="ndb:bg-transparent ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400">
        {{ $label }}
    </dt>
    @isset($value)
        <dd {{ $value->attributes->class($valueClasses) }}>{{ $value }}</dd>
    @else
        <dd class="{{ $valueClasses }}">{{ $slot }}</dd>
    @endisset
</div>

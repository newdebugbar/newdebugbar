@props(['layout' => 'grid'])

@php
    $primaryClasses = match ($layout) {
        'grid' => 'ndb:grid ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-start ndb:gap-x-3 ndb:gap-y-2',
        'wrap' => 'ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:items-center ndb:justify-between ndb:gap-3',
        default => throw new \InvalidArgumentException("Unknown inspector detail header layout [{$layout}]."),
    };
@endphp

<header {{ $attributes->class('ndb:border-b ndb:border-zinc-200/90 ndb:p-3 ndb:sm:p-4 ndb:dark:border-zinc-800') }}>
    <div data-ndb-inspector-detail-header-primary class="{{ $primaryClasses }}">
        {{ $title }}
        @isset($aside)
            {{ $aside }}
        @endisset
    </div>

    @isset($identity)
        <div {{ $identity->attributes->class('ndb:mt-3 ndb:rounded-lg ndb:bg-zinc-50/85 ndb:px-3 ndb:py-2.5 ndb:ring-1 ndb:ring-inset ndb:ring-zinc-200/70 ndb:dark:bg-zinc-900/65 ndb:dark:ring-zinc-800') }}>
            {{ $identity }}
        </div>
    @endisset

    @isset($metadata)
        <dl {{ $metadata->attributes->class('ndb:mt-2.5 ndb:flex ndb:flex-wrap ndb:items-center ndb:gap-x-3 ndb:gap-y-1 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400') }}>
            {{ $metadata }}
        </dl>
    @endisset
</header>

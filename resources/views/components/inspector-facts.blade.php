@props([
    'columns' => 4,
    'bordered' => true,
    'layout' => 'grid',
])

@php
    $columnClasses = match ((int) $columns) {
        1 => 'ndb:grid-cols-1',
        2 => 'ndb:grid-cols-1 ndb:@xs:grid-cols-2',
        4 => 'ndb:grid-cols-1 ndb:@xs:grid-cols-2 ndb:@2xl:grid-cols-4',
        default => throw new \InvalidArgumentException("Unsupported inspector fact column count [{$columns}]."),
    };
    $layoutClasses = match ($layout) {
        'grid' => "ndb:grid ndb:gap-x-6 ndb:gap-y-3 {$columnClasses}",
        'inline' => 'ndb:flex ndb:flex-wrap ndb:items-baseline ndb:gap-x-6 ndb:gap-y-2',
        default => throw new \InvalidArgumentException("Unknown inspector fact layout [{$layout}]."),
    };
    $borderClasses = $bordered
        ? 'ndb:border-b ndb:border-zinc-200/90 ndb:pb-3 ndb:sm:pb-4 ndb:dark:border-zinc-800'
        : '';
@endphp

<dl
    data-ndb-inspector-facts="{{ $layout }}"
    {{ $attributes->class("ndb:min-w-0 ndb:border-t-0 ndb:bg-transparent ndb:pt-0 ndb:text-zinc-700 ndb:dark:text-zinc-200 {$layoutClasses} {$borderClasses}") }}
>
    {{ $slot }}
</dl>

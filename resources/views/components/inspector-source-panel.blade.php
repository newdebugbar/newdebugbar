@props([
    'frames',
    'columns' => 1,
    'emptyLabel' => 'No application stack was captured.',
    'title' => null,
    'resetOn' => null,
])

@php
    $columnClasses = match ((int) $columns) {
        1 => 'ndb:grid-cols-1',
        2 => 'ndb:grid-cols-1 ndb:@2xl:grid-cols-2',
        default => throw new \InvalidArgumentException("Unsupported inspector source panel column count [{$columns}]."),
    };
@endphp

<section data-ndb-inspector-source-panel {{ $attributes->class('ndb:p-3 ndb:sm:p-4') }}>
    @if ($title !== null || isset($actions))
        <div @class([
            'ndb:mb-3 ndb:flex ndb:items-center ndb:gap-3',
            'ndb:justify-between' => $title !== null && isset($actions),
            'ndb:justify-end' => $title === null && isset($actions),
        ])>
            @if ($title !== null)
                <h4 class="ndb:text-sm ndb:font-semibold ndb:text-zinc-800 ndb:dark:text-zinc-100">{{ $title }}</h4>
            @endif
            @isset($actions)
                <div {{ $actions->attributes->class('ndb:shrink-0') }}>{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <dl class="ndb:grid ndb:min-w-0 ndb:gap-x-6 ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800 {{ $columnClasses }}">
        {{ $slot }}
    </dl>

    <template x-if="({{ $frames }}).length > 0">
        <x-newdebugbar::inspector-disclosure label="Application stack" :reset-on="$resetOn">
            <x-slot:count x-text="({{ $frames }}).length + (({{ $frames }}).length === 1 ? ' frame' : ' frames')"></x-slot:count>
            <x-newdebugbar::inspector-stack
                :frames="$frames"
                :empty-label="$emptyLabel"
                :show-heading="false"
                class="ndb:mt-0 ndb:sm:mt-0"
            />
        </x-newdebugbar::inspector-disclosure>
    </template>
</section>

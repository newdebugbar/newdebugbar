@props([
    'name' => null,
    'copy' => null,
    'copyExpression' => null,
    'iconClass' => 'ndb:size-4',
    'darkSurface' => false,
    'colorOnly' => false,
])

@php
    $attributes = $attributes->class([
        'ndb:inline-flex ndb:items-center ndb:justify-center ndb:text-zinc-500 ndb:transition ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:disabled:pointer-events-none ndb:disabled:opacity-25',
        'ndb:hover:bg-zinc-100 ndb:hover:text-zinc-950 ndb:dark:hover:text-white' => ! $colorOnly,
        'ndb:hover:text-indigo-600 ndb:dark:hover:text-indigo-300' => $colorOnly,
        'ndb:dark:text-zinc-300 ndb:dark:hover:bg-white/10' => $darkSurface && ! $colorOnly,
        'ndb:dark:text-zinc-300' => $darkSurface && $colorOnly,
        'ndb:dark:text-zinc-400 ndb:dark:hover:bg-zinc-800' => ! $darkSurface && ! $colorOnly,
        'ndb:dark:text-zinc-400' => ! $darkSurface && $colorOnly,
    ]);
@endphp

@if ($copy !== null || $copyExpression !== null)
    <x-newdebugbar::copy-button
        :copy="$copy"
        :copy-expression="$copyExpression"
        :icon="$name ?? 'copy'"
        :icon-class="$iconClass"
        :label="$attributes->get('aria-label')"
        {{ $attributes->except('aria-label') }}
    />
@else
    <button type="button" {{ $attributes }}>
        @if ($name)
            <x-newdebugbar::icon :name="$name" :class="$iconClass" />
        @else
            {{ $slot }}
        @endif
    </button>
@endif

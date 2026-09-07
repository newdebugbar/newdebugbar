@props([
    'copy' => null,
    'copyExpression' => null,
    'icon' => null,
    'iconClass' => 'ndb:size-3.5',
    'label' => null,
])

@php
    $copyValue = $copyExpression ?? \Illuminate\Support\Js::from($copy);
    $accessibleLabel = $label !== null
        ? \Illuminate\Support\Js::from($label)
        : $attributes->get(':aria-label', \Illuminate\Support\Js::from($attributes->get('aria-label')));
@endphp

<button
    type="button"
    data-ndb-copy-button
    x-data="copyControl()"
    x-effect="syncCopyValue({{ $copyValue }})"
    @click="copyWithFeedback({{ $copyValue }})"
    x-bind:aria-label="copyStatus || ({{ $accessibleLabel }})"
    @if ($label !== null)
        aria-label="{{ $label }}"
    @endif
    {{ $attributes->except(['aria-label', ':aria-label'])->class('ndb:relative') }}
>
    @if ($icon)
        <x-newdebugbar::icon :name="$icon" :class="$iconClass" x-show.important="copyStatus !== 'Copied'" />
        <x-newdebugbar::icon name="check" :class="$iconClass" x-show.important="copyStatus === 'Copied'" />
    @endif
    @if ($label !== null)
        <span
            x-cloak
            x-show.important="copyStatus"
            x-text="copyStatus"
            role="status"
            class="ndb:pointer-events-none ndb:absolute ndb:bottom-full ndb:left-1/2 ndb:mb-1 ndb:-translate-x-1/2 ndb:rounded-md ndb:bg-zinc-900 ndb:px-2 ndb:py-1 ndb:text-xs ndb:whitespace-nowrap ndb:text-white ndb:dark:bg-zinc-100 ndb:dark:text-zinc-900"
        ></span>
    @else
        <span class="ndb:relative ndb:block ndb:min-w-0">
            <span
                class="ndb:block ndb:truncate"
                :class="copyStatus ? 'ndb:invisible' : ''"
                :aria-hidden="copyStatus ? 'true' : null"
            >{{ $slot }}</span>
            <span
                x-cloak
                x-show.important="copyStatus"
                x-text="copyStatus"
                role="status"
                class="ndb:absolute ndb:inset-0 ndb:text-left"
            ></span>
        </span>
    @endif
</button>

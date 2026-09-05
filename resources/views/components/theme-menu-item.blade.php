@php
    $themes = [
        'system' => ['System', 'monitor'],
        'light' => ['Light', 'sun'],
        'dark' => ['Dark', 'moon'],
    ];
@endphp

<div role="group" aria-label="Color theme" {{ $attributes->class('ndb:py-1') }}>
    <p class="ndb:px-3 ndb:pb-1 ndb:pt-1 ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400">
        Color theme
    </p>
    @foreach ($themes as $theme => [$label, $icon])
        <button
            type="button"
            role="menuitemradio"
            data-ndb-mobile-theme-option="{{ $theme }}"
            @click="setTheme(@js($theme)); closeMobileToolbarMenu()"
            :aria-checked="theme === @js($theme)"
            :class="theme === @js($theme) ? 'ndb:bg-indigo-50 ndb:text-indigo-700 ndb:dark:bg-indigo-950/70 ndb:dark:text-indigo-300' : 'ndb:hover:bg-zinc-100 ndb:dark:hover:bg-white/10'"
            class="ndb:flex ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2 ndb:text-left ndb:transition-colors ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
        >
            <x-newdebugbar::icon
                :name="$icon"
                class="ndb:size-4 ndb:shrink-0 ndb:text-zinc-500 ndb:dark:text-zinc-400"
            />
            <span class="ndb:min-w-0 ndb:flex-1 ndb:text-sm ndb:font-medium">{{ $label }}</span>
            <span
                x-cloak
                x-show.important="theme === @js($theme)"
                class="ndb:flex ndb:size-4 ndb:shrink-0 ndb:items-center ndb:justify-center"
            >
                <x-newdebugbar::icon name="check" class="ndb:size-4" />
            </span>
        </button>
    @endforeach
</div>

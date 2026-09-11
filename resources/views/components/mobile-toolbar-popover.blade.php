@props([
    'id',
    'menu',
    'label',
    'direction' => 'dynamic',
])

@php
    $expanded = $menu === 'header-actions';
    $actionAttribute = $expanded ? 'data-ndb-header-mobile-action' : 'data-ndb-mobile-toolbar-action';
    $actions = [
        ['key' => 'palette', 'label' => 'Command palette', 'icon' => 'search', 'click' => 'openPalette()'],
        $expanded
            ? ['key' => 'shrink', 'label' => 'Shrink inspector', 'icon' => 'shrink', 'click' => 'closeInspector()']
            : ['key' => 'inspector', 'label' => 'Open', 'icon' => 'expand', 'click' => "openInspector('request')"],
        ['key' => 'dismiss', 'label' => 'Hide until reload', 'icon' => 'close', 'click' => 'dismissBar()'],
    ];
@endphp

<x-newdebugbar::popover-surface
    id="{{ $id }}"
    x-cloak
    x-show.important="mobileToolbarMenu === '{{ $menu }}'"
    x-transition:enter="ndb:transition ndb:duration-150 ndb:ease-out"
    x-transition:enter-start="ndb:scale-95 ndb:opacity-0"
    x-transition:enter-end="ndb:scale-100 ndb:opacity-100"
    x-transition:leave="ndb:transition ndb:duration-100 ndb:ease-in"
    x-transition:leave-start="ndb:scale-100 ndb:opacity-100"
    x-transition:leave-end="ndb:scale-95 ndb:opacity-0"
    role="menu"
    aria-label="{{ $label }}"
    @dragstart.stop=""
    @keydown.tab.stop="keepFocusWithin($event, $el)"
    @keydown.arrow-up.prevent="moveMobileToolbarMenu(-1, $el)"
    @keydown.arrow-down.prevent="moveMobileToolbarMenu(1, $el)"
    @resize.window="closeMobileToolbarMenu(false)"
    data-ndb-mobile-toolbar-menu="{{ $menu }}"
    :direction="$direction"
    :mobile-menu="$menu"
    width-class="ndb:w-80 ndb:max-w-[calc(100vw-2.5rem)]"
>
    <div
        data-ndb-mobile-toolbar-popover-items
        @class([
            'ndb-scrollbar ndb:flex ndb:flex-col ndb:gap-0.5 ndb:overflow-y-auto ndb:overscroll-contain',
            'ndb:max-h-[calc(min(82dvh,780px)-6rem)]' => $expanded,
            'ndb:max-h-[calc(100dvh-6.5rem)]' => ! $expanded,
        ])
    >
        <div role="group" aria-label="Controls" class="ndb:flex ndb:shrink-0 ndb:flex-col ndb:gap-0.5">
            @foreach ($actions as $action)
                <button
                    type="button"
                    role="menuitem"
                    {{ $actionAttribute }}="{{ $action['key'] }}"
                    @click="{{ $action['click'] }}"
                    class="ndb:flex ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2 ndb:text-left ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/10"
                >
                    <x-newdebugbar::icon
                        :name="$action['icon']"
                        class="ndb:size-4 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                    />
                    <span class="ndb:text-sm ndb:font-medium">{{ $action['label'] }}</span>
                </button>
            @endforeach
        </div>
        <x-newdebugbar::theme-menu-item :attributes="new \Illuminate\View\ComponentAttributeBag([$actionAttribute => 'theme'])" />
        <div
            role="separator"
            class="ndb:mx-2 ndb:my-2 ndb:h-px ndb:shrink-0 ndb:bg-zinc-200 ndb:dark:bg-zinc-800"
        ></div>
        <template x-if="mobileToolbarMenu === '{{ $menu }}'">
            <x-newdebugbar::inspector-navigation mobile />
        </template>
    </div>
</x-newdebugbar::popover-surface>

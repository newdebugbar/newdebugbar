{{-- Renders the compact debug toolbar and its responsive action menu. --}}
<div
    x-cloak
    x-show.important="barVisible && ! inspectorOpen"
    x-transition.opacity.duration.150ms
    role="toolbar"
    aria-label="Debug toolbar"
    aria-describedby="newdebugbar-toolbar-drag-hint"
    data-ndb-toolbar-shell
    :data-ndb-placement="toolbarPlacement"
    :data-ndb-preferred-placement="toolbarPreferredPlacement"
    :data-ndb-dragging="toolbarDragging"
    :data-ndb-drag-target="toolbarDragTarget"
    :data-ndb-rebasing="toolbarRebasing"
    :data-ndb-snapping="toolbarSnapping"
    :data-ndb-form="toolbarIsCorner ? 'corner' : 'center'"
    :style="{
        '--ndb-toolbar-drag-x': toolbarDragOffsetX + 'px',
        '--ndb-toolbar-drag-y': toolbarDragOffsetY + 'px',
    }"
    :class="[
        toolbarIsTop ? 'ndb:top-3' : 'ndb:bottom-3',
        toolbarIsLeft ? 'ndb:left-3' : toolbarIsRight ? 'ndb:right-3' : 'ndb:left-1/2 ndb:-translate-x-1/2',
        toolbarIsCorner
            ? 'ndb:h-14 ndb:w-[196px] ndb:gap-1 ndb:rounded-[18px] ndb:border-white/70 ndb:bg-white/80 ndb:p-1.5 ndb:shadow-[0_18px_60px_-18px_rgba(24,24,27,0.4)] ndb:backdrop-blur-xl ndb:backdrop-brightness-110 ndb:backdrop-saturate-125 ndb:dark:border-white/10 ndb:dark:bg-zinc-950/90 ndb:dark:shadow-[0_18px_60px_-18px_rgba(0,0,0,0.8)] ndb:dark:backdrop-brightness-75 ndb:dark:backdrop-saturate-100'
            : 'ndb:w-[calc(100vw-24px)] ndb:max-w-[calc(100vw-24px)] ndb:gap-1 ndb:rounded-[18px] ndb:border-white/70 ndb:bg-white/80 ndb:py-1.5 ndb:pl-1.5 ndb:pr-1.5 ndb:shadow-[0_18px_60px_-18px_rgba(24,24,27,0.4)] ndb:backdrop-blur-xl ndb:backdrop-brightness-110 ndb:backdrop-saturate-125 ndb:sm:max-w-5xl ndb:sm:pr-3 ndb:dark:border-white/10 ndb:dark:bg-zinc-950/90 ndb:dark:shadow-[0_18px_60px_-18px_rgba(0,0,0,0.8)] ndb:dark:backdrop-brightness-75 ndb:dark:backdrop-saturate-100',
    ]"
    @pointerdown="startToolbarDrag($event)"
    @pointermove.window="moveToolbarDrag($event)"
    @pointerup.window="endToolbarDrag($event)"
    @pointercancel.window="cancelToolbarDrag($event)"
    @click.capture="consumeToolbarClick($event)"
    @dragstart.prevent
    @transitionend.self="if ($event.propertyName === 'transform') finishToolbarSnap();"
    class="ndb-toolbar-draggable ndb:pointer-events-auto ndb:fixed ndb:flex ndb:items-stretch ndb:border"
>
    <div
        x-cloak
        x-show.important="! toolbarIsCorner"
        data-ndb-center-toolbar
        class="ndb:flex ndb:min-w-0 ndb:flex-1 ndb:items-stretch ndb:gap-1"
    >
        <x-newdebugbar::request-switcher
            scope="toolbar"
            class="ndb:min-w-0 ndb:flex-1 ndb:sm:w-[9.5rem] ndb:sm:flex-none ndb:md:w-[11.5rem] ndb:lg:w-auto ndb:lg:max-w-[18.5rem]"
        />

        <x-newdebugbar::mobile-request-metrics
            scope="toolbar"
            data-ndb-mobile-toolbar-control="metrics"
            class="ndb:sm:hidden"
        />

        <div
            data-ndb-toolbar-facts
            class="ndb-toolbar-facts ndb:hidden ndb:min-w-0 ndb:flex-1 ndb:items-stretch ndb:gap-1 ndb:sm:ml-auto ndb:sm:flex ndb:sm:flex-none"
        >
            <x-newdebugbar::toolbar-button
                data-ndb-toolbar="environment"
                class="ndb:order-1 ndb:hidden ndb:min-w-max ndb:shrink-0 ndb:sm:px-2 ndb:md:flex ndb:lg:px-2.5"
            >
                <span
                    class="ndb:size-2 ndb:shrink-0 ndb:rounded-full"
                    :class="summary.warning ? 'ndb:bg-amber-500' : 'ndb:bg-emerald-500'"
                ></span>
                <span
                    ><span
                        class="ndb:hidden ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400 ndb:lg:block"
                        >Environment</span
                    ><span
                        class="ndb:block ndb:max-w-24 ndb:truncate ndb:text-xs ndb:font-bold ndb:sm:text-xs"
                        x-text="summary.environment"
                    ></span
                ></span>
            </x-newdebugbar::toolbar-button>

            <x-newdebugbar::toolbar-button
                section="request"
                data-ndb-toolbar="duration"
                class="ndb:order-3 ndb:flex ndb:min-w-max ndb:shrink-0 ndb:sm:px-2 ndb:lg:px-2.5"
            >
                <x-newdebugbar::icon
                    name="clock"
                    class="ndb:size-3.5 ndb:shrink-0 ndb:text-indigo-500 ndb:dark:text-indigo-400"
                />
                <span
                    ><span
                        class="ndb:hidden ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400 ndb:lg:block"
                        >Duration</span
                    ><span
                        class="ndb:block ndb:whitespace-nowrap ndb:text-xs ndb:font-bold ndb:tabular-nums"
                        x-text="summary.duration_label"
                    ></span
                ></span>
            </x-newdebugbar::toolbar-button>

            <x-newdebugbar::toolbar-button
                data-ndb-toolbar="memory"
                class="ndb:order-4 ndb:flex ndb:min-w-max ndb:shrink-0 ndb:sm:px-2 ndb:lg:px-2.5"
            >
                <x-newdebugbar::icon
                    name="memory"
                    class="ndb:size-3.5 ndb:shrink-0 ndb:text-indigo-500 ndb:dark:text-indigo-400"
                />
                <span
                    ><span
                        class="ndb:hidden ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400 ndb:lg:block"
                        >Peak</span
                    ><span
                        class="ndb:block ndb:whitespace-nowrap ndb:text-xs ndb:font-bold ndb:tabular-nums"
                        x-text="summary.peak_memory_mb + ' MB'"
                    ></span
                ></span>
            </x-newdebugbar::toolbar-button>

            <x-newdebugbar::toolbar-button
                section="queries"
                data-ndb-toolbar="queries"
                class="ndb:order-2 ndb:flex ndb:min-w-max ndb:shrink-0 ndb:sm:px-2 ndb:lg:px-2.5"
            >
                <x-newdebugbar::icon
                    name="database"
                    class="ndb:size-3.5 ndb:shrink-0 ndb:text-indigo-500 ndb:dark:text-indigo-400"
                />
                <span
                    ><span
                        class="ndb:hidden ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400 ndb:lg:block"
                        >Queries</span
                    ><span
                        class="ndb:flex ndb:items-center ndb:gap-1.5 ndb:whitespace-nowrap ndb:text-xs ndb:font-bold ndb:tabular-nums"
                        ><span x-text="summary.query_count"></span
                        ><span
                            class="ndb:hidden ndb:font-medium ndb:text-zinc-400 ndb:lg:inline"
                            x-text="summary.query_time_label"
                        ></span></span
                ></span>
            </x-newdebugbar::toolbar-button>
        </div>

        <div
            data-ndb-mobile-toolbar-control="actions"
            @click.outside="if (mobileToolbarMenu === 'actions') closeMobileToolbarMenu(false);"
            class="ndb:relative ndb:flex ndb:shrink-0 ndb:sm:hidden"
        >
            <button
                type="button"
                data-ndb-mobile-toolbar-trigger="actions"
                @click="toggleMobileToolbarMenu('actions', $el)"
                :aria-expanded="mobileToolbarMenu === 'actions'"
                aria-controls="newdebugbar-mobile-actions"
                aria-label="Show debug bar actions"
                :class="mobileToolbarMenu === 'actions' ? 'ndb:text-indigo-600 ndb:dark:text-indigo-300' : ''"
                class="ndb:inline-flex ndb:size-11 ndb:items-center ndb:justify-center ndb:text-zinc-700 ndb:transition-colors ndb:hover:text-indigo-600 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-300 ndb:dark:hover:text-indigo-300"
            >
                <x-newdebugbar::icon name="ellipsis" class="ndb:size-5" />
            </button>

            <x-newdebugbar::mobile-toolbar-popover
                id="newdebugbar-mobile-actions"
                menu="actions"
                label="Debug bar actions"
            >
                <button
                    type="button"
                    role="menuitem"
                    data-ndb-mobile-toolbar-action="palette"
                    @click="openPalette()"
                    class="ndb:flex ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2 ndb:text-left ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/10"
                >
                    <x-newdebugbar::icon name="search" class="ndb:size-4 ndb:text-zinc-500 ndb:dark:text-zinc-400" />
                    <span class="ndb:text-sm ndb:font-medium">Command palette</span>
                </button>
                <button
                    type="button"
                    role="menuitem"
                    data-ndb-mobile-toolbar-action="inspector"
                    @click="openInspector('request')"
                    class="ndb:flex ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2 ndb:text-left ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/10"
                >
                    <x-newdebugbar::icon name="expand" class="ndb:size-4 ndb:text-zinc-500 ndb:dark:text-zinc-400" />
                    <span class="ndb:text-sm ndb:font-medium">Open</span>
                </button>
                <x-newdebugbar::theme-menu-item data-ndb-mobile-toolbar-action="theme" />
                <button
                    type="button"
                    role="menuitem"
                    data-ndb-mobile-toolbar-action="dismiss"
                    @click="dismissBar()"
                    class="ndb:flex ndb:min-h-11 ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2 ndb:text-left ndb:transition-colors ndb:hover:bg-zinc-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:hover:bg-white/10"
                >
                    <x-newdebugbar::icon name="close" class="ndb:size-4 ndb:text-zinc-500 ndb:dark:text-zinc-400" />
                    <span class="ndb:text-sm ndb:font-medium">Hide until reload</span>
                </button>
            </x-newdebugbar::mobile-toolbar-popover>
        </div>

        <div data-ndb-toolbar-actions class="ndb:hidden ndb:shrink-0 ndb:items-center ndb:gap-0.5 ndb:sm:flex">
            <div
                data-ndb-toolbar-utility-actions
                role="group"
                aria-label="Tools"
                class="ndb:flex ndb:items-center ndb:gap-0.5"
            >
                <x-newdebugbar::icon-button
                    name="search"
                    :dark-surface="true"
                    data-ndb-toolbar="palette"
                    @click="openPalette()"
                    class="ndb:size-9 ndb:rounded-xl"
                    aria-label="Open command palette"
                    title="Command palette (Command or Control + Shift + P)"
                />
                <x-newdebugbar::theme-toggle scope="toolbar" :dark-surface="true" data-ndb-toolbar-action="theme" />
            </div>
            <x-newdebugbar::window-controls data-ndb-window-controls="compact" :dark-surface="true" />
        </div>
    </div>

    <x-newdebugbar::corner-toolbar />
</div>

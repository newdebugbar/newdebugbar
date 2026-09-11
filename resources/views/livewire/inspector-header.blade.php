{{-- Renders responsive request facts and inspector window controls. --}}
<header class="ndb:relative ndb:z-40 ndb:shrink-0 ndb:overflow-visible ndb:border-b ndb:border-zinc-200/80 ndb:bg-white ndb:p-1.5 ndb:dark:border-zinc-800/80 ndb:dark:bg-zinc-950">
    <div data-ndb-header-mobile-toolbar class="ndb:flex ndb:min-w-0 ndb:items-stretch ndb:gap-1 ndb:sm:hidden">
        <x-newdebugbar::request-switcher scope="header-mobile" direction="below" class="ndb:min-w-0 ndb:flex-1" />

        <x-newdebugbar::mobile-request-metrics scope="header" data-ndb-header-mobile-control="metrics" />

        <div
            data-ndb-header-mobile-control="actions"
            @click.outside="if (mobileToolbarMenu === 'header-actions') closeMobileToolbarMenu(false);"
            class="ndb:relative ndb:flex ndb:shrink-0"
        >
            <button
                type="button"
                data-ndb-header-mobile-trigger="actions"
                @click="toggleMobileToolbarMenu('header-actions', $el)"
                :aria-expanded="mobileToolbarMenu === 'header-actions'"
                aria-controls="newdebugbar-header-mobile-actions"
                aria-label="Show inspector actions"
                :class="mobileToolbarMenu === 'header-actions' ? 'ndb:text-indigo-600 ndb:dark:text-indigo-300' : ''"
                class="ndb:inline-flex ndb:size-11 ndb:items-center ndb:justify-center ndb:text-zinc-700 ndb:transition-colors ndb:hover:text-indigo-600 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-300 ndb:dark:hover:text-indigo-300"
            >
                <x-newdebugbar::icon name="ellipsis" class="ndb:size-5" />
            </button>

            <x-newdebugbar::mobile-toolbar-popover
                id="newdebugbar-header-mobile-actions"
                menu="header-actions"
                label="Inspector actions"
                direction="below"
            />
        </div>
    </div>

    <div data-ndb-header-toolbar class="ndb:hidden ndb:items-stretch ndb:gap-1 ndb:sm:flex ndb:sm:flex-nowrap">
        <x-newdebugbar::request-switcher
            scope="header"
            direction="below"
            class="ndb:w-[9.5rem] ndb:flex-none ndb:md:w-[11.5rem] ndb:lg:w-auto ndb:lg:max-w-[18.5rem]"
        />

        <div
            data-ndb-header-facts
            class="ndb-scrollbar ndb:flex ndb:min-w-0 ndb:flex-1 ndb:gap-2 ndb:overflow-x-auto ndb:overscroll-x-contain ndb:pb-0.5 ndb:sm:order-none ndb:sm:ml-auto ndb:sm:w-auto ndb:sm:flex-none ndb:sm:gap-1 ndb:sm:overflow-visible ndb:sm:pb-0"
        >
            <x-newdebugbar::toolbar-button
                data-ndb-header-fact="environment"
                class="ndb:order-1 ndb:flex ndb:min-w-max ndb:shrink-0 ndb:sm:px-2 ndb:lg:px-2.5"
            >
                <span
                    class="ndb:size-2 ndb:shrink-0 ndb:rounded-full"
                    :class="summary.warning ? 'ndb:bg-amber-500' : 'ndb:bg-emerald-500'"
                ></span>
                <span class="ndb:min-w-0"
                    ><span
                        class="ndb:hidden ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400 ndb:lg:block"
                        >Environment</span
                    ><span
                        data-ndb-header-environment
                        class="ndb:block ndb:max-w-24 ndb:truncate ndb:text-xs ndb:font-bold"
                        x-text="summary.environment"
                    ></span
                ></span>
            </x-newdebugbar::toolbar-button>

            <x-newdebugbar::toolbar-button
                inspector="request"
                data-ndb-header-fact="duration"
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
                data-ndb-header-fact="memory"
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
                        data-ndb-header-memory
                        class="ndb:block ndb:whitespace-nowrap ndb:text-xs ndb:font-bold ndb:tabular-nums"
                        x-text="summary.peak_memory_mb + ' MB'"
                    ></span
                ></span>
            </x-newdebugbar::toolbar-button>

            <x-newdebugbar::toolbar-button
                inspector="queries"
                data-ndb-header-fact="queries"
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
                        ><span data-ndb-header-query-count x-text="summary.query_count"></span
                        ><span
                            data-ndb-header-query-duration
                            class="ndb:hidden ndb:font-medium ndb:text-zinc-400 ndb:lg:inline"
                            x-text="summary.query_time_label"
                        ></span></span
                ></span>
            </x-newdebugbar::toolbar-button>
        </div>

        <div data-ndb-inspector-actions class="ndb:flex ndb:items-center ndb:gap-0.5">
            <div
                data-ndb-inspector-utility-actions
                role="group"
                aria-label="Tools"
                class="ndb:flex ndb:items-center ndb:gap-0.5"
            >
                <x-newdebugbar::icon-button
                    name="search"
                    data-ndb-inspector-action="palette"
                    @click="openPalette()"
                    class="ndb:size-9 ndb:rounded-xl"
                    aria-label="Open command palette"
                />
                <x-newdebugbar::theme-toggle scope="header" direction="below" data-ndb-inspector-action="theme" />
            </div>
            <x-newdebugbar::window-controls data-ndb-window-controls="expanded" />
        </div>
    </div>
</header>

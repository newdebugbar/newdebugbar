{{-- Renders the expanded inspector shell, navigation, and section content. --}}
<div
    x-cloak
    x-show.important="barVisible && inspectorOpen"
    class="ndb:pointer-events-auto ndb:fixed ndb:inset-0"
    role="presentation"
>
    <div
        data-ndb-backdrop
        x-show.important="barVisible && inspectorOpen"
        x-transition.opacity.duration.150ms
        @click="closeInspector()"
        class="ndb:absolute ndb:inset-0 ndb:bg-zinc-950/30 ndb:backdrop-blur-[1px] ndb:dark:bg-black/55"
    ></div>

    <aside
        x-show.important="barVisible && inspectorOpen"
        x-transition:enter="ndb:transition ndb:duration-200 ndb:ease-out ndb:motion-reduce:transition-none"
        x-transition:enter-start="ndb-inspector-offscreen"
        x-transition:enter-end="ndb-inspector-onscreen"
        x-transition:leave="ndb:transition ndb:duration-150 ndb:ease-in ndb:motion-reduce:transition-none"
        x-transition:leave-start="ndb-inspector-onscreen"
        x-transition:leave-end="ndb-inspector-offscreen"
        :data-ndb-placement="toolbarVerticalPlacement"
        :class="toolbarIsTop
            ? 'ndb:top-0 ndb:rounded-b-2xl ndb:border-x ndb:border-b ndb:shadow-[0_24px_80px_-28px_rgba(24,24,27,0.5)]'
            : 'ndb:bottom-0 ndb:rounded-t-2xl ndb:border-x ndb:border-t ndb:shadow-[0_-24px_80px_-28px_rgba(24,24,27,0.5)]'"
        role="dialog"
        aria-modal="true"
        aria-label="Request inspector"
        @keydown="keepFocusWithin($event, mobileSectionsOpen ? $refs.mobileSectionsNav : $el)"
        class="ndb-inspector-panel ndb:absolute ndb:inset-x-0 ndb:mx-auto ndb:flex ndb:h-[min(82vh,780px)] ndb:w-full ndb:max-w-8xl ndb:min-[1560px]:max-w-[calc(100%-24px)] ndb:max-h-[calc(100vh-12px)] ndb:flex-col ndb:overflow-hidden ndb:border-white/70 ndb:bg-white/90 ndb:backdrop-blur-2xl ndb:dark:border-zinc-800/80 ndb:dark:bg-zinc-950/90"
    >
        @include('newdebugbar::livewire.inspector-header')

        <div class="ndb:relative ndb:isolate ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col ndb:sm:flex-row">
            <div
                x-cloak
                x-show.important="mobileSectionsOpen"
                x-transition.opacity.duration.150ms
                data-ndb-mobile-sections-backdrop
                @click="closeMobileSections()"
                class="ndb:absolute ndb:inset-y-0 ndb:right-0 ndb:left-[min(82vw,280px)] ndb:z-20 ndb:bg-transparent ndb:sm:hidden"
                aria-hidden="true"
            ></div>

            <nav
                id="newdebugbar-section-navigation"
                x-ref="mobileSectionsNav"
                aria-label="Debug sections"
                :data-ndb-mobile-open="mobileSectionsOpen ? 'true' : 'false'"
                class="ndb-mobile-section-navigation ndb:absolute ndb:inset-y-0 ndb:left-0 ndb:z-30 ndb:flex ndb:w-[82vw] ndb:max-w-[280px] ndb:flex-col ndb:border-r ndb:border-zinc-200/80 ndb:bg-zinc-50/95 ndb:p-3 ndb:shadow-2xl ndb:backdrop-blur-2xl ndb:sm:static ndb:sm:z-auto ndb:sm:w-[210px] ndb:sm:max-w-none ndb:sm:shrink-0 ndb:sm:shadow-none ndb:dark:border-zinc-800/80 ndb:dark:bg-zinc-900/95 ndb:sm:dark:bg-zinc-900/60"
            >
                <div
                    id="newdebugbar-section-list"
                    class="ndb-scrollbar ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col ndb:gap-0.5 ndb:overflow-y-auto"
                >
                    <p
                        data-ndb-favorites-heading
                        x-show.important="favorites.length > 0"
                        class="ndb:px-2 ndb:pb-1.5 ndb:pt-1 ndb:text-[11px] ndb:font-bold ndb:uppercase ndb:tracking-[0.14em] ndb:text-zinc-400"
                    >
                        Favorites
                    </p>
                    <template x-for="section in orderedSections" :key="'section-' + section.key">
                        <div x-show.important="isSectionVisible(section)" class="ndb:contents">
                            <div
                                x-show.important="favorites.length > 0 && section.key === firstVisibleNonFavoriteKey"
                                class="ndb:my-2 ndb:h-px ndb:bg-zinc-200 ndb:dark:bg-zinc-800"
                            ></div>
                            <p
                                data-ndb-sections-heading
                                x-show.important="favorites.length > 0 && section.key === firstVisibleNonFavoriteKey"
                                class="ndb:px-2 ndb:pb-1.5 ndb:pt-1 ndb:text-[11px] ndb:font-bold ndb:uppercase ndb:tracking-[0.14em] ndb:text-zinc-400"
                            >
                                Sections
                            </p>
                            <div
                                :draggable="isFavorite(section.key)"
                                :data-ndb-section="section.key"
                                :data-ndb-section-visible="isSectionVisible(section) ? 'true' : 'false'"
                                :data-ndb-favorite="isFavorite(section.key) ? 'true' : 'false'"
                                data-ndb-dragging="false"
                                @dragstart="startFavoriteDrag(section.key, $event)"
                                @dragover.prevent="
                                    hoverFavorite(
                                        section.key,
                                        $event.clientY >
                                            $event.currentTarget.getBoundingClientRect().top +
                                                $event.currentTarget.offsetHeight / 2,
                                    )
                                "
                                @dragleave="leaveFavorite(section.key)"
                                @drop.prevent="dropFavorite(section.key, favoriteDropAfter)"
                                @dragend="endFavoriteDrag()"
                                class="ndb:group ndb:relative ndb:flex ndb:w-full ndb:items-center ndb:rounded-lg ndb:pr-1 ndb:transition ndb:hover:bg-zinc-200/60 ndb:dark:hover:bg-zinc-800/60"
                                :class="selected === section.key ? 'ndb-section-active' : ''"
                            >
                                <span
                                    :data-ndb-favorite-drop-before="section.key"
                                    hidden
                                    class="ndb:absolute ndb:inset-x-0.5 ndb:top-0 ndb:z-20 ndb:h-1 ndb:-translate-y-1/2 ndb:rounded-full ndb:bg-indigo-500 ndb:shadow-[0_0_0_2px_rgba(255,255,255,0.8)] ndb:dark:shadow-[0_0_0_2px_rgba(9,9,11,0.9)]"
                                ></span>
                                <span
                                    :data-ndb-favorite-drop-after="section.key"
                                    hidden
                                    class="ndb:absolute ndb:inset-x-0.5 ndb:bottom-0 ndb:z-20 ndb:h-1 ndb:translate-y-1/2 ndb:rounded-full ndb:bg-indigo-500 ndb:shadow-[0_0_0_2px_rgba(255,255,255,0.8)] ndb:dark:shadow-[0_0_0_2px_rgba(9,9,11,0.9)]"
                                ></span>
                                <button
                                    type="button"
                                    :data-ndb-select-section="section.key"
                                    :aria-current="selected === section.key ? 'page' : null"
                                    :aria-label="isFavorite(section.key)
                                        ? section.label + '. Drag to reorder. Shift and arrow keys also reorder.'
                                        : section.label"
                                    @click="selectSection(section.key)"
                                    @keydown.shift.arrow-up.prevent="moveFavorite(section.key, -1)"
                                    @keydown.shift.arrow-down.prevent="moveFavorite(section.key, 1)"
                                    class="ndb:flex ndb:h-9 ndb:min-w-0 ndb:flex-1 ndb:items-center ndb:gap-2 ndb:rounded-lg ndb:px-2.5 ndb:text-left ndb:text-xs ndb:font-semibold ndb:transition ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
                                    :class="(isFavorite(section.key)
                                        ? 'ndb:cursor-grab ndb:active:cursor-grabbing '
                                        : '') +
                                    (selected === section.key
                                        ? ''
                                        : 'ndb:text-zinc-600 ndb:hover:text-zinc-950 ndb:dark:text-zinc-400 ndb:dark:hover:text-white')"
                                >
                                    <span class="ndb-section-label ndb:truncate" x-text="section.label"></span>
                                    <span class="ndb:ml-auto ndb:flex ndb:h-7 ndb:shrink-0 ndb:items-center ndb:gap-1.5">
                                        <span
                                            x-show.important="section.count !== null"
                                            class="ndb-section-count ndb:inline-flex ndb:items-center ndb:text-[11px] ndb:leading-none ndb:tabular-nums"
                                            :class="selected === section.key ? '' : 'ndb:text-zinc-400'"
                                            x-text="section.count"
                                        ></span>
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    draggable="false"
                                    :data-ndb-toggle-favorite="section.key"
                                    :aria-label="(isFavorite(section.key) ? 'Remove ' : 'Add ') +
                                    section.label +
                                    (isFavorite(section.key) ? ' from favorites' : ' to favorites')"
                                    :aria-pressed="isFavorite(section.key)"
                                    :title="isFavorite(section.key) ? 'Remove from favorites' : 'Add to favorites'"
                                    @dragstart.prevent
                                    @click.stop="toggleFavorite(section.key)"
                                    class="ndb-star-button ndb:inline-flex ndb:size-7 ndb:items-center ndb:justify-center ndb:rounded-lg ndb:text-zinc-400 ndb:transition ndb:hover:scale-105 ndb:hover:text-blue-600 ndb:focus-visible:opacity-100 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-1 ndb:focus-visible:outline-blue-500 ndb:sm:opacity-0 ndb:sm:group-focus-within:opacity-100 ndb:sm:group-hover:opacity-100 ndb:dark:text-zinc-500 ndb:dark:hover:text-blue-300"
                                    :class="isFavorite(section.key) || selected === section.key
                                        ? 'ndb:sm:opacity-100'
                                        : ''"
                                >
                                    <span
                                        x-show.important="! isFavorite(section.key)"
                                        class="ndb-section-star-outline ndb:flex ndb:items-center ndb:justify-center ndb:leading-none"
                                        ><x-newdebugbar::icon name="star" class="ndb:size-3.5"
                                    /></span>
                                    <span
                                        x-show.important="isFavorite(section.key)"
                                        class="ndb:flex ndb:items-center ndb:justify-center ndb:leading-none"
                                        ><x-newdebugbar::icon name="star-filled" class="ndb-favorite-star ndb:size-3.5"
                                    /></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </nav>

            <div
                data-ndb-inspector-content
                x-ref="content"
                :inert="mobileSectionsOpen"
                class="ndb-scrollbar ndb:min-w-0 ndb:flex-1 ndb:overflow-y-auto ndb:bg-white/70 ndb:lg:flex ndb:lg:flex-col ndb:dark:bg-zinc-950/70"
            >
                <x-newdebugbar::section-heading>
                    <x-slot:heading
                        data-ndb-section-heading
                        x-ref="sectionHeading"
                        tabindex="-1"
                        aria-describedby="newdebugbar-section-description"
                        x-text="selectedSection.label"
                    ></x-slot:heading>
                    <x-slot:description
                        id="newdebugbar-section-description"
                        data-ndb-section-description
                        x-ref="sectionDescription"
                        x-text="selectedSection.description"
                    ></x-slot:description>
                </x-newdebugbar::section-heading>

                <div
                    data-ndb-section-stage
                    :aria-busy="sectionLoading ? 'true' : 'false'"
                    class="ndb:relative ndb:min-h-64 ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
                >
                    <div
                        x-cloak
                        x-show.important="sectionLoadingIndicator"
                        x-transition:enter="ndb:transition-opacity ndb:duration-150 ndb:ease-out ndb:motion-reduce:transition-none"
                        x-transition:enter-start="ndb:opacity-0"
                        x-transition:enter-end="ndb:opacity-100"
                        x-transition:leave="ndb:transition-opacity ndb:duration-100 ndb:ease-in ndb:motion-reduce:transition-none"
                        x-transition:leave-start="ndb:opacity-100"
                        x-transition:leave-end="ndb:opacity-0"
                        data-ndb-section-loading
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                        class="ndb:absolute ndb:inset-0 ndb:z-10 ndb:flex ndb:min-h-64 ndb:items-start ndb:justify-center ndb:bg-white/85 ndb:p-4 ndb:backdrop-blur-[1px] ndb:dark:bg-zinc-950/85 ndb:sm:p-6"
                    >
                        <div class="ndb:flex ndb:items-center ndb:gap-3 ndb:rounded-xl ndb:border ndb:border-zinc-200 ndb:bg-white/90 ndb:px-4 ndb:py-3 ndb:shadow-sm ndb:dark:border-zinc-800 ndb:dark:bg-zinc-900/90">
                            <span
                                class="ndb-loading-pulse ndb:grid ndb:size-8 ndb:shrink-0 ndb:place-items-center ndb:rounded-lg ndb:bg-indigo-50 ndb:text-indigo-600 ndb:dark:bg-indigo-950 ndb:dark:text-indigo-300"
                                ><x-newdebugbar::icon name="clock" class="ndb:size-4" /></span
                            ><span class="ndb:text-sm ndb:font-semibold"
                                >Loading <span x-text="selectedSection.label.toLowerCase()"></span>…</span>
                        </div>
                    </div>

                    <div
                        data-ndb-section-content
                        :class="sectionTransitioning ? 'ndb:opacity-0' : 'ndb:opacity-100'"
                        class="ndb:transition-opacity ndb:duration-150 ndb:ease-out ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col ndb:motion-reduce:transition-none"
                    >
                        <div
                            x-cloak
                            x-show.important="sectionError"
                            role="alert"
                            class="ndb:m-4 ndb:rounded-xl ndb:border ndb:border-red-200 ndb:bg-red-50/70 ndb:p-4 ndb:dark:border-red-950 ndb:dark:bg-red-950/25 ndb:sm:m-6"
                        >
                            <p class="ndb:text-sm ndb:font-bold ndb:text-red-800 ndb:dark:text-red-200">
                                Collector details could not be loaded.
                            </p>
                            <p class="ndb:mt-1 ndb:text-xs ndb:text-red-700/80 ndb:dark:text-red-300/80">
                                The request summary is still available. Retry or reload the page to capture a new
                                request.
                            </p>
                            <div class="ndb:mt-3 ndb:flex ndb:flex-wrap ndb:gap-2">
                                <button
                                    type="button"
                                    @click="requestSection(selected, true)"
                                    class="ndb:rounded-lg ndb:bg-red-700 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-bold ndb:text-white ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-red-500 ndb:dark:bg-red-300 ndb:dark:text-red-950"
                                >
                                    Retry section</button
                                ><button
                                    type="button"
                                    @click="window.location.reload()"
                                    class="ndb:rounded-lg ndb:border ndb:border-red-300 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-bold ndb:text-red-800 ndb:focus-visible:outline-2 ndb:focus-visible:outline-red-500 ndb:dark:border-red-900 ndb:dark:text-red-200"
                                >
                                    Reload page
                                </button>
                            </div>
                        </div>

                        @island(name: 'section-details', skip: true, always: true)
                            @placeholder
                                <span data-ndb-section-placeholder hidden></span>
                            @endplaceholder

                            @php($profile = $this->profile)
                            @php($sectionKey = $selectedSection)
                            @php($section = $profile['sections'][$sectionKey] ?? null)
                            @include('newdebugbar::livewire.section-panel')
                        @endisland
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>

{{-- Renders the expanded inspector shell, navigation, and inspector content. --}}
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
        @keydown="keepFocusWithin($event, $el)"
        class="ndb-inspector-panel ndb:absolute ndb:inset-x-0 ndb:mx-auto ndb:flex ndb:h-[min(82vh,780px)] ndb:w-full ndb:max-w-8xl ndb:max-h-[calc(100vh-12px)] ndb:flex-col ndb:overflow-hidden ndb:border-white/70 ndb:bg-white/90 ndb:backdrop-blur-2xl ndb:dark:border-zinc-800/80 ndb:dark:bg-zinc-950/90"
    >
        @include('newdebugbar::livewire.inspector-header')

        <div
            x-cloak
            x-show.important="backgroundActivityError !== null"
            data-ndb-background-activity-error
            role="status"
            aria-live="polite"
            class="ndb:flex ndb:w-full ndb:min-w-0 ndb:shrink-0 ndb:items-center ndb:justify-between ndb:gap-3 ndb:border-b ndb:border-amber-200 ndb:bg-amber-50 ndb:px-3 ndb:py-2 ndb:text-xs ndb:text-amber-900 ndb:sm:px-6 ndb:dark:border-amber-900 ndb:dark:bg-amber-950 ndb:dark:text-amber-200"
        >
            <span x-text="backgroundActivityError" class="ndb:min-w-0"></span>
            <x-newdebugbar::inspector-action
                icon="activity"
                @click="refreshBackgroundActivity(true)"
                x-bind:disabled="activityRefreshPending"
                class="ndb:shrink-0"
            >Retry now</x-newdebugbar::inspector-action>
        </div>

        <div class="ndb:relative ndb:isolate ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col ndb:sm:flex-row">
            <nav
                id="newdebugbar-inspector-navigation"
                aria-label="Debug inspectors"
                class="ndb:hidden ndb:w-[210px] ndb:shrink-0 ndb:flex-col ndb:border-r ndb:border-zinc-200/80 ndb:bg-zinc-50/95 ndb:p-3 ndb:sm:flex ndb:dark:border-zinc-800/80 ndb:dark:bg-zinc-900/60"
            >
                <template x-if="! mobileToolbarMenu">
                    <x-newdebugbar::inspector-navigation
                        id="newdebugbar-inspector-list"
                        class="ndb-scrollbar ndb:overflow-y-auto"
                    />
                </template>
            </nav>

            <div
                data-ndb-inspector-content
                x-ref="content"
                class="ndb-scrollbar ndb:min-w-0 ndb:flex-1 ndb:overflow-y-auto ndb:bg-white/70 ndb:lg:flex ndb:lg:flex-col ndb:dark:bg-zinc-950/70"
            >
                <x-newdebugbar::inspector-heading>
                    <x-slot:heading
                        data-ndb-inspector-heading
                        x-ref="inspectorHeading"
                        tabindex="-1"
                        x-bind:aria-describedby="selected === 'request' ? null : 'newdebugbar-inspector-description'"
                        x-text="selectedInspector.label"
                    ></x-slot:heading>
                    <x-slot:description
                        id="newdebugbar-inspector-description"
                        data-ndb-inspector-description
                        x-ref="inspectorDescription"
                        x-show.important="selected !== 'request'"
                        x-text="selectedInspector.description"
                    ></x-slot:description>
                </x-newdebugbar::inspector-heading>

                <div
                    data-ndb-inspector-stage
                    :aria-busy="inspectorLoading ? 'true' : 'false'"
                    class="ndb:relative ndb:min-h-64 ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
                >
                    <div
                        x-cloak
                        x-show.important="inspectorLoadingIndicator"
                        x-transition:enter="ndb:transition-opacity ndb:duration-150 ndb:ease-out ndb:motion-reduce:transition-none"
                        x-transition:enter-start="ndb:opacity-0"
                        x-transition:enter-end="ndb:opacity-100"
                        x-transition:leave="ndb:transition-opacity ndb:duration-100 ndb:ease-in ndb:motion-reduce:transition-none"
                        x-transition:leave-start="ndb:opacity-100"
                        x-transition:leave-end="ndb:opacity-0"
                        data-ndb-inspector-loading
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
                                >Loading <span x-text="selectedInspector.label.toLowerCase()"></span>…</span>
                        </div>
                    </div>

                    <div
                        data-ndb-inspector-body
                        :class="inspectorTransitioning ? 'ndb:opacity-0' : 'ndb:opacity-100'"
                        class="ndb:transition-opacity ndb:duration-150 ndb:ease-out ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col ndb:motion-reduce:transition-none"
                    >
                        <div
                            x-cloak
                            x-show.important="inspectorError"
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
                                    @click="requestInspector(selected, true)"
                                    class="ndb:rounded-lg ndb:bg-red-700 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-bold ndb:text-white ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-red-500 ndb:dark:bg-red-300 ndb:dark:text-red-950"
                                >
                                    Retry inspector</button
                                ><button
                                    type="button"
                                    @click="window.location.reload()"
                                    class="ndb:rounded-lg ndb:border ndb:border-red-300 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-bold ndb:text-red-800 ndb:focus-visible:outline-2 ndb:focus-visible:outline-red-500 ndb:dark:border-red-900 ndb:dark:text-red-200"
                                >
                                    Reload page
                                </button>
                            </div>
                        </div>

                        @island(name: 'inspector-details', skip: true, always: true)
                            @placeholder
                                <span data-ndb-inspector-placeholder hidden></span>
                            @endplaceholder

                            @php($profile = $this->profile)
                            @php($inspectorKey = $selectedInspector)
                            @php($inspector = $profile['inspectors'][$inspectorKey] ?? null)
                            @include('newdebugbar::livewire.inspector-panel')
                        @endisland
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>

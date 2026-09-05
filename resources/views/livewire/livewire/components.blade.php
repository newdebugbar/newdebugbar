<div data-ndb-livewire-component-list class="ndb:divide-y ndb:divide-zinc-200/80 ndb:dark:divide-zinc-800">
    <template x-for="component in filteredLivewireComponents" :key="component.id">
        <div
            data-ndb-livewire-component-row
            :data-ndb-livewire-component-id="component.id"
            :data-ndb-livewire-component-depth="component.depth"
            :class="livewireSelectedComponentId === component.id
                ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                : livewireComponentIsSearchContext(component)
                  ? 'ndb:bg-zinc-50/50 ndb:text-zinc-500 ndb:dark:bg-zinc-900/35 ndb:dark:text-zinc-400'
                  : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
            class="ndb:flex ndb:w-full ndb:min-w-0 ndb:items-stretch ndb:pr-3 ndb:transition-colors"
            :style="`padding-left: ${12 + component.depth * 18}px`"
        >
            <button
                x-show.important="component.hasChildren"
                data-ndb-livewire-component-toggle
                type="button"
                @click.stop="toggleLivewireComponent(component)"
                :aria-expanded="! livewireComponentCollapsed(component)"
                :aria-label="`${livewireComponentCollapsed(component) ? 'Expand' : 'Collapse'} ${component.title}`"
                class="ndb:my-auto ndb:grid ndb:size-5 ndb:shrink-0 ndb:place-items-center ndb:rounded ndb:text-zinc-400 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:hover:text-zinc-700 ndb:dark:hover:text-zinc-200"
            >
                <x-newdebugbar::icon
                    name="chevron-down"
                    size="3"
                    class="ndb:transition"
                    ::class="livewireComponentCollapsed(component) ? 'ndb:-rotate-90' : ''"
                />
            </button>
            <span x-show.important="! component.hasChildren" aria-hidden="true" class="ndb:w-5 ndb:shrink-0"></span>

            <button
                data-ndb-livewire-component-select
                type="button"
                @click="selectLivewireComponent(component.id)"
                :aria-pressed="livewireSelectedComponentId === component.id"
                class="ndb:grid ndb:h-auto ndb:min-w-0 ndb:flex-1 ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-center ndb:gap-3 ndb:px-2 ndb:py-2.5 ndb:text-left ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
            >
                <span class="ndb:min-w-0">
                    <span
                        data-ndb-livewire-component-title
                        class="ndb:block ndb:truncate ndb:text-xs ndb:font-bold"
                        x-text="component.title"
                    ></span>
                    <span class="ndb:mt-0.5 ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:items-baseline ndb:gap-x-2 ndb:text-xs ndb:font-medium ndb:text-zinc-400">
                        <span
                            data-ndb-livewire-component-property-count
                            x-text="livewireComponentPropertyCountLabel(component)"
                        ></span>
                        <span x-show.important="livewireComponentIsSearchContext(component)">Parent component</span>
                    </span>
                </span>
                <span
                    x-show.important="component.status !== 'idle'"
                    class="ndb:text-xs ndb:font-bold"
                    :class="component.status === 'failed'
                        ? 'ndb:text-red-600 ndb:dark:text-red-300'
                        : component.status === 'updating'
                          ? 'ndb:text-indigo-600 ndb:dark:text-indigo-300'
                          : 'ndb:text-zinc-400'"
                    x-text="component.status === 'stale' ? 'Server only' : component.status"
                ></span>
            </button>
        </div>
    </template>
</div>

<div x-show.important="filteredLivewireComponents.length === 0" class="ndb:p-3">
    <x-newdebugbar::empty-state label="No mounted components match this search." />
</div>

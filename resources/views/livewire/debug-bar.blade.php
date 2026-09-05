<div
    id="newdebugbar"
    wire:ignore.self
    x-data="newDebugBar(@js($summary), @js($profileLimit))"
    :data-ndb-theme="resolvedTheme"
    @keydown.window="handleShortcut($event)"
    @newdebugbar-content-updated.window="
        $nextTick(() => {
            syncSectionHeading();
            syncSectionPanels();
            applyViewFilters();
            applyAuthorizationFilters();
            applyTimelineFilters();
            applyEventFilters();
            applyLogFilters();
            syncHostLock();
            window.newDebugBarHighlight?.($root);
        })
    "
    @newdebugbar-profile-switched.window="switchProfile($event.detail.summary)"
    @newdebugbar-profile-noticed.window="receiveProfile($event.detail.summary)"
    @newdebugbar-profile-refreshed.window="receiveActivityRefresh($event.detail.summary, $event.detail.relatedProfiles)"
    @newdebugbar-section-loaded.window="receiveSection($event.detail.section)"
    class="ndb:pointer-events-none ndb:fixed ndb:inset-0 ndb:z-[2147483000] ndb:text-zinc-900 ndb:dark:text-zinc-100"
>
    <span id="newdebugbar-toolbar-drag-hint" class="ndb:sr-only"
        >Drag this toolbar to pin it to the top, bottom, or any corner. The command palette offers the same
        actions.</span>

    <x-newdebugbar::toolbar-anchor-preview placement="top-left" />
    <x-newdebugbar::toolbar-anchor-preview placement="top" />
    <x-newdebugbar::toolbar-anchor-preview placement="top-right" />
    <x-newdebugbar::toolbar-anchor-preview placement="bottom-left" />
    <x-newdebugbar::toolbar-anchor-preview placement="bottom" />
    <x-newdebugbar::toolbar-anchor-preview placement="bottom-right" />

    @include('newdebugbar::livewire.toolbar')

    @include('newdebugbar::livewire.inspector')

    <div
        x-cloak
        x-show.important="paletteOpen"
        class="ndb:pointer-events-auto ndb:fixed ndb:inset-0 ndb:z-50 ndb:grid ndb:justify-items-center ndb:bg-zinc-950/45 ndb:px-3 ndb:pt-[12vh] ndb:backdrop-blur-sm"
        @click.self="closePalette()"
    >
        <div
            x-show.important="paletteOpen"
            x-transition
            @keydown="keepFocusWithin($event, $el)"
            class="ndb:w-full ndb:max-w-xl ndb:self-start ndb:overflow-hidden ndb:rounded-2xl ndb:border ndb:border-white/70 ndb:bg-white/90 ndb:shadow-2xl ndb:backdrop-blur-2xl ndb:dark:border-zinc-700/80 ndb:dark:bg-zinc-900/90"
            role="dialog"
            aria-modal="true"
            aria-label="Command palette"
        >
            <div class="ndb:flex ndb:items-center ndb:gap-3 ndb:border-b ndb:border-zinc-200 ndb:px-4 ndb:dark:border-zinc-800">
                <x-newdebugbar::icon name="search" class="ndb:size-5 ndb:text-zinc-400" /><input
                    data-ndb-palette-search
                    x-ref="paletteSearch"
                    x-model="paletteSearch"
                    @input="paletteIndex = 0"
                    @keydown.down.prevent="movePalette(1)"
                    @keydown.up.prevent="movePalette(-1)"
                    @keydown.enter.prevent="runActiveCommand()"
                    type="search"
                    placeholder="Jump to a section or change a setting…"
                    class="ndb:h-14 ndb:min-w-0 ndb:flex-1 ndb:border-0 ndb:bg-transparent ndb:text-sm ndb:font-medium ndb:outline-none ndb:placeholder:text-zinc-400"
                /><kbd
                    class="ndb:rounded-md ndb:border ndb:border-zinc-200 ndb:bg-zinc-50 ndb:px-1.5 ndb:py-1 ndb:text-xs ndb:font-bold ndb:text-zinc-400 ndb:dark:border-zinc-700 ndb:dark:bg-zinc-800"
                    >ESC</kbd>
            </div>
            <div class="ndb-scrollbar ndb:max-h-[min(420px,60vh)] ndb:overflow-y-auto ndb:p-2">
                <template x-for="command in allCommands" :key="command.id">
                    <button
                        x-show.important="commandIndex(command.id) !== -1"
                        type="button"
                        :data-ndb-command="command.id"
                        @mouseenter="paletteIndex = commandIndex(command.id)"
                        @click="runCommand(command.id)"
                        class="ndb:flex ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition"
                        :class="filteredCommands[paletteIndex]?.id === command.id
                            ? 'ndb:bg-blue-100/60 ndb:text-blue-700 ndb:dark:bg-indigo-950/60 ndb:dark:text-indigo-200'
                            : 'ndb:text-zinc-700 ndb:dark:text-zinc-300'"
                    >
                        <span class="ndb:flex-1 ndb:text-sm ndb:font-semibold" x-text="command.label"></span
                        ><span
                            class="ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                            x-text="command.hint"
                        ></span>
                    </button>
                </template>
                <button
                    x-show.important="commandIndex('collectors:show') !== -1"
                    type="button"
                    data-ndb-command="collectors:show"
                    @mouseenter="paletteIndex = commandIndex('collectors:show')"
                    @click="runCommand('collectors:show')"
                    class="ndb:flex ndb:w-full ndb:items-center ndb:gap-3 ndb:rounded-lg ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition"
                    :class="filteredCommands[paletteIndex]?.id === 'collectors:show'
                        ? 'ndb:bg-blue-100/60 ndb:text-blue-700 ndb:dark:bg-indigo-950/60 ndb:dark:text-indigo-200'
                        : 'ndb:text-zinc-700 ndb:dark:text-zinc-300'"
                >
                    <span class="ndb:flex-1 ndb:text-sm ndb:font-semibold">Show other collectors</span
                    ><span
                        class="ndb:text-xs ndb:font-bold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                        x-text="`${hiddenCommandCount} hidden`"
                    ></span>
                </button>
                <p
                    x-show.important="filteredCommands.length === 0"
                    class="ndb:px-3 ndb:py-8 ndb:text-center ndb:text-sm ndb:text-zinc-500"
                >
                    No matching commands.
                </p>
            </div>
            <div class="ndb:flex ndb:items-center ndb:gap-3 ndb:border-t ndb:border-zinc-200 ndb:bg-zinc-50 ndb:px-4 ndb:py-2 ndb:text-xs ndb:font-medium ndb:text-zinc-400 ndb:dark:border-zinc-800 ndb:dark:bg-zinc-950">
                <span>↑↓ Navigate</span><span>↵ Select</span><span class="ndb:ml-auto">⌘/Ctrl ⇧ P</span>
            </div>
        </div>
    </div>
</div>

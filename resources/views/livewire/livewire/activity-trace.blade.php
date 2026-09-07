<section
    data-ndb-livewire-trace
    x-data="livewireTraceHelp()"
    x-id="['newdebugbar-livewire-phase-trigger', 'newdebugbar-livewire-phase-help']"
    x-init="
        $watch('livewireSelectedActivityId', () => closePhaseHelp());
        $watch('livewireDetailOpen', () => closePhaseHelp());
    "
    x-effect="if (paletteOpen || selected !== 'livewire' || ! inspectorOpen || ! barVisible) closePhaseHelp();"
    @keydown.escape.window.capture="
        if (phaseHelpIndex !== null) {
            $event.preventDefault();
            $event.stopPropagation();
            closePhaseHelp();
        }
    "
>
    <ol aria-label="Livewire update timeline" class="ndb:m-0 ndb:list-none ndb:p-0">
        <template
            x-for="(group, groupIndex) in livewireActivityPhaseGroups(selectedLivewireActivity)"
            :key="`${selectedLivewireActivity.id}-${group.start}`"
        >
            <li
                :data-ndb-livewire-phase-group="group.kind"
                class="ndb:grid ndb:grid-cols-[2rem_minmax(0,1fr)] ndb:gap-x-3 ndb:border-0 ndb:bg-transparent ndb:p-0 ndb:text-sm ndb:text-zinc-900 ndb:dark:text-zinc-100"
                :class="groupIndex < livewireActivityPhaseGroups(selectedLivewireActivity).length - 1
                    ? 'ndb:pb-8 ndb:sm:pb-10'
                    : ''"
            >
                <div aria-hidden="true" class="ndb:relative ndb:row-span-2">
                    <span
                        data-ndb-livewire-phase-connector
                        x-show.important="groupIndex < livewireActivityPhaseGroups(selectedLivewireActivity).length - 1"
                        class="ndb:absolute ndb:top-4 ndb:-bottom-12 ndb:left-1/2 ndb:w-px ndb:-translate-x-1/2 ndb:border-0 ndb:bg-zinc-200 ndb:p-0 ndb:sm:-bottom-14 ndb:dark:bg-zinc-800"
                    ></span>
                    <span
                        data-ndb-livewire-phase-node
                        class="ndb:relative ndb:z-10 ndb:grid ndb:size-8 ndb:place-items-center ndb:rounded-full ndb:border-0 ndb:bg-zinc-100 ndb:p-0 ndb:text-zinc-500 ndb:ring-4 ndb:ring-white ndb:dark:bg-zinc-900 ndb:dark:text-zinc-400 ndb:dark:ring-zinc-950"
                    >
                        <x-newdebugbar::icon name="monitor" size="4" x-show.important="group.kind !== 'server'" />
                        <x-newdebugbar::icon name="server" size="4" x-show.important="group.kind === 'server'" />
                    </span>
                </div>
                <div class="ndb:flex ndb:min-h-8 ndb:items-center">
                    <h4 class="ndb:text-sm ndb:font-semibold ndb:leading-5" x-text="group.label"></h4>
                </div>
                <div class="ndb:col-start-2 ndb:mt-1">
                    <p
                        x-show.important="group.kind === 'server'"
                        class="ndb:max-w-sm ndb:text-sm ndb:leading-6 ndb:text-zinc-600 ndb:dark:text-zinc-300"
                    >
                        Processes the update and builds the response.
                    </p>
                    <ol x-show.important="group.steps.length > 0" class="ndb:m-0 ndb:list-none ndb:p-0">
                        <template
                            x-for="{ phase, index } in group.steps"
                            :key="`${selectedLivewireActivity.id}-${index}`"
                        >
                            <li
                                :data-ndb-livewire-phase="phase.name"
                                class="ndb:m-0 ndb:border-0 ndb:bg-transparent ndb:px-0 ndb:py-2 ndb:text-sm ndb:text-zinc-600 ndb:dark:text-zinc-300"
                            >
                                <div class="ndb:flex ndb:min-w-0 ndb:items-center ndb:gap-1">
                                    <span
                                        data-ndb-livewire-phase-name
                                        class="ndb:min-w-0 ndb:text-sm ndb:leading-5"
                                        x-text="livewirePhaseLabel(phase.name)"
                                    ></span>
                                    <x-newdebugbar::icon-button
                                        data-ndb-livewire-phase-trigger
                                        ::id="$id('newdebugbar-livewire-phase-trigger', index)"
                                        ::aria-label="`Explain ${livewirePhaseLabel(phase.name)}`"
                                        ::aria-expanded="phaseHelpIndex === index"
                                        ::aria-describedby="phaseHelpIndex === index ? $id('newdebugbar-livewire-phase-help') : null"
                                        @pointerenter="if ($event.pointerType !== 'touch') showPhaseHelp(index, $el);"
                                        @pointerleave="leavePhaseHelp()"
                                        @focus="showPhaseHelp(index, $el)"
                                        @blur="leavePhaseHelp()"
                                        @keydown.tab="closePhaseHelp()"
                                        @click.stop="togglePhaseHelp(index, $el)"
                                        class="ndb:-my-2.5 ndb:size-10 ndb:shrink-0 ndb:rounded-lg ndb:sm:-my-1 ndb:sm:size-7"
                                        :color-only="true"
                                    >
                                        <x-newdebugbar::icon name="info" size="3.5" />
                                    </x-newdebugbar::icon-button>
                                </div>
                                <span
                                    data-ndb-livewire-phase-time
                                    class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:leading-4 ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                    x-text="
                                        `+${formatDuration(Math.max(0, phase.at - selectedLivewireActivity.startedAt))}`
                                    "
                                ></span>
                            </li>
                        </template>
                    </ol>
                </div>
            </li>
        </template>
    </ol>

    <template x-if="phaseHelpIndex !== null">
        <template x-teleport="#newdebugbar">
            {{-- Include the hover gap in the anchored box so viewport-edge flipping accounts for it. --}}
            <x-newdebugbar::popover-surface
                :anchored="true"
                x-anchor.bottom-start.fixed="phaseHelpTrigger"
                data-ndb-livewire-phase-help
                ::id="$id('newdebugbar-livewire-phase-help')"
                ::style="{ visibility: $anchor.x !== 0 || $anchor.y !== 0 ? 'visible' : 'hidden' }"
                role="tooltip"
                @pointerenter="holdPhaseHelp()"
                @pointerleave="leavePhaseHelp()"
                @click.outside="closePhaseHelp()"
                width-class="ndb:w-[min(18rem,calc(100vw-2rem))]"
                surface-class="ndb:px-3 ndb:py-2.5"
                arrow-class="ndb:hidden"
                class="ndb:pointer-events-auto ndb:border-0 ndb:bg-transparent ndb:px-0 ndb:py-1 ndb:text-xs ndb:text-zinc-900 ndb:dark:text-zinc-100"
            >
                <p
                    class="ndb:text-xs ndb:leading-5"
                    x-text="livewirePhaseDescription(selectedLivewireActivity.phases[phaseHelpIndex]?.name)"
                ></p>
            </x-newdebugbar::popover-surface>
        </template>
    </template>
</section>

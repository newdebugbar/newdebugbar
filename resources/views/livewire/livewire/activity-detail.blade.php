<article data-ndb-livewire-activity-detail class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col">
    <x-newdebugbar::inspector-detail-header data-ndb-livewire-activity-header>
        <x-slot:title>
            <div class="ndb:min-w-0">
                <h3
                    class="ndb:min-w-0 ndb:break-words ndb:text-sm ndb:font-bold"
                    x-text="selectedLivewireActivity.title"
                ></h3>
                <p
                    class="ndb:mt-0.5 ndb:max-w-2xl ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                    x-text="livewireActivitySummary(selectedLivewireActivity)"
                ></p>
            </div>
        </x-slot:title>
        <x-slot:aside>
            <span
                data-ndb-livewire-activity-status
                x-show.important="selectedLivewireActivity.status !== 'complete'"
                class="ndb:inline-flex ndb:min-h-7 ndb:items-center ndb:justify-self-end ndb:rounded-md ndb:px-2 ndb:text-xs ndb:font-bold"
                :class="selectedLivewireActivity.status === 'failed' ||
                selectedLivewireActivity.status === 'failed_validation'
                    ? 'ndb:bg-red-50 ndb:text-red-700 ndb:dark:bg-red-950/60 ndb:dark:text-red-300'
                    : selectedLivewireActivity.status === 'updating'
                      ? 'ndb:bg-indigo-50 ndb:text-indigo-700 ndb:dark:bg-indigo-950/60 ndb:dark:text-indigo-300'
                      : 'ndb:bg-zinc-100 ndb:text-zinc-600 ndb:dark:bg-zinc-800 ndb:dark:text-zinc-300'"
                x-text="livewireActivityStatusLabel(selectedLivewireActivity)"
            ></span>
        </x-slot:aside>
    </x-newdebugbar::inspector-detail-header>

    <x-newdebugbar::inspector-detail-tabs
        label="Livewire activity detail"
        x-show.important="selectedLivewireActivity.phases.length > 0"
    >
        <x-newdebugbar::filter-tab
            variant="segmented"
            data-ndb-livewire-detail-tab="overview"
            @click="setLivewireDetailTab('overview')"
            ::aria-pressed="livewireDetailTab === 'overview'"
            class="ndb:h-auto"
        >
            Overview
        </x-newdebugbar::filter-tab>
        <x-newdebugbar::filter-tab
            variant="segmented"
            data-ndb-livewire-detail-tab="trace"
            @click="setLivewireDetailTab('trace')"
            ::aria-pressed="livewireDetailTab === 'trace'"
            class="ndb:h-auto"
        >
            Trace
        </x-newdebugbar::filter-tab>
    </x-newdebugbar::inspector-detail-tabs>

    <template x-if="livewireDetailTab === 'overview'">
        <div data-ndb-livewire-detail-panel="overview" class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-5 ndb:sm:p-4">
            <div x-show.important="selectedLivewireActivity.kind === 'mount'" data-ndb-livewire-mount-facts>
                <x-newdebugbar::inspector-facts columns="2">
                    <x-newdebugbar::inspector-fact label="Component">
                        <x-slot:value>
                            <button
                                type="button"
                                @click="inspectLivewireActivityComponent()"
                                class="ndb:max-w-full ndb:truncate ndb:bg-transparent ndb:p-0 ndb:text-left ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:underline ndb:decoration-zinc-300 ndb:underline-offset-2 ndb:hover:text-zinc-950 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:decoration-zinc-600 ndb:dark:hover:text-white"
                                x-text="livewireActivityComponentTitle(selectedLivewireActivity)"
                            ></button>
                        </x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Parent">
                        <x-slot:value
                            class="ndb:truncate ndb:font-semibold"
                            x-text="livewireActivityParentTitle(selectedLivewireActivity)"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Mounted at">
                        <x-slot:value
                            data-ndb-livewire-mount-time
                            class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                            x-text="livewireMountTime(selectedLivewireActivity)"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Initial render">
                        <x-slot:value
                            data-ndb-livewire-initial-render-duration
                            class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                            x-text="livewireInitialRenderDuration(selectedLivewireActivity)"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                </x-newdebugbar::inspector-facts>
            </div>

            <div x-show.important="selectedLivewireActivity.kind !== 'mount'">
                <x-newdebugbar::inspector-facts columns="4">
                    <x-newdebugbar::inspector-fact label="Component">
                        <x-slot:value>
                            <button
                                type="button"
                                @click="inspectLivewireActivityComponent()"
                                class="ndb:max-w-full ndb:truncate ndb:bg-transparent ndb:p-0 ndb:text-left ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:underline ndb:decoration-zinc-300 ndb:underline-offset-2 ndb:hover:text-zinc-950 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:decoration-zinc-600 ndb:dark:hover:text-white"
                                x-text="livewireActivityComponentTitle(selectedLivewireActivity)"
                            ></button>
                        </x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Type">
                        <x-slot:value
                            class="ndb:truncate ndb:font-semibold"
                            x-text="
                                selectedLivewireActivity.kind
                                    .replaceAll('_', ' ')
                                    .replace(/\b\w/g, (letter) => letter.toUpperCase())
                            "
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Happened">
                        <x-slot:value
                            class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                            x-text="livewireActivityAge(selectedLivewireActivity)"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Duration">
                        <x-slot:value
                            class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                            x-text="livewireDuration(selectedLivewireActivity)"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                </x-newdebugbar::inspector-facts>
            </div>

            <div
                x-show.important="selectedLivewireActivity.error"
                role="alert"
                class="ndb:rounded-lg ndb:border ndb:border-red-200 ndb:bg-red-50/70 ndb:px-3 ndb:py-2.5 ndb:text-xs ndb:font-semibold ndb:text-red-800 ndb:dark:border-red-950 ndb:dark:bg-red-950/30 ndb:dark:text-red-200"
                x-text="selectedLivewireActivity.error"
            ></div>

            <div
                x-show.important="
                    livewireActivitySourceLabel(selectedLivewireActivity) ||
                    livewireActivityProfileIds(selectedLivewireActivity).length > 0
                "
            >
                <h4 class="ndb:mb-3 ndb:text-xs ndb:font-bold">Evidence</h4>
                <x-newdebugbar::inspector-facts columns="2">
                    <x-newdebugbar::inspector-fact
                        label="Source"
                        x-show.important="livewireActivitySourceLabel(selectedLivewireActivity)"
                    >
                        <x-slot:value>
                            <x-newdebugbar::inspector-source-link
                                ::title="livewireActivitySourceLabel(selectedLivewireActivity)"
                                @click="copyText(livewireActivitySourceLabel(selectedLivewireActivity))"
                            >
                                <x-slot:value x-text="livewireActivitySourceLabel(selectedLivewireActivity)"></x-slot:value>
                            </x-newdebugbar::inspector-source-link>
                        </x-slot:value>
                    </x-newdebugbar::inspector-fact>

                    <x-newdebugbar::inspector-fact
                        label="Request"
                        x-show.important="livewireActivityProfileIds(selectedLivewireActivity).length > 0"
                    >
                        <x-slot:value class="ndb:flex ndb:flex-wrap ndb:gap-1.5">
                            <template
                                x-for="(profileId, index) in livewireActivityProfileIds(selectedLivewireActivity)"
                                :key="profileId"
                            >
                                <x-newdebugbar::inspector-action
                                    icon="external-link"
                                    @click="openRelatedProfile(profileId, 'request')"
                                    ::aria-label="'Open related request ' + (index + 1)"
                                    class="ndb:min-h-0 ndb:bg-transparent ndb:px-0"
                                >
                                    <span
                                        x-text="
                                            livewireActivityProfileIds(selectedLivewireActivity).length === 1
                                                ? 'Open request'
                                                : `Open request ${index + 1}`
                                        "
                                    ></span>
                                </x-newdebugbar::inspector-action>
                            </template>
                        </x-slot:value>
                    </x-newdebugbar::inspector-fact>
                </x-newdebugbar::inspector-facts>
            </div>

            <section x-show.important="selectedLivewireActivity.changes.length > 0">
                <x-newdebugbar::inspector-explanation
                    title="Which properties changed?"
                    description="Before is the browser value at the start of the update. Sent is what the browser submitted. If Server is not confirmed or differs, inspect the trace before changing the property again."
                />

                <div class="ndb:mt-3 ndb:border-y ndb:border-zinc-200/90 ndb:dark:border-zinc-800">
                    <div class="ndb:hidden ndb:grid-cols-[minmax(8rem,1fr)_minmax(6rem,0.8fr)_minmax(6rem,0.8fr)_minmax(6rem,0.8fr)] ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:dark:border-zinc-800 ndb:sm:grid">
                        <span>Property</span><span>Before</span><span>Sent</span><span>Server</span>
                    </div>
                    <div class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                        <template x-for="change in selectedLivewireActivity.changes" :key="change.path">
                            <div class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:py-2.5 ndb:sm:grid-cols-[minmax(8rem,1fr)_minmax(6rem,0.8fr)_minmax(6rem,0.8fr)_minmax(6rem,0.8fr)] ndb:sm:items-center ndb:sm:gap-3">
                                <code
                                    class="ndb:min-w-0 ndb:break-all ndb:text-xs ndb:font-semibold"
                                    x-text="change.path"
                                ></code>
                                <p class="ndb:flex ndb:min-w-0 ndb:gap-2 ndb:text-xs ndb:sm:block">
                                    <span class="ndb:w-14 ndb:shrink-0 ndb:text-zinc-400 ndb:sm:hidden">Before</span>
                                    <code
                                        class="ndb:min-w-0 ndb:break-all"
                                        x-text="JSON.stringify(change.before)"
                                    ></code>
                                </p>
                                <p class="ndb:flex ndb:min-w-0 ndb:gap-2 ndb:text-xs ndb:sm:block">
                                    <span class="ndb:w-14 ndb:shrink-0 ndb:text-zinc-400 ndb:sm:hidden">Sent</span>
                                    <code
                                        class="ndb:min-w-0 ndb:break-all"
                                        x-text="JSON.stringify(change.submitted)"
                                    ></code>
                                </p>
                                <p class="ndb:flex ndb:min-w-0 ndb:gap-2 ndb:text-xs ndb:sm:block">
                                    <span class="ndb:w-14 ndb:shrink-0 ndb:text-zinc-400 ndb:sm:hidden">Server</span>
                                    <code
                                        class="ndb:min-w-0 ndb:break-all"
                                        x-text="change.serverKnown ? JSON.stringify(change.server) : 'Not confirmed'"
                                    ></code>
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <section x-show.important="livewireMeaningfulActions(selectedLivewireActivity).length > 0">
                <h4 class="ndb:text-xs ndb:font-bold">Server actions</h4>
                <div class="ndb:mt-2 ndb:space-y-2">
                    <template
                        x-for="(action, index) in livewireMeaningfulActions(selectedLivewireActivity)"
                        :key="`${action.name}-${index}`"
                    >
                        <div class="ndb:rounded-lg ndb:bg-zinc-50 ndb:px-3 ndb:py-2.5 ndb:dark:bg-zinc-900/65">
                            <code class="ndb:text-xs ndb:font-bold" x-text="action.name"></code>
                            <x-newdebugbar::code-block
                                language="json"
                                x-show.important="Object.keys(action.params ?? {}).length > 0"
                                class="ndb:mt-2"
                            >
                                <x-slot:value x-text="JSON.stringify(action.params, null, 2)"></x-slot:value>
                            </x-newdebugbar::code-block>
                        </div>
                    </template>
                </div>
            </section>

            <section x-show.important="livewireActivityEvents(selectedLivewireActivity).length > 0">
                <h4 class="ndb:text-xs ndb:font-bold">Events</h4>
                <div class="ndb:mt-2 ndb:space-y-2">
                    <template x-for="event in livewireActivityEvents(selectedLivewireActivity)" :key="event.name">
                        <div class="ndb:rounded-lg ndb:border ndb:border-zinc-200/90 ndb:px-3 ndb:py-3 ndb:dark:border-zinc-800">
                            <div class="ndb:flex ndb:flex-wrap ndb:items-center ndb:gap-2">
                                <code class="ndb:text-xs ndb:font-bold" x-text="event.name"></code>
                                <span
                                    class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                                    x-text="event.mode"
                                ></span>
                            </div>
                            <p
                                x-show.important="event.declaredTarget"
                                class="ndb:mt-2 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            >
                                Declared target <code x-text="event.declaredTarget"></code>
                            </p>
                            <div
                                x-show.important="event.observedRecipientIds.length > 0"
                                class="ndb:mt-2 ndb:flex ndb:flex-wrap ndb:items-center ndb:gap-1.5 ndb:text-xs"
                            >
                                <span class="ndb:text-zinc-400">Observed recipients</span>
                                <template x-for="recipient in event.observedRecipientIds" :key="recipient">
                                    <button
                                        type="button"
                                        @click="inspectLivewireComponent(recipient)"
                                        class="ndb:bg-transparent ndb:p-0 ndb:font-semibold ndb:text-zinc-700 ndb:underline ndb:decoration-zinc-300 ndb:underline-offset-2 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-200 ndb:dark:decoration-zinc-600"
                                        x-text="livewireComponentTitle(recipient)"
                                    ></button>
                                </template>
                            </div>
                            <x-newdebugbar::code-block
                                language="json"
                                x-show.important="Object.keys(event.params ?? {}).length > 0"
                                class="ndb:mt-2"
                            >
                                <x-slot:value x-text="JSON.stringify(event.params, null, 2)"></x-slot:value>
                            </x-newdebugbar::code-block>
                        </div>
                    </template>
                </div>
            </section>
        </div>
    </template>

    <template x-if="livewireDetailTab === 'trace' && selectedLivewireActivity.phases.length > 0">
        <div data-ndb-livewire-detail-panel="trace" class="ndb:p-3 ndb:sm:p-4">
            <x-newdebugbar::inspector-explanation
                title="Where did this update spend time?"
                description="Request steps cover the server round trip. Browser steps cover state sync and page rendering. If the update feels slow, inspect the largest gap between two steps."
            />

            <div class="ndb:mt-3 ndb:space-y-3 ndb:sm:mt-4 ndb:sm:space-y-5">
                <template x-for="group in livewireActivityPhaseGroups(selectedLivewireActivity)" :key="group.name">
                    <section>
                        <div class="ndb:flex ndb:items-center ndb:justify-between ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:pb-2 ndb:dark:border-zinc-800">
                            <h4 class="ndb:text-xs ndb:font-bold" x-text="group.name"></h4>
                            <span
                                class="ndb:text-xs ndb:font-medium ndb:tabular-nums ndb:text-zinc-400"
                                x-text="`${group.phases.length} ${group.phases.length === 1 ? 'step' : 'steps'}`"
                            ></span>
                        </div>
                        <ol class="ndb:m-0 ndb:list-none ndb:divide-y ndb:divide-zinc-200/90 ndb:p-0 ndb:dark:divide-zinc-800">
                            <template x-for="(phase, index) in group.phases" :key="`${phase.name}-${index}`">
                                <li class="ndb:grid ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-baseline ndb:gap-3 ndb:py-2.5 ndb:sm:py-3">
                                    <span class="ndb:min-w-0">
                                        <span
                                            class="ndb:block ndb:text-xs ndb:font-semibold"
                                            x-text="phase.name"
                                        ></span>
                                        <span
                                            class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-400"
                                            x-text="livewirePhaseDescription(phase.name)"
                                        ></span>
                                    </span>
                                    <span
                                        class="ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                        x-text="
                                            formatDuration(Math.max(0, phase.at - selectedLivewireActivity.startedAt))
                                        "
                                    ></span>
                                </li>
                            </template>
                        </ol>
                    </section>
                </template>
            </div>
        </div>
    </template>
</article>

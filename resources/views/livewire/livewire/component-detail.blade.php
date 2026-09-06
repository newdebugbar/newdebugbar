<article data-ndb-livewire-component-detail class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col">
    <x-newdebugbar::inspector-detail-header data-ndb-livewire-component-header>
        <x-slot:title>
            <div class="ndb:min-w-0">
                <h3
                    class="ndb:min-w-0 ndb:break-words ndb:text-sm ndb:font-bold"
                    x-text="selectedLivewireComponent.title"
                ></h3>
                <p
                    class="ndb:mt-0.5 ndb:truncate ndb:text-xs ndb:font-medium ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                    :title="selectedLivewireComponent.name"
                    x-text="selectedLivewireComponent.name"
                ></p>
            </div>
        </x-slot:title>
        <x-slot:aside>
            <x-newdebugbar::inspector-action
                icon="activity"
                data-ndb-livewire-view-activity
                @click="inspectLivewireComponentActivity()"
                ::disabled="! selectedLivewireComponent.latestActivityId"
                class="ndb:justify-self-end ndb:disabled:cursor-default ndb:disabled:opacity-40"
            >
                Latest activity
            </x-newdebugbar::inspector-action>
        </x-slot:aside>
    </x-newdebugbar::inspector-detail-header>

    <x-newdebugbar::inspector-detail-tabs label="Livewire component detail">
        <x-newdebugbar::filter-tab
            variant="segmented"
            data-ndb-livewire-detail-tab="properties"
            @click="setLivewireDetailTab('properties')"
            ::aria-pressed="livewireDetailTab === 'properties'"
            class="ndb:h-auto"
        >
            Properties
        </x-newdebugbar::filter-tab>
        <x-newdebugbar::filter-tab
            variant="segmented"
            data-ndb-livewire-detail-tab="source"
            @click="setLivewireDetailTab('source')"
            ::aria-pressed="livewireDetailTab === 'source'"
            class="ndb:h-auto"
        >
            Source
        </x-newdebugbar::filter-tab>
    </x-newdebugbar::inspector-detail-tabs>

    <template x-if="livewireDetailTab === 'properties'">
        <div data-ndb-livewire-detail-panel="properties" class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-5 ndb:sm:p-4">
            <x-newdebugbar::inspector-facts columns="4">
                <x-newdebugbar::inspector-fact label="State">
                    <x-slot:value
                        class="ndb:truncate ndb:font-semibold"
                        ::class="selectedLivewireComponent.status === 'failed'
                            ? 'ndb:text-red-700 ndb:dark:text-red-300'
                            : selectedLivewireComponent.status === 'updating'
                              ? 'ndb:text-indigo-700 ndb:dark:text-indigo-300'
                              : 'ndb:text-zinc-700 ndb:dark:text-zinc-200'"
                        ::title="livewireComponentStatusDescription(selectedLivewireComponent)"
                        x-text="
                            selectedLivewireComponent.status === 'stale'
                                ? 'Server only'
                                : selectedLivewireComponent.status.replace(/\b\w/g, (letter) => letter.toUpperCase())
                        "
                    ></x-slot:value>
                </x-newdebugbar::inspector-fact>
                <x-newdebugbar::inspector-fact label="Parent">
                    <x-slot:value
                        class="ndb:truncate ndb:font-semibold"
                        x-text="
                            selectedLivewireComponent.parentId
                                ? livewireComponentTitle(selectedLivewireComponent.parentId)
                                : 'Top level'
                        "
                    ></x-slot:value>
                </x-newdebugbar::inspector-fact>
                <x-newdebugbar::inspector-fact label="Properties">
                    <x-slot:value
                        data-ndb-livewire-component-property-count
                        class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                        x-text="livewireComponentPropertyCountLabel(selectedLivewireComponent)"
                    ></x-slot:value>
                </x-newdebugbar::inspector-fact>
                <x-newdebugbar::inspector-fact label="Changed / editable">
                    <x-slot:value
                        data-ndb-livewire-component-property-summary
                        class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                        x-text="livewireComponentPropertyStateSummary(selectedLivewireComponent)"
                    ></x-slot:value>
                </x-newdebugbar::inspector-fact>
            </x-newdebugbar::inspector-facts>

            <section>
                <x-newdebugbar::inspector-explanation
                    title="Which values differ from the server?"
                    description="Client is the value currently in the browser. Server is the latest confirmed value. If they differ unexpectedly, inspect recent activity before editing the property."
                />

                <div
                    x-show.important="livewirePropertyRows.length > 0"
                    data-ndb-livewire-property-table
                    class="ndb-scrollbar ndb:mt-3 ndb:overflow-x-auto ndb:border-y ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
                >
                    <div class="ndb:hidden ndb:grid-cols-[minmax(10rem,1fr)_minmax(7rem,0.8fr)_minmax(7rem,0.8fr)_5rem_3rem] ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:bg-zinc-50/75 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:sm:grid ndb:sm:min-w-[36rem] ndb:dark:border-zinc-800 ndb:dark:bg-zinc-900/55">
                        <span>Property</span><span>Client</span><span>Server</span><span>State</span><span></span>
                    </div>

                    <template x-for="row in livewirePropertyRows" :key="`${row.componentId}:${row.path}`">
                        <div
                            :data-ndb-livewire-property-path="row.path"
                            class="ndb:border-b ndb:border-zinc-200/80 ndb:last:border-b-0 ndb:sm:min-w-[36rem] ndb:dark:border-zinc-800"
                        >
                            <div class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:px-3 ndb:py-2.5 ndb:sm:grid-cols-[minmax(10rem,1fr)_minmax(7rem,0.8fr)_minmax(7rem,0.8fr)_5rem_3rem] ndb:sm:items-center ndb:sm:gap-3">
                                <div
                                    data-ndb-livewire-property-name
                                    class="ndb:flex ndb:min-w-0 ndb:items-center ndb:gap-1.5"
                                    :style="`padding-left: ${row.depth * 16}px`"
                                >
                                    <template x-if="row.hasChildren">
                                        <button
                                            data-ndb-livewire-property-toggle
                                            type="button"
                                            @click="toggleLivewireProperty(row)"
                                            :aria-expanded="row.expanded"
                                            :aria-label="`${row.expanded ? 'Collapse' : 'Expand'} ${row.path}`"
                                            class="ndb:grid ndb:size-5 ndb:shrink-0 ndb:place-items-center ndb:rounded ndb:text-zinc-400 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
                                        >
                                            <x-newdebugbar::icon
                                                name="chevron-down"
                                                size="3"
                                                class="ndb:transition"
                                                ::class="row.expanded ? '' : 'ndb:-rotate-90'"
                                            />
                                        </button>
                                    </template>
                                    <code
                                        data-ndb-livewire-property-label
                                        class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-bold"
                                        :title="row.path"
                                        x-text="row.label"
                                    ></code>
                                    <span
                                        class="ndb:shrink-0 ndb:rounded-md ndb:bg-zinc-100 ndb:px-1.5 ndb:py-0.5 ndb:text-xs ndb:font-bold ndb:text-zinc-500 ndb:dark:bg-zinc-800 ndb:dark:text-zinc-400"
                                        x-text="row.phpType ?? row.type"
                                    ></span>
                                </div>
                                <div class="ndb:flex ndb:min-w-0 ndb:items-baseline ndb:gap-2 ndb:sm:block">
                                    <span class="ndb:w-16 ndb:shrink-0 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:sm:hidden">Client</span>
                                    <code
                                        class="ndb:block ndb:min-w-0 ndb:flex-1 ndb:truncate ndb:text-xs ndb:sm:w-full"
                                        :title="row.valueSummary"
                                        x-text="row.valueSummary"
                                    ></code>
                                </div>
                                <div class="ndb:flex ndb:min-w-0 ndb:items-baseline ndb:gap-2 ndb:sm:block">
                                    <span class="ndb:w-16 ndb:shrink-0 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:sm:hidden">Server</span>
                                    <code
                                        class="ndb:block ndb:min-w-0 ndb:flex-1 ndb:truncate ndb:text-xs ndb:text-zinc-500 ndb:sm:w-full ndb:dark:text-zinc-400"
                                        :title="row.serverSummary"
                                        x-text="row.serverSummary"
                                    ></code>
                                </div>
                                <div>
                                    <span
                                        class="ndb:text-xs ndb:font-bold"
                                        :class="row.state === 'Dirty'
                                            ? 'ndb:text-amber-700 ndb:dark:text-amber-300'
                                            : row.state === 'Updating'
                                              ? 'ndb:text-indigo-700 ndb:dark:text-indigo-300'
                                              : ['Locked', 'Unknown'].includes(row.state)
                                                ? 'ndb:text-zinc-400'
                                                : 'ndb:text-zinc-600 ndb:dark:text-zinc-300'"
                                        :title="livewirePropertyStateDescription(row)"
                                        x-text="livewirePropertyStateLabel(row)"
                                    ></span>
                                </div>
                                <x-newdebugbar::livewire-property-editor />
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    x-show.important="livewirePropertyRows.length === 0"
                    data-ndb-livewire-property-empty
                    class="ndb:mt-3"
                >
                    <x-newdebugbar::empty-state label="No serialized public properties." />
                </div>
            </section>
        </div>
    </template>

    <template x-if="livewireDetailTab === 'source'">
        <div data-ndb-livewire-detail-panel="source" class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-4 ndb:sm:p-4">
            <dl class="ndb:grid ndb:grid-cols-1 ndb:gap-2 ndb:sm:grid-cols-2">
                <x-newdebugbar::inspector-source-fact label="Implementation">
                    <x-slot:value
                        x-text="
                            selectedLivewireComponent.server?.implementation === 'single_file'
                                ? 'Single file'
                                : selectedLivewireComponent.server?.implementation === 'class'
                                  ? 'Class'
                                  : 'Browser only'
                        "
                    ></x-slot:value>
                </x-newdebugbar::inspector-source-fact>
                <x-newdebugbar::inspector-source-fact label="Instance">
                    <x-slot:value>
                        <span
                            data-ndb-livewire-component-instance
                            class="ndb:block ndb:truncate ndb:text-xs ndb:font-semibold"
                            ::title="selectedLivewireComponent.id"
                            x-text="selectedLivewireComponent.id"
                        ></span>
                    </x-slot:value>
                </x-newdebugbar::inspector-source-fact>
                <x-newdebugbar::inspector-source-fact
                    label="Component file"
                    x-show.important="selectedLivewireComponent.server?.source?.file"
                >
                    <x-slot:value
                        x-text="
                            selectedLivewireComponent.server?.source?.file
                                ? `${selectedLivewireComponent.server.source.file}${selectedLivewireComponent.server.source.line ? `:${selectedLivewireComponent.server.source.line}` : ''}`
                                : 'Not captured'
                        "
                    ></x-slot:value>
                </x-newdebugbar::inspector-source-fact>
                <x-newdebugbar::inspector-source-fact
                    label="Blade view"
                    x-show.important="selectedLivewireComponent.server?.view?.name"
                >
                    <x-slot:value x-text="selectedLivewireComponent.server?.view?.name ?? 'Not captured'"></x-slot:value>
                </x-newdebugbar::inspector-source-fact>
                <x-newdebugbar::inspector-source-fact
                    label="View file"
                    x-show.important="
                        selectedLivewireComponent.server?.view?.source?.file &&
                        selectedLivewireComponent.server.view.source.file !==
                            selectedLivewireComponent.server?.source?.file
                    "
                    class="ndb:sm:col-span-2"
                >
                    <x-slot:value
                        x-text="
                            `${selectedLivewireComponent.server.view.source.file}${selectedLivewireComponent.server.view.source.line ? `:${selectedLivewireComponent.server.view.source.line}` : ''}`
                        "
                    ></x-slot:value>
                </x-newdebugbar::inspector-source-fact>
            </dl>

            <x-newdebugbar::inspector-evidence
                label="Component class"
                language="php"
                x-show.important="selectedLivewireComponent.server?.class"
            >
                <x-slot:value x-text="selectedLivewireComponent.server.class"></x-slot:value>
            </x-newdebugbar::inspector-evidence>

            <div
                x-show.important="
                    ! selectedLivewireComponent.server?.class &&
                    ! selectedLivewireComponent.server?.source?.file &&
                    ! selectedLivewireComponent.server?.view?.name
                "
            >
                <x-newdebugbar::empty-state label="Server source was not captured for this component." />
            </div>
        </div>
    </template>
</article>

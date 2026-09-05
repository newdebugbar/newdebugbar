{{-- Explains the selected Laravel event through listener, payload, and source evidence. --}}
<x-newdebugbar::inspector-detail-pane
    detail-open="eventDetailOpen"
    detail-ref="eventDetail"
    detail-label="Selected Laravel event details"
    back-label="Events"
    close-action="closeEventDetail()"
    data-ndb-event-detail
>
    <x-slot:back>
        <x-newdebugbar::inspector-detail-back data-ndb-event-detail-back @click="closeEventDetail()" label="Events" />
    </x-slot:back>

    <template x-if="! selectedEvent">
        <x-newdebugbar::inspector-detail-empty label="No event is selected. Adjust the source filter or search." />
    </template>

    <template x-if="selectedEvent">
        <div class="ndb:flex ndb:flex-col">
            <x-newdebugbar::inspector-detail-header data-ndb-event-header>
                <x-slot:title>
                    <div class="ndb:min-w-0">
                        <h3
                            data-ndb-event-detail-title
                            class="ndb:break-words ndb:text-base ndb:font-bold ndb:leading-6"
                            x-text="selectedEvent.display_name"
                        ></h3>
                        <code
                            data-ndb-event-qualified-name
                            x-show.important="selectedEvent.name !== selectedEvent.display_name"
                            :title="selectedEvent.name"
                            class="ndb:mt-1 ndb:block ndb:break-all ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-medium ndb:leading-4 ndb:text-zinc-400"
                            x-text="selectedEvent.name"
                        ></code>
                    </div>
                </x-slot:title>
                <x-slot:aside></x-slot:aside>
            </x-newdebugbar::inspector-detail-header>

            <x-newdebugbar::inspector-detail-tabs label="Laravel event detail">
                @foreach (['overview' => ['Overview', 'eye'], 'payload' => ['Payload', 'database'], 'source' => ['Source', 'code']] as $tab => [$label, $icon])
                    <x-newdebugbar::filter-tab
                        variant="segmented"
                        data-ndb-event-detail-tab="{{ $tab }}"
                        @click="setEventDetailTab({{ \Illuminate\Support\Js::from($tab) }})"
                        ::aria-pressed="eventDetailTab === {{ \Illuminate\Support\Js::from($tab) }}"
                        aria-label="{{ $label }}"
                        class="ndb:h-auto"
                    >
                        <x-newdebugbar::icon
                            name="{{ $icon }}"
                            size="3.5"
                            data-ndb-event-detail-tab-icon="{{ $tab }}"
                            class="ndb:sm:hidden"
                        />
                        <span class="ndb:hidden ndb:sm:inline">{{ $label }}</span>
                    </x-newdebugbar::filter-tab>
                @endforeach
            </x-newdebugbar::inspector-detail-tabs>

            <template x-if="eventDetailTab === 'overview'">
                <div data-ndb-event-detail-panel="overview" class="ndb:p-3 ndb:sm:p-4">
                    <x-newdebugbar::inspector-facts columns="4" data-ndb-event-facts>
                        <x-newdebugbar::inspector-fact label="Origin" data-ndb-event-fact>
                            <x-slot:value
                                class="ndb:truncate ndb:font-semibold"
                                x-text="
                                    selectedEvent.source === 'application'
                                        ? selectedEvent.broadcast
                                            ? 'Application broadcast'
                                            : 'Application'
                                        : 'Framework'
                                "
                            ></x-slot:value>
                        </x-newdebugbar::inspector-fact>
                        <x-newdebugbar::inspector-fact label="Sequence" data-ndb-event-fact>
                            <x-slot:value
                                class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                                x-text="
                                    selectedEvent.first_sequence === selectedEvent.last_sequence
                                        ? '#' + selectedEvent.first_sequence
                                        : '#' + selectedEvent.first_sequence + '–' + selectedEvent.last_sequence
                                "
                            ></x-slot:value>
                        </x-newdebugbar::inspector-fact>
                        <x-newdebugbar::inspector-fact label="Dispatches" data-ndb-event-fact>
                            <x-slot:value
                                class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                                x-text="selectedEvent.occurrence_count"
                            ></x-slot:value>
                        </x-newdebugbar::inspector-fact>
                        <x-newdebugbar::inspector-fact label="First seen" data-ndb-event-fact>
                            <x-slot:value
                                class="ndb:truncate ndb:font-semibold ndb:tabular-nums"
                                x-text="formatEventTime(selectedEvent.first_at_ms)"
                            ></x-slot:value>
                        </x-newdebugbar::inspector-fact>
                    </x-newdebugbar::inspector-facts>

                    <section data-ndb-event-listeners class="ndb:mt-4 ndb:sm:mt-6">
                        <div class="ndb:flex ndb:items-baseline ndb:justify-between ndb:gap-3">
                            <h4 class="ndb:text-xs ndb:font-bold">Listener handling</h4>
                            <span
                                data-ndb-event-listener-outcome
                                class="ndb:bg-transparent ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                x-text="selectedEvent.listener_outcome_label"
                            ></span>
                        </div>
                        <p
                            class="ndb:mt-1 ndb:text-xs ndb:leading-5 ndb:text-zinc-600 ndb:dark:text-zinc-300"
                            x-text="selectedEvent.listener_summary"
                        ></p>
                        <p
                            x-show.important="selectedEvent.duplicate_registration_count > 0"
                            class="ndb:mt-1 ndb:text-xs ndb:font-bold ndb:text-amber-600 ndb:dark:text-amber-300"
                            x-text="
                                selectedEvent.duplicate_registration_count +
                                (selectedEvent.duplicate_registration_count === 1
                                    ? ' extra registration needs review.'
                                    : ' extra registrations need review.')
                            "
                        ></p>

                        <div
                            x-show.important="selectedEvent.listeners.length > 0"
                            class="ndb:mt-3 ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800"
                        >
                            <template
                                x-for="listener in selectedEvent.listeners"
                                :key="listener.name +
                                ':' +
                                (listener.source?.file ?? '') +
                                ':' +
                                (listener.source?.line ?? '')"
                            >
                                <div
                                    data-ndb-event-listener-row
                                    class="ndb:grid ndb:grid-cols-[minmax(0,1fr)_auto] ndb:gap-x-3 ndb:gap-y-1.5 ndb:bg-transparent ndb:px-0 ndb:py-3 ndb:first:pt-0"
                                >
                                    <code
                                        class="ndb:col-start-1 ndb:row-start-1 ndb:min-w-0 ndb:break-all ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-semibold"
                                        x-text="listener.name"
                                    ></code>
                                    <span
                                        class="ndb:col-start-2 ndb:row-start-1 ndb:justify-self-end ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                        x-text="listener.queued ? 'Queued' : 'Completed'"
                                    ></span>
                                    <span
                                        x-show.important="listener.source"
                                        class="ndb:col-start-1 ndb:row-start-2 ndb:min-w-0 ndb:truncate ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                        x-text="
                                            listener.source ? listener.source.file + ':' + listener.source.line : ''
                                        "
                                    ></span>
                                    <span
                                        :class="listener.registrations > 1
                                            ? 'ndb:text-amber-600 ndb:dark:text-amber-300'
                                            : 'ndb:text-zinc-400'"
                                        class="ndb:col-start-2 ndb:row-start-2 ndb:justify-self-end ndb:text-xs ndb:font-semibold ndb:tabular-nums"
                                        x-text="
                                            listener.registrations +
                                            (listener.registrations === 1 ? ' registration' : ' registrations')
                                        "
                                    ></span>
                                </div>
                            </template>
                        </div>
                    </section>

                    <x-newdebugbar::inspector-action
                        icon="external-link"
                        data-ndb-event-related-section
                        x-show.important="selectedEvent.related_section"
                        @click="navigateToSection(selectedEvent.related_section.key)"
                        class="ndb:mt-4 ndb:sm:mt-6"
                    >
                        <span x-text="selectedEvent.related_section ? 'Open ' + selectedEvent.related_section.label : ''"></span>
                    </x-newdebugbar::inspector-action>

                    <details
                        data-ndb-event-outcome-help
                        x-show.important="selectedEvent.listeners.length > 0"
                        class="ndb:group ndb:mt-3 ndb:border-0 ndb:bg-transparent ndb:p-0 ndb:sm:mt-4"
                    >
                        <summary class="ndb:flex ndb:cursor-pointer ndb:list-none ndb:items-center ndb:gap-1.5 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-zinc-400">
                            How listener outcomes are recorded
                            <x-newdebugbar::icon
                                name="chevron-down"
                                size="3"
                                class="ndb:transition ndb:group-open:rotate-180"
                            />
                        </summary>
                        <p class="ndb:mt-2 ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400">
                            Completed means Laravel reached this observer after synchronous listener dispatch. Queued
                            means Laravel handed the listener to the queue. Laravel does not expose per-listener
                            duration to this observer.
                        </p>
                    </details>
                </div>
            </template>

            <template x-if="eventDetailTab === 'payload'">
                <div data-ndb-event-detail-panel="payload" class="ndb:p-3 ndb:sm:p-4">
                    <h4 class="ndb:text-xs ndb:font-bold">Payload shape</h4>

                    <template x-if="selectedEvent.payload_shape.length === 0">
                        <p class="ndb:mt-2 ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400">
                            No payload arguments were exposed.
                        </p>
                    </template>

                    <dl
                        x-show.important="selectedEvent.payload_shape.length > 0"
                        class="ndb:mt-2 ndb:divide-y ndb:divide-zinc-200/90 ndb:bg-transparent ndb:dark:divide-zinc-800"
                    >
                        <template x-for="entry in selectedEvent.payload_shape" :key="entry.position">
                            <x-newdebugbar::inspector-definition-row>
                                <x-slot:term>
                                    <span x-text="'Argument ' + entry.position"></span>
                                </x-slot:term>
                                <x-slot:value class="ndb:min-w-0 ndb:bg-transparent">
                                    <code
                                        class="ndb:block ndb:break-all ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-semibold"
                                        x-text="entry.type"
                                    ></code>
                                    <div
                                        x-show.important="entry.fields.length > 0"
                                        class="ndb:mt-2 ndb:grid ndb:gap-1 ndb:sm:grid-cols-[4rem_minmax(0,1fr)]"
                                    >
                                        <span class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400">Fields</span>
                                        <code
                                            class="ndb:break-all ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:text-zinc-600 ndb:dark:text-zinc-300"
                                            x-text="entry.fields.join(', ')"
                                        ></code>
                                    </div>
                                    <p
                                        x-show.important="entry.field_count > entry.fields.length"
                                        class="ndb:mt-1 ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                                        x-text="
                                            entry.field_count -
                                            entry.fields.length +
                                            (entry.field_count - entry.fields.length === 1
                                                ? ' more field is not shown.'
                                                : ' more fields are not shown.')
                                        "
                                    ></p>
                                </x-slot:value>
                            </x-newdebugbar::inspector-definition-row>
                        </template>
                    </dl>

                    <p class="ndb:mt-3 ndb:text-xs ndb:leading-5 ndb:text-zinc-400">
                        Field names and types are shown. Payload values are not captured.
                    </p>
                </div>
            </template>

            <template x-if="eventDetailTab === 'source'">
                <div data-ndb-event-detail-panel="source" class="ndb:p-3 ndb:sm:p-4">
                    <section data-ndb-event-dispatch-sources>
                        <div class="ndb:flex ndb:items-baseline ndb:justify-between ndb:gap-3">
                            <h4 class="ndb:text-xs ndb:font-bold">Dispatch locations</h4>
                            <span
                                class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                                x-text="
                                    selectedEvent.dispatch_source_count === 1
                                        ? '1 application location'
                                        : selectedEvent.dispatch_source_count + ' application locations'
                                "
                            ></span>
                        </div>

                        <template x-if="selectedEvent.dispatch_sources.length === 0">
                            <p class="ndb:mt-2 ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                No application dispatch location was captured. Framework-only stack frames stay hidden.
                            </p>
                        </template>

                        <div
                            x-show.important="selectedEvent.dispatch_sources.length > 0"
                            class="ndb:mt-2 ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800"
                        >
                            <template
                                x-for="(source, index) in selectedEvent.dispatch_sources"
                                :key="source.file + ':' + source.line"
                            >
                                <div class="ndb:grid ndb:min-w-0 ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-center ndb:gap-3 ndb:py-2.5">
                                    <x-newdebugbar::inspector-source-link
                                        data-ndb-event-copy-dispatch-source
                                        ::data-ndb-event-copy-dispatch-source-index="index"
                                        @click="copyText(source.file + ':' + source.line)"
                                        ::aria-label="'Copy dispatch source ' + source.file + ':' + source.line"
                                    >
                                        <x-slot:value x-text="source.file + ':' + source.line"></x-slot:value>
                                    </x-newdebugbar::inspector-source-link>
                                    <span
                                        class="ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-400"
                                        x-text="source.count + (source.count === 1 ? ' dispatch' : ' dispatches')"
                                    ></span>
                                </div>
                            </template>
                        </div>

                        <p
                            data-ndb-event-dispatch-sources-omitted
                            x-show.important="selectedEvent.dispatch_source_omitted_count > 0"
                            class="ndb:mt-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                            x-text="
                                selectedEvent.dispatch_source_omitted_count +
                                (selectedEvent.dispatch_source_omitted_count === 1
                                    ? ' lower-frequency location is not shown.'
                                    : ' lower-frequency locations are not shown.')
                            "
                        ></p>
                    </section>

                    <details
                        data-ndb-event-timeline
                        x-show.important="selectedEvent.occurrence_count > 1"
                        class="ndb:group ndb:mt-4 ndb:border-0 ndb:bg-transparent ndb:p-0 ndb:sm:mt-6"
                    >
                        <summary class="ndb:flex ndb:cursor-pointer ndb:list-none ndb:items-center ndb:justify-between ndb:gap-3 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:font-bold ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500">
                            <span>Dispatch timeline</span>
                            <span class="ndb:flex ndb:items-center ndb:gap-1.5 ndb:text-xs ndb:font-semibold ndb:text-zinc-400">
                                <span
                                    x-text="
                                        selectedEvent.occurrences.length +
                                        (selectedEvent.occurrences.length === 1
                                            ? ' dispatch shown'
                                            : ' dispatches shown')
                                    "
                                ></span>
                                <x-newdebugbar::icon
                                    name="chevron-down"
                                    size="3"
                                    class="ndb:transition ndb:group-open:rotate-180"
                                />
                            </span>
                        </summary>

                        <div class="ndb:mt-3 ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                            <template x-for="occurrence in selectedEvent.occurrences" :key="occurrence.sequence">
                                <div class="ndb:grid ndb:grid-cols-[auto_minmax(0,1fr)_auto] ndb:items-center ndb:gap-3 ndb:bg-transparent ndb:py-2.5">
                                    <span
                                        class="ndb:text-xs ndb:font-bold ndb:tabular-nums"
                                        x-text="'#' + occurrence.sequence"
                                    ></span>
                                    <span
                                        x-show.important="occurrence.callsite"
                                        class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:text-zinc-400"
                                        x-text="
                                            occurrence.callsite
                                                ? occurrence.callsite.file + ':' + occurrence.callsite.line
                                                : ''
                                        "
                                    ></span>
                                    <span class="ndb:flex ndb:flex-wrap ndb:items-center ndb:justify-end ndb:gap-2">
                                        <span
                                            x-show.important="occurrence.lifecycle === 'after_response'"
                                            class="ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                        >After response</span>
                                        <span
                                            class="ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                            x-text="formatEventTime(occurrence.at_ms)"
                                        ></span>
                                    </span>
                                </div>
                            </template>
                        </div>

                        <p
                            data-ndb-event-occurrences-omitted
                            x-show.important="selectedEvent.occurrence_omitted_count > 0"
                            class="ndb:mt-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                            x-text="
                                selectedEvent.occurrence_omitted_count +
                                (selectedEvent.occurrence_omitted_count === 1
                                    ? ' middle dispatch is not shown.'
                                    : ' middle dispatches are not shown.')
                            "
                        ></p>
                    </details>
                </div>
            </template>
        </div>
    </template>
</x-newdebugbar::inspector-detail-pane>

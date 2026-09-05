{{-- Groups Laravel dispatches into a compact event list with a focused evidence pane. --}}
@php
    $eventGroups = array_values($section['payload']['groups'] ?? []);
    $eventSummary = $section['summary'];
    $eventSourceCounts = [
        'all' => (int) ($eventSummary['retained_count'] ?? count($section['payload']['items'] ?? [])),
        'application' => (int) ($eventSummary['application_count'] ?? 0),
        'framework' => (int) ($eventSummary['framework_count'] ?? 0),
    ];
@endphp

<div
    data-ndb-events
    x-init="initializeEvents(JSON.parse(atob($el.querySelector('[data-ndb-event-payload]').textContent.trim())))"
    class="ndb:space-y-4 ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col ndb:lg:space-y-0"
>
    <script type="application/json" data-ndb-event-payload>
        {{ base64_encode(\Illuminate\Support\Js::encode($eventGroups)) }}
    </script>

    @if ($eventGroups !== [])
        <x-newdebugbar::inspector-workspace frame="top" data-ndb-event-workspace>
            <x-newdebugbar::inspector-list-panel detail-open="eventDetailOpen" list-ref="eventList">
                <x-slot:controls>
                    <x-newdebugbar::inspector-list-controls :show-search="true">
                        <x-slot:leading>
                            <p
                                data-ndb-event-visible-summary
                                aria-live="polite"
                                class="ndb:min-w-0 ndb:text-xs ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300"
                                x-text="visibleEventSummary"
                            ></p>
                        </x-slot:leading>

                        <x-slot:search>
                            <x-newdebugbar::search-field
                                label="Search events"
                                placeholder="Search events, listeners, or payloads"
                                data-ndb-event-search
                                x-model="eventSearch"
                                @input.debounce.100ms="applyEventFilters()"
                            />
                        </x-slot:search>

                        <x-slot:filter>
                            <x-newdebugbar::select-field
                                label="Filter events by source"
                                data-ndb-event-source-control
                                x-model="eventSource"
                                @change="setEventSource($event.target.value)"
                            >
                                @foreach (['all' => 'All', 'application' => 'Application', 'framework' => 'Framework'] as $source => $label)
                                    <option
                                        value="{{ $source }}"
                                        data-ndb-event-source="{{ $source }}"
                                        data-ndb-event-source-count="{{ $source }}"
                                    >
                                        {{ $label }} ({{ $eventSourceCounts[$source] }})
                                    </option>
                                @endforeach
                            </x-newdebugbar::select-field>
                        </x-slot:filter>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list data-ndb-event-list aria-label="Laravel events">
                    @foreach ($eventGroups as $event)
                        @php
                            $eventListenerActivity = match (true) {
                                $event['listener_count'] === 0 => 'No listeners',
                                $event['completed_listener_count'] > 0 && $event['queued_listener_count'] > 0 => number_format($event['completed_listener_count']).' completed, '.number_format($event['queued_listener_count']).' queued',
                                $event['queued_listener_count'] > 0 => number_format($event['queued_listener_count']).' queued',
                                default => number_format($event['completed_listener_count']).' completed',
                            };
                        @endphp
                        <button
                            type="button"
                            data-ndb-event-item="{{ $event['id'] }}"
                            data-ndb-event-id="{{ $event['id'] }}"
                            data-ndb-event-source-value="{{ $event['source'] }}"
                            data-ndb-event-search-value="{{ $event['search'] }}"
                            data-ndb-event-occurrence-count="{{ $event['occurrence_count'] }}"
                            @click="selectEvent({{ $event['id'] }}, $el)"
                            :aria-pressed="eventSelected === {{ $event['id'] }}"
                            :class="eventSelected === {{ $event['id'] }}
                                ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                                : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
                            class="ndb:grid ndb:h-auto ndb:w-full ndb:grid-cols-[minmax(0,1fr)_8rem] ndb:items-baseline ndb:gap-x-3 ndb:gap-y-1 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:sm:py-3"
                        >
                            <span
                                data-ndb-event-list-name
                                class="ndb:col-start-1 ndb:row-start-1 ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-bold ndb:text-zinc-900 ndb:dark:text-zinc-100"
                            >{{ $event['display_name'] }}</span>
                            <span
                                class="ndb:col-start-2 ndb:row-start-1 ndb:w-full ndb:truncate ndb:text-right ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                title="{{ $eventListenerActivity }}"
                            >{{ $eventListenerActivity }}</span>
                            <span class="ndb:col-start-1 ndb:row-start-2 ndb:flex ndb:min-w-0 ndb:items-baseline ndb:gap-2 ndb:overflow-hidden ndb:text-xs ndb:text-zinc-400">
                                <span
                                    x-show.important="eventSource === 'all' || {{ $event['namespace'] === null ? 'true' : 'false' }}"
                                    class="ndb:shrink-0 ndb:font-semibold"
                                >{{ $event['source'] === 'application' ? 'Application' : 'Framework' }}</span>
                                @if ($event['namespace'] !== null)
                                    <code
                                        data-ndb-event-list-namespace
                                        class="ndb:min-w-0 ndb:truncate ndb:font-mono ndb:text-xs"
                                    >{{ $event['namespace'] }}</code>
                                @endif
                            </span>
                            <span @class([
                                'ndb:col-start-2 ndb:row-start-2 ndb:w-full ndb:truncate ndb:text-right ndb:text-xs ndb:font-semibold',
                                'ndb:text-amber-600 ndb:dark:text-amber-300' => $event['duplicate_registration_count'] > 0,
                                'ndb:tabular-nums ndb:text-zinc-400' => $event['duplicate_registration_count'] === 0,
                            ])>
                                @if ($event['duplicate_registration_count'] > 0)
                                    Duplicate registration
                                @else
                                    {{ number_format($event['occurrence_count']) }} {{ \Illuminate\Support\Str::plural('dispatch', $event['occurrence_count']) }}
                                @endif
                            </span>
                        </button>
                    @endforeach
                </x-slot:list>

                <x-slot:empty data-ndb-event-empty x-show.important="visibleEventGroupCount === 0">
                    <x-newdebugbar::empty-state label="No events match this source and search." />
                </x-slot:empty>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::event-detail />
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No Laravel events were captured." />
    @endif
</div>

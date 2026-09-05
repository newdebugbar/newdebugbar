{{-- Presents request activity as a desktop waterfall and mobile chronological drill-in. --}}
@php
    $timelineItems = array_values($section['payload']['items'] ?? []);
    $timelineSections = $section['payload']['available_sections'] ?? array_values(array_unique(array_column($timelineItems, 'section')));
    $timelineSourceSections = array_values(array_filter($timelineSections, fn ($timelineSection) => $timelineSection !== 'request'));
    $timelineKeySections = ['request', 'queries', 'http_client', 'exceptions', 'authorization', 'validation', 'queue'];
    $timelineDuration = (float) ($section['payload']['total_duration_ms'] ?? max(0.001, ...array_column($timelineItems, 'at_ms')));
    $timelineTotal = (int) ($section['payload']['total_item_count'] ?? count($timelineItems));
    $timelineLoaded = count($timelineItems);
    $timelineTicks = [0, 25, 50, 75, 100];
    $formatMilliseconds = static fn (?float $value): ?string => $value === null
        ? null
        : \NewDebugBar\Support\DurationFormatter::format($value);
@endphp

<div
    data-ndb-timeline
    class="ndb:text-zinc-950 ndb:dark:text-white ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    @if ($timelineItems !== [])
        <x-newdebugbar::inspector-workspace
            mode="focus"
            frame="top"
            detail-open="timelineDetailOpen"
            detail-id="newdebugbar-timeline-detail"
            detail-ref="timelineDetail"
            detail-label="Selected timeline activity"
            back-label="Timeline"
            close-action="closeTimelineDetail()"
            data-ndb-timeline-workspace
            class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col"
        >
            <x-slot:list class="ndb:flex ndb:h-full ndb:min-h-0 ndb:flex-col">
                <x-newdebugbar::inspector-list-panel
                    detail-open="false"
                    list-ref="timelineList"
                    data-ndb-timeline-list-panel
                    class="ndb:min-h-[32rem] ndb:flex-1 ndb:lg:min-h-0 ndb:lg:border-r-0"
                >
                    <x-slot:controls>
                        <x-newdebugbar::inspector-list-controls :show-search="true">
                            <x-slot:leading>
                                <p
                                    data-ndb-timeline-summary
                                    aria-live="polite"
                                    aria-atomic="true"
                                    class="ndb:flex ndb:flex-col ndb:items-start ndb:text-xs ndb:text-zinc-700 ndb:dark:text-zinc-200"
                                >
                                    <strong class="ndb:font-bold">
                                        <span x-text="visibleTimelineCount"></span>
                                        matching
                                    </strong>
                                    <span class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                        of {{ number_format($timelineLoaded) }} loaded across {{ $formatMilliseconds($timelineDuration) }}
                                    </span>
                                </p>
                            </x-slot:leading>

                            <x-slot:search>
                                <x-newdebugbar::search-field
                                    label="Search timeline activity"
                                    placeholder="Search activity or source"
                                    data-ndb-timeline-search-field
                                    x-model="timelineSearch"
                                    @input.debounce.100ms="applyTimelineFilters()"
                                />
                            </x-slot:search>

                            <x-slot:filter>
                                <x-newdebugbar::select-field
                                    label="Filter timeline activity"
                                    data-ndb-timeline-filter
                                    x-model="timelineFilter"
                                    @change="setTimelineFilter($event.target.value)"
                                >
                                    <optgroup label="View">
                                        <option value="key">Key activity</option>
                                        <option value="all">All activity</option>
                                    </optgroup>
                                    <optgroup label="Source">
                                        <option value="request">Request</option>
                                        @foreach ($timelineSourceSections as $timelineSection)
                                            <option value="{{ $timelineSection }}">
                                                {{ str($timelineSection)->replace('_', ' ')->title() }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                </x-newdebugbar::select-field>
                            </x-slot:filter>
                        </x-newdebugbar::inspector-list-controls>
                    </x-slot:controls>

                    <x-slot:list
                        data-ndb-timeline-list
                        class="ndb:divide-y ndb:divide-zinc-200/80 ndb:bg-transparent ndb:dark:divide-zinc-800"
                    >
                        <div
                            data-ndb-timeline-waterfall-header
                            class="ndb:sticky ndb:top-0 ndb:z-10 ndb:hidden ndb:grid-cols-[minmax(13rem,0.8fr)_minmax(20rem,2fr)_6rem] ndb:border-b ndb:border-zinc-200/90 ndb:bg-white/95 ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400 ndb:backdrop-blur-sm ndb:dark:border-zinc-800 ndb:dark:bg-zinc-950/95 ndb:lg:grid"
                        >
                            <span class="ndb:px-3 ndb:py-2">Activity</span>
                            <span
                                data-ndb-timeline-waterfall
                                class="ndb:relative ndb:border-x ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
                                aria-label="Timeline from zero to {{ $formatMilliseconds($timelineDuration) }}"
                            >
                                @foreach ($timelineTicks as $tick)
                                    @php
                                        $timelineTickMs = $timelineDuration * $tick / 100;
                                    @endphp
                                    <span
                                        data-ndb-timeline-tick="{{ $tick }}"
                                        class="ndb:absolute ndb:top-1/2 ndb:-translate-x-1/2 ndb:-translate-y-1/2 ndb:normal-case ndb:tracking-normal ndb:first:translate-x-0 ndb:last:-translate-x-full"
                                        style="left: {{ $tick }}%"
                                    >{{ $formatMilliseconds($timelineTickMs) }}</span>
                                @endforeach
                            </span>
                            <span class="ndb:px-3 ndb:py-2 ndb:text-right">Time</span>
                        </div>

                        @foreach ($timelineItems as $item)
                            @php
                                $timelineSectionLabel = $item['section_label'] ?? str($item['section'])->replace('_', ' ')->title();
                                $timelineSource = is_array($item['source'] ?? null) ? $item['source'] : null;
                                $timelineSourceLabel = $timelineSource === null
                                    ? null
                                    : $timelineSource['file'].':'.($timelineSource['line'] ?? 1);
                                $timelineAtLabel = $formatMilliseconds((float) $item['at_ms']);
                                $timelineStartLabel = isset($item['start_ms']) ? $formatMilliseconds((float) $item['start_ms']) : null;
                                $timelineDurationLabel = isset($item['duration_ms']) ? $formatMilliseconds((float) $item['duration_ms']) : null;
                                $timelineKindLabel = match ($item['kind']) {
                                    'span' => 'Duration',
                                    'milestone' => 'Request milestone',
                                    default => 'Event',
                                };
                                $timelineSearchValue = mb_strtolower(implode(' ', array_filter([
                                    $item['label'],
                                    $timelineSectionLabel,
                                    $timelineSourceLabel,
                                ])));
                            @endphp
                            <button
                                type="button"
                                data-ndb-timeline-item="{{ $item['id'] }}"
                                data-ndb-timeline-section="{{ $item['section'] }}"
                                data-ndb-timeline-section-label="{{ $timelineSectionLabel }}"
                                data-ndb-timeline-key="{{ in_array($item['section'], $timelineKeySections, true) ? 'true' : 'false' }}"
                                data-ndb-timeline-kind="{{ $timelineKindLabel }}"
                                data-ndb-timeline-label="{{ $item['label'] }}"
                                data-ndb-timeline-at="{{ $item['at_ms'] }}"
                                data-ndb-timeline-at-label="{{ $timelineAtLabel }}"
                                data-ndb-timeline-start="{{ $item['start_ms'] ?? '' }}"
                                data-ndb-timeline-start-label="{{ $timelineStartLabel }}"
                                data-ndb-timeline-duration="{{ $item['duration_ms'] ?? '' }}"
                                data-ndb-timeline-duration-label="{{ $timelineDurationLabel }}"
                                data-ndb-timeline-source="{{ $timelineSourceLabel }}"
                                data-ndb-timeline-search-value="{{ $timelineSearchValue }}"
                                wire:key="timeline-item-{{ $item['id'] }}"
                                aria-controls="newdebugbar-timeline-detail"
                                @click="selectTimelineItem({{ \Illuminate\Support\Js::from($item['id']) }})"
                                :aria-pressed="selectedTimelineItem?.id === {{ \Illuminate\Support\Js::from($item['id']) }}"
                                :class="selectedTimelineItem?.id === {{ \Illuminate\Support\Js::from($item['id']) }}
                                    ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                                    : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
                                class="ndb:grid ndb:h-auto ndb:w-full ndb:min-w-0 ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-center ndb:gap-x-3 ndb:bg-transparent ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:sm:py-3 ndb:lg:grid-cols-[minmax(13rem,0.8fr)_minmax(20rem,2fr)_6rem] ndb:lg:px-0 ndb:lg:py-0"
                                style="--ndb-timeline-at: {{ $item['at_percent'] }}%; --ndb-timeline-start: {{ $item['start_percent'] ?? $item['at_percent'] }}%; --ndb-timeline-width: {{ $item['duration_percent'] ?? 0 }}%;"
                            >
                                <span class="ndb:min-w-0 ndb:lg:px-3 ndb:lg:py-2.5">
                                    <span class="ndb:block ndb:truncate ndb:text-xs ndb:font-bold">{{ $item['label'] }}</span>
                                    <span
                                        data-ndb-timeline-activity-section
                                        class="ndb:mt-0.5 ndb:block ndb:truncate ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                    >
                                        {{ $timelineSectionLabel }}
                                        @if ($timelineDurationLabel !== null)
                                            <span class="ndb:lg:hidden">, {{ $timelineDurationLabel }}</span>
                                        @endif
                                    </span>
                                </span>

                                <span
                                    data-ndb-timeline-track
                                    class="ndb:relative ndb:hidden ndb:h-full ndb:min-h-11 ndb:border-x ndb:border-zinc-200/90 ndb:bg-[linear-gradient(to_right,transparent_calc(25%-0.5px),rgba(161,161,170,0.16)_25%,transparent_calc(25%+0.5px),transparent_calc(50%-0.5px),rgba(161,161,170,0.16)_50%,transparent_calc(50%+0.5px),transparent_calc(75%-0.5px),rgba(161,161,170,0.16)_75%,transparent_calc(75%+0.5px))] ndb:dark:border-zinc-800 ndb:lg:block"
                                >
                                    @if ($item['kind'] === 'span')
                                        <span
                                            data-ndb-timeline-mark
                                            class="ndb:absolute ndb:top-1/2 ndb:left-[var(--ndb-timeline-start)] ndb:h-2.5 ndb:w-[max(3px,var(--ndb-timeline-width))] ndb:-translate-y-1/2 ndb:rounded-sm ndb:bg-indigo-500 ndb:dark:bg-indigo-400"
                                        ></span>
                                    @elseif ($item['kind'] === 'milestone')
                                        <span
                                            data-ndb-timeline-mark
                                            class="ndb:absolute ndb:top-1/2 ndb:left-[clamp(4px,var(--ndb-timeline-at),calc(100%-4px))] ndb:h-5 ndb:w-px ndb:-translate-x-1/2 ndb:-translate-y-1/2 ndb:bg-zinc-700 ndb:dark:bg-zinc-200"
                                        ></span>
                                    @else
                                        <span
                                            data-ndb-timeline-mark
                                            class="ndb:absolute ndb:top-1/2 ndb:left-[clamp(4px,var(--ndb-timeline-at),calc(100%-4px))] ndb:size-2.5 ndb:-translate-x-1/2 ndb:-translate-y-1/2 ndb:rounded-full ndb:border-2 ndb:border-white ndb:bg-zinc-500 ndb:dark:border-zinc-950 ndb:dark:bg-zinc-300"
                                        ></span>
                                    @endif
                                </span>

                                <span class="ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-600 ndb:dark:text-zinc-300 ndb:lg:px-3 ndb:lg:py-2.5">
                                    {{ $timelineAtLabel }}
                                </span>
                            </button>
                        @endforeach

                        <div x-show.important="visibleTimelineCount === 0" class="ndb:p-3">
                            <x-newdebugbar::empty-state label="No timeline activity matches this search and filter." />
                        </div>

                        @if ($section['payload']['has_more'] ?? false)
                            <div
                                data-ndb-timeline-pagination
                                data-ndb-timeline-page-sentinel
                                wire:key="timeline-page-sentinel-{{ $timelineLoaded }}"
                                x-init="$nextTick(() => observeTimelinePageEnd($el, $wire))"
                                :aria-busy="timelineLoadingMore"
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                                class="ndb:flex ndb:min-h-12 ndb:items-center ndb:justify-center ndb:px-3 ndb:py-3 ndb:text-center"
                            >
                                <span
                                    x-show.important="! timelineLoadingMore && ! timelinePaginationError"
                                    class="ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                                >
                                    Showing {{ number_format($timelineLoaded) }} of {{ number_format($timelineTotal) }} timeline
                                    events. More activity loads as you scroll.
                                </span>
                                <span
                                    x-cloak
                                    x-show.important="timelineLoadingMore"
                                    data-ndb-timeline-page-loading
                                    class="ndb:inline-flex ndb:items-center ndb:gap-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-300"
                                >
                                    <span
                                        aria-hidden="true"
                                        class="ndb:size-3 ndb:animate-spin ndb:rounded-full ndb:border-2 ndb:border-zinc-300 ndb:border-t-indigo-500 ndb:dark:border-zinc-700 ndb:dark:border-t-indigo-400"
                                    ></span>
                                    Loading up to {{ number_format(min(50, $timelineTotal - $timelineLoaded)) }} more
                                    timeline events…
                                </span>
                                <span
                                    x-cloak
                                    x-show.important="timelinePaginationError"
                                    data-ndb-timeline-page-error
                                    class="ndb:inline-flex ndb:flex-wrap ndb:items-center ndb:justify-center ndb:gap-x-2 ndb:gap-y-1 ndb:text-xs ndb:font-semibold ndb:text-rose-700 ndb:dark:text-rose-300"
                                >
                                    More activity could not be loaded.
                                    <button
                                        type="button"
                                        data-ndb-timeline-page-retry
                                        @click="retryTimelinePage($wire)"
                                        class="ndb:h-auto ndb:bg-transparent ndb:p-0 ndb:font-bold ndb:underline ndb:decoration-current/50 ndb:underline-offset-2 ndb:hover:decoration-current ndb:focus-visible:rounded-sm ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500"
                                    >
                                        Retry
                                    </button>
                                </span>
                            </div>
                        @elseif ($timelineTotal > 50)
                            <p
                                data-ndb-timeline-complete
                                class="ndb:px-3 ndb:py-3 ndb:text-center ndb:text-xs ndb:font-semibold ndb:text-zinc-400"
                            >
                                All {{ number_format($timelineTotal) }} timeline events are loaded.
                            </p>
                        @endif
                    </x-slot:list>
                </x-newdebugbar::inspector-list-panel>
            </x-slot:list>

            <x-slot:detail class="ndb-scrollbar ndb:h-full ndb:min-h-0 ndb:overflow-y-auto">
                <template x-if="selectedTimelineItem">
                    <div data-ndb-timeline-detail-content class="ndb:flex ndb:flex-col">
                        <x-newdebugbar::inspector-detail-header layout="wrap">
                            <x-slot:title>
                                <div class="ndb:min-w-0">
                                    <p
                                        class="ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                                        x-text="selectedTimelineItem.sectionLabel"
                                    ></p>
                                    <h3
                                        data-ndb-timeline-detail-label
                                        class="ndb:mt-0.5 ndb:break-words ndb:text-base ndb:font-bold ndb:leading-6"
                                        x-text="selectedTimelineItem.label"
                                    ></h3>
                                </div>
                            </x-slot:title>

                            <x-slot:aside>
                                <x-newdebugbar::inspector-action
                                    icon="external-link"
                                    data-ndb-timeline-open-section
                                    @click="selectSection(selectedTimelineItem.section)"
                                >
                                    Open section
                                </x-newdebugbar::inspector-action>
                            </x-slot:aside>
                        </x-newdebugbar::inspector-detail-header>

                        <div class="ndb:p-3 ndb:sm:p-4">
                            <x-newdebugbar::inspector-facts :columns="4">
                                <x-newdebugbar::inspector-fact label="At">
                                    <x-slot:value
                                        class="ndb:font-semibold ndb:tabular-nums"
                                        x-text="selectedTimelineItem.atLabel"
                                    ></x-slot:value>
                                </x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Type">
                                    <x-slot:value
                                        class="ndb:font-semibold"
                                        x-text="selectedTimelineItem.kind"
                                    ></x-slot:value>
                                </x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Started">
                                    <x-slot:value
                                        class="ndb:font-semibold ndb:tabular-nums"
                                        x-text="selectedTimelineItem.startLabel ?? 'Not a duration'"
                                    ></x-slot:value>
                                </x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Duration">
                                    <x-slot:value
                                        class="ndb:font-semibold ndb:tabular-nums"
                                        x-text="selectedTimelineItem.durationLabel ?? 'Point event'"
                                    ></x-slot:value>
                                </x-newdebugbar::inspector-fact>
                            </x-newdebugbar::inspector-facts>

                            <x-newdebugbar::inspector-source-fact label="Source" class="ndb:mt-3 ndb:sm:mt-4">
                                <x-slot:value>
                                    <template x-if="selectedTimelineItem.source">
                                        <x-newdebugbar::inspector-source-link
                                            @click="copyText(selectedTimelineItem.source)"
                                            ::title="selectedTimelineItem.source"
                                        >
                                            <x-slot:value x-text="selectedTimelineItem.source"></x-slot:value>
                                        </x-newdebugbar::inspector-source-link>
                                    </template>
                                    <template x-if="! selectedTimelineItem.source">
                                        <span>Not captured for this activity.</span>
                                    </template>
                                </x-slot:value>
                            </x-newdebugbar::inspector-source-fact>
                        </div>
                    </div>
                </template>
            </x-slot:detail>
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No timeline activity was captured for this request." />
    @endif
</div>

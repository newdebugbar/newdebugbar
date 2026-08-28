{{-- Presents application views first, with one active render detail and lazy data loading. --}}
@php
    $viewGroups = array_values(array_map(static function (array $group): array {
        $group['items'] = array_values(array_map(
            static fn (array $view): array => \Illuminate\Support\Arr::except($view, ['data']),
            $group['items'] ?? [],
        ));

        return $group;
    }, $section['payload']['groups'] ?? []));
    $viewSummary = $section['summary'];
    $viewRenderCount = (int) ($viewSummary['count'] ?? array_sum(array_column($viewGroups, 'count')));
    $viewApplicationCount = (int) ($viewSummary['application_count'] ?? $viewRenderCount);
    $viewFrameworkCount = (int) ($viewSummary['framework_count'] ?? 0);
    $viewApplicationViews = (int) ($viewSummary['application_views'] ?? count($viewGroups));
    $viewDefaultGroupCount = $viewApplicationViews > 0 ? $viewApplicationViews : count($viewGroups);
    $viewDefaultRenderCount = $viewApplicationViews > 0 ? $viewApplicationCount : $viewRenderCount;
@endphp

<div
    data-ndb-views
    x-init="initializeViews(JSON.parse(atob($el.querySelector('[data-ndb-view-payload]').textContent.trim())))"
    class="ndb:text-zinc-950 ndb:dark:text-white ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    <script type="application/json" data-ndb-view-payload>
        {{ base64_encode(\Illuminate\Support\Js::encode($viewGroups)) }}
    </script>

    @if ($viewGroups !== [])
        <x-newdebugbar::inspector-workspace
            frame="top"
            data-ndb-view-workspace
            class="ndb:border-l-0 ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
        >
            <x-newdebugbar::inspector-list-panel
                detail-open="viewDetailOpen"
                list-ref="viewGroups"
                data-ndb-view-list-panel
                class="ndb:border-l-0 ndb:bg-transparent ndb:p-0"
            >
                <x-slot:controls>
                    <x-newdebugbar::inspector-list-controls :show-search="true">
                        <x-slot:leading>
                            <p
                                data-ndb-view-summary
                                aria-live="polite"
                                aria-atomic="true"
                                class="ndb:min-w-0 ndb:text-xs ndb:text-zinc-700 ndb:dark:text-zinc-200"
                            >
                                <strong class="ndb:font-bold">
                                    <span
                                        data-ndb-view-visible-count
                                        x-text="visibleViewCount"
                                    >{{ number_format($viewDefaultGroupCount) }}</span>
                                    <span x-text="visibleViewCount === 1 ? 'view' : 'views'">{{ \Illuminate\Support\Str::plural('view', $viewDefaultGroupCount) }}</span>
                                </strong>
                                <span class="ndb:text-[11px] ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                    <span
                                        data-ndb-view-visible-render-count
                                        x-text="visibleViewRenderCount"
                                    >{{ number_format($viewDefaultRenderCount) }}</span>
                                    <span x-text="visibleViewRenderCount === 1 ? 'render' : 'renders'">{{ \Illuminate\Support\Str::plural('render', $viewDefaultRenderCount) }}</span>
                                    shown
                                </span>
                            </p>
                        </x-slot:leading>

                        <x-slot:search>
                            <x-newdebugbar::search-field
                                label="Search views"
                                placeholder="Search views or sources"
                                data-ndb-view-search
                                x-model="viewSearch"
                                @input.debounce.100ms="applyViewFilters()"
                                class="ndb:py-0"
                            />
                        </x-slot:search>

                        <x-slot:filter>
                            <x-newdebugbar::select-field
                                label="Filter views by origin"
                                data-ndb-view-filter
                                x-model="viewFilter"
                                @change="setViewFilter($event.target.value)"
                                class="ndb:py-0"
                            >
                                <option value="application">
                                    Application ({{ number_format($viewApplicationCount) }})
                                </option>
                                <option value="all">All ({{ number_format($viewRenderCount) }})</option>
                                <option value="framework">Framework ({{ number_format($viewFrameworkCount) }})</option>
                            </x-newdebugbar::select-field>
                        </x-slot:filter>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list
                    data-ndb-view-list
                    aria-label="Rendered views"
                    class="ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
                >
                    @foreach ($viewGroups as $group)
                        <button
                            type="button"
                            data-ndb-view-group="{{ $group['id'] }}"
                            data-ndb-view-origin="{{ $group['origin'] }}"
                            data-ndb-view-search-value="{{ $group['search'] }}"
                            data-ndb-view-count="{{ $group['count'] }}"
                            wire:key="view-group-{{ $group['id'] }}"
                            @click="selectViewGroup({{ \Illuminate\Support\Js::from($group['id']) }})"
                            :aria-pressed="viewSelected === {{ \Illuminate\Support\Js::from($group['id']) }}"
                            :class="viewSelected === {{ \Illuminate\Support\Js::from($group['id']) }}
                                ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                                : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
                            class="ndb:grid ndb:h-auto ndb:w-full ndb:min-w-0 ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-center ndb:gap-x-3 ndb:border-l-0 ndb:bg-transparent ndb:px-3 ndb:py-3 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500"
                        >
                            <span
                                data-ndb-view-list-name
                                class="ndb:min-w-0 ndb:truncate ndb:font-sans ndb:text-xs ndb:font-bold ndb:text-zinc-900 ndb:dark:text-zinc-100"
                            >{{ $group['display_name'] }}</span>
                            <span class="ndb:text-right ndb:text-xs ndb:font-bold ndb:tabular-nums">
                                {{ number_format($group['count']) }}
                            </span>
                            <span class="ndb:min-w-0 ndb:truncate ndb:text-[11px] ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                {{ $group['origin'] === 'application' ? 'Application' : 'Framework' }}
                            </span>
                            <span class="ndb:text-right ndb:text-[11px] ndb:font-medium ndb:text-zinc-400">
                                {{ \Illuminate\Support\Str::plural('render', $group['count']) }}
                            </span>
                        </button>
                    @endforeach

                    <div x-show.important="visibleViewCount === 0" class="ndb:p-3">
                        <x-newdebugbar::empty-state label="No views match this origin and search." />
                    </div>
                </x-slot:list>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::inspector-detail-pane
                detail-open="viewDetailOpen"
                detail-ref="viewDetail"
                detail-label="Selected view details"
                back-label="Views"
                close-action="closeViewDetail()"
                id="newdebugbar-view-detail"
                data-ndb-view-detail-pane
                class="ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
            >
                <x-slot:back>
                    <x-newdebugbar::inspector-detail-back
                        data-ndb-view-detail-back
                        @click="closeViewDetail()"
                        label="Views"
                        class="ndb:bg-transparent"
                    />
                </x-slot:back>

                <template x-if="selectedViewGroup">
                    <div
                        data-ndb-view-detail
                        class="ndb:flex ndb:min-h-0 ndb:flex-col ndb:border-l-0 ndb:bg-transparent ndb:p-0"
                    >
                        <x-newdebugbar::inspector-detail-header layout="wrap" data-ndb-view-detail-header>
                            <x-slot:title class="ndb:min-w-0">
                                <h3
                                    data-ndb-view-detail-name
                                    class="ndb:min-w-0 ndb:break-words ndb:font-sans ndb:text-sm ndb:font-bold ndb:leading-5 ndb:text-zinc-950 ndb:dark:text-white"
                                    x-text="selectedViewGroup.display_name"
                                ></h3>
                            </x-slot:title>

                            <x-slot:aside>
                                <template x-if="selectedViewGroup.items.length > 1">
                                    <x-newdebugbar::select-field
                                        label="Select rendered view instance"
                                        data-ndb-view-render-select
                                        ::value="viewRenderOrder"
                                        @change="
                                            selectViewRender(Number($event.target.value));
                                            if (viewDetailTab === 'data') loadSelectedViewData($wire);
                                        "
                                        class="ndb:w-32"
                                    >
                                        <template x-for="view in selectedViewGroup.items" :key="view.render_order">
                                            <option
                                                :value="view.render_order"
                                                x-text="`Render #${view.render_order}`"
                                            ></option>
                                        </template>
                                    </x-newdebugbar::select-field>
                                </template>
                            </x-slot:aside>

                            <x-slot:metadata data-ndb-view-detail-metadata class="ndb:gap-x-6 ndb:gap-y-2">
                                <div class="ndb:min-w-0">
                                    <dt class="ndb:text-zinc-400">Origin</dt>
                                    <dd
                                        class="ndb:font-semibold"
                                        x-text="
                                            selectedViewGroup.origin === 'application' ? 'Application' : 'Framework'
                                        "
                                    ></dd>
                                </div>
                                <div class="ndb:min-w-0">
                                    <dt class="ndb:text-zinc-400">Passed values</dt>
                                    <dd
                                        class="ndb:font-semibold ndb:tabular-nums"
                                        x-text="selectedViewRender.data_key_count"
                                    ></dd>
                                </div>
                                <template x-if="selectedViewRender.composer_count > 0">
                                    <div class="ndb:min-w-0" data-ndb-view-composer-count>
                                        <dt class="ndb:text-zinc-400">Composers</dt>
                                        <dd
                                            class="ndb:font-semibold ndb:tabular-nums"
                                            x-text="selectedViewRender.composer_count"
                                        ></dd>
                                    </div>
                                </template>
                            </x-slot:metadata>
                        </x-newdebugbar::inspector-detail-header>

                        <x-newdebugbar::inspector-detail-tabs label="View detail">
                            @foreach (['overview' => 'Overview', 'data' => 'Data'] as $tab => $label)
                                <x-newdebugbar::filter-tab
                                    variant="segmented"
                                    data-ndb-view-detail-tab="{{ $tab }}"
                                    @click="
                                        setViewDetailTab({{ \Illuminate\Support\Js::from($tab) }});
                                        if (viewDetailTab === 'data') loadSelectedViewData($wire);
                                    "
                                    role="tab"
                                    ::aria-selected="viewDetailTab === {{ \Illuminate\Support\Js::from($tab) }}"
                                    class="ndb:h-auto"
                                >
                                    {{ $label }}
                                </x-newdebugbar::filter-tab>
                            @endforeach
                        </x-newdebugbar::inspector-detail-tabs>

                        <template x-if="viewDetailTab === 'overview'">
                            <section
                                data-ndb-view-detail-panel="overview"
                                role="tabpanel"
                                class="ndb:space-y-4 ndb:border-l-0 ndb:bg-transparent ndb:p-4"
                            >
                                <x-newdebugbar::inspector-source-fact label="Render source">
                                    <x-slot:value>
                                        <x-newdebugbar::inspector-source-link
                                            data-ndb-view-copy-source
                                            aria-label="Copy template path"
                                            x-show.important="selectedViewRender.source_label"
                                            ::title="selectedViewRender.source_label"
                                            @click="copyText(selectedViewRender.source_label)"
                                        >
                                            <x-slot:value x-text="selectedViewRender.source_label"></x-slot:value>
                                        </x-newdebugbar::inspector-source-link>
                                        <span x-show.important="! selectedViewRender.source_label">Template source was not captured.</span>
                                    </x-slot:value>
                                </x-newdebugbar::inspector-source-fact>

                                <template x-if="selectedViewRender.composers.length > 0">
                                    <div data-ndb-view-composers>
                                        <h4 class="ndb:text-xs ndb:font-bold">View composers</h4>
                                        <ul class="ndb:mt-2 ndb:divide-y ndb:divide-zinc-200/80 ndb:border-y ndb:border-zinc-200/80 ndb:dark:divide-zinc-800 ndb:dark:border-zinc-800">
                                            <template
                                                x-for="composer in selectedViewRender.composers"
                                                :key="`${composer.name}:${composer.source_label ?? ''}`"
                                            >
                                                <li class="ndb:min-w-0 ndb:py-2.5">
                                                    <p
                                                        class="ndb:break-words ndb:text-xs ndb:font-semibold"
                                                        x-text="composer.name"
                                                    ></p>
                                                    <p
                                                        x-show.important="composer.source_label"
                                                        class="ndb:mt-0.5 ndb:break-all ndb:text-[11px] ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                                        x-text="composer.source_label"
                                                    ></p>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                            </section>
                        </template>

                        <template x-if="viewDetailTab === 'data'">
                            <section
                                data-ndb-view-detail-panel="data"
                                role="tabpanel"
                                class="ndb:min-h-0 ndb:border-l-0 ndb:bg-transparent ndb:p-4"
                            >
                                <div
                                    x-show.important="viewDataLoading"
                                    data-ndb-view-data-loading
                                    class="ndb:flex ndb:items-center ndb:gap-2 ndb:py-3 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                >
                                    <span class="ndb:size-1.5 ndb:rounded-full ndb:bg-indigo-500"></span>
                                    Loading render data…
                                </div>

                                <template x-if="viewDataLoaded && ! viewDataIsEmpty">
                                    <x-newdebugbar::code-block
                                        language="json"
                                        tabindex="0"
                                        data-ndb-view-data
                                        class="ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500"
                                    >
                                        <x-slot:value x-text="formattedViewData"></x-slot:value>
                                    </x-newdebugbar::code-block>
                                </template>

                                <template x-if="viewDataLoaded && viewDataIsEmpty">
                                    <x-newdebugbar::empty-state label="No data was passed directly to this render." />
                                </template>

                                <template x-if="viewDataError">
                                    <div class="ndb:flex ndb:flex-wrap ndb:items-center ndb:justify-between ndb:gap-3">
                                        <p class="ndb:text-xs ndb:font-semibold ndb:text-amber-700 ndb:dark:text-amber-300">
                                            Render data could not be loaded.
                                        </p>
                                        <x-newdebugbar::inspector-action
                                            icon="activity"
                                            data-ndb-view-data-retry
                                            @click="loadSelectedViewData($wire, true)"
                                        >
                                            Retry
                                        </x-newdebugbar::inspector-action>
                                    </div>
                                </template>
                            </section>
                        </template>
                    </div>
                </template>

                <x-newdebugbar::inspector-detail-empty
                    data-ndb-view-detail-empty
                    label="Select a view to inspect its renders, data, and source."
                    x-show.important="viewSelected === null"
                    class="ndb:flex-1"
                />
            </x-newdebugbar::inspector-detail-pane>
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No views were captured for this request." />
    @endif
</div>

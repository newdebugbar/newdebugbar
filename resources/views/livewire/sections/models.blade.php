{{-- Presents model activity as a compact model table with persistent details. --}}
@php
    $modelGroups = array_values($section['payload']['model_group_previews'] ?? $section['payload']['model_groups'] ?? []);
    $modelCount = count($modelGroups);
@endphp

<div
    data-ndb-models
    x-init="initializeModels({{ $modelCount }})"
    class="ndb:text-zinc-950 ndb:[&_code]:bg-transparent ndb:[&_dd]:bg-transparent ndb:[&_dl]:bg-transparent ndb:[&_dt]:bg-transparent ndb:dark:text-white ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    @if ($modelGroups !== [])
        <x-newdebugbar::inspector-workspace
            frame="top"
            data-ndb-model-workspace
            class="ndb:border-l-0 ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white ndb:lg:grid-cols-[minmax(20rem,0.72fr)_minmax(0,1.68fr)]"
        >
            <x-newdebugbar::inspector-list-panel detail-open="modelDetailOpen" list-ref="modelList">
                <x-slot:controls>
                    <x-newdebugbar::inspector-list-controls :show-search="true">
                        <x-slot:leading>
                            <p
                                data-ndb-model-summary
                                aria-live="polite"
                                aria-atomic="true"
                                class="ndb:min-w-0 ndb:text-xs ndb:text-zinc-700 ndb:dark:text-zinc-200"
                            >
                                <span data-ndb-model-summary-count class="ndb:font-bold">
                                    {{ number_format($modelCount) }} {{ \Illuminate\Support\Str::plural('model', $modelCount) }}
                                </span>
                                <span
                                    x-show.important="visibleModelCount !== modelGroupCount"
                                    class="ndb:ml-1 ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                >
                                    <span data-ndb-model-visible-count x-text="visibleModelCount"></span>
                                    shown
                                </span>
                            </p>
                        </x-slot:leading>

                        <x-slot:search>
                            <x-newdebugbar::search-field
                                label="Search models"
                                placeholder="Search models"
                                data-ndb-model-search
                                x-model="modelSearch"
                                @input.debounce.100ms="applyModelView()"
                            />
                        </x-slot:search>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list
                    data-ndb-model-list
                    class="ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
                >
                    <div
                        data-ndb-model-list-heading
                        class="ndb:sticky ndb:top-0 ndb:z-10 ndb:hidden ndb:grid-cols-[minmax(7rem,1fr)_3.5rem_2.75rem_3.75rem] ndb:gap-2 ndb:border-l-0 ndb:border-b ndb:border-zinc-200/90 ndb:bg-white/95 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:backdrop-blur-sm ndb:dark:border-zinc-800 ndb:dark:bg-zinc-950/95 ndb:sm:grid"
                    >
                        <span class="ndb:flex ndb:justify-start">
                            <x-newdebugbar::inspector-sort-heading
                                label="Model"
                                active="modelSort === 'model'"
                                direction="modelSortDirection"
                                data-ndb-model-sort-heading="model"
                                class="ndb:-ml-4"
                                @click="toggleModelSort('model')"
                            />
                        </span>
                        <span class="ndb:flex ndb:justify-end">
                            <x-newdebugbar::inspector-sort-heading
                                label="Retrieved"
                                align="right"
                                active="modelSort === 'retrieved'"
                                direction="modelSortDirection"
                                data-ndb-model-sort-heading="retrieved"
                                @click="toggleModelSort('retrieved')"
                            />
                        </span>
                        <span class="ndb:flex ndb:justify-end">
                            <x-newdebugbar::inspector-sort-heading
                                label="Writes"
                                align="right"
                                active="modelSort === 'writes'"
                                direction="modelSortDirection"
                                data-ndb-model-sort-heading="writes"
                                @click="toggleModelSort('writes')"
                            />
                        </span>
                        <span class="ndb:flex ndb:justify-end" title="Loads after the first for identified records">
                            <x-newdebugbar::inspector-sort-heading
                                label="Reloads"
                                align="right"
                                active="modelSort === 'reloads'"
                                direction="modelSortDirection"
                                data-ndb-model-sort-heading="reloads"
                                @click="toggleModelSort('reloads')"
                            />
                        </span>
                    </div>

                    @foreach ($modelGroups as $index => $group)
                        <x-newdebugbar::model-group :group="$group" :index="$index" />
                    @endforeach

                    <div x-show.important="visibleModelCount === 0" class="ndb:p-3">
                        <x-newdebugbar::empty-state label="No models match this search." />
                    </div>
                </x-slot:list>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::inspector-detail-pane
                detail-open="modelDetailOpen"
                detail-ref="modelDetail"
                detail-label="Selected model details"
                back-label="Models"
                close-action="closeModelDetail()"
                id="newdebugbar-model-detail"
                data-ndb-model-detail-pane
                class="ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
            >
                <x-slot:back>
                    <x-newdebugbar::inspector-detail-back
                        data-ndb-model-detail-back
                        @click="closeModelDetail()"
                        label="Models"
                        class="ndb:bg-transparent"
                    />
                </x-slot:back>

                @foreach ($modelGroups as $index => $group)
                    <template x-if="modelSelected === {{ $index }}">
                        <div wire:key="model-detail-{{ $index }}" class="ndb:flex ndb:flex-col">
                            <x-newdebugbar::model-group-detail :$group />
                        </div>
                    </template>
                @endforeach

                <x-newdebugbar::inspector-detail-empty
                    data-ndb-model-detail-empty
                    label="Select a model to inspect its activity."
                    x-show.important="modelSelected === null"
                    class="ndb:flex-1"
                />
            </x-newdebugbar::inspector-detail-pane>
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No Eloquent model activity was captured for this request." />
    @endif
</div>

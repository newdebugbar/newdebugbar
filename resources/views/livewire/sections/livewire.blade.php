{{-- Renders current-page Livewire activity and mounted components in one shared workspace. --}}
@php($livewirePayload = $section['payload'] ?? ['components' => [], 'activity' => []])

<div
    data-ndb-livewire
    x-init="mergeLivewireServer(JSON.parse(atob($el.querySelector('[data-ndb-livewire-payload]').textContent.trim())))"
    class="ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    <script type="application/json" data-ndb-livewire-payload>
        {{
            base64_encode(json_encode([
                'components' => $livewirePayload['components'] ?? [],
                'activity' => $livewirePayload['activity'] ?? [],
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE))
        }}
    </script>

    <div
        x-show.important="(livewireTrace.dropped?.components ?? 0) + (livewireTrace.dropped?.activity ?? 0) > 0"
        role="status"
        class="ndb:mb-3 ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:sm:mb-4 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
    >
        Capture limit reached.
        <span x-text="livewireTrace.dropped.activity"></span> activity records and
        <span x-text="livewireTrace.dropped.components"></span> component records were omitted.
    </div>

    <x-newdebugbar::inspector-workspace
        frame="top"
        data-ndb-livewire-workspace
        class="ndb:border-l-0 ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
    >
        <x-newdebugbar::inspector-list-panel detail-open="livewireDetailOpen" list-ref="livewireList">
            <x-slot:controls>
                @include('newdebugbar::livewire.livewire.controls')
            </x-slot:controls>

            <x-slot:list data-ndb-livewire-list class="ndb:divide-y-0 ndb:p-0">
                <template x-if="livewireTab === 'activity'">
                    <div data-ndb-livewire-activity>
                        @include('newdebugbar::livewire.livewire.activity')
                    </div>
                </template>

                <template x-if="livewireTab === 'components'">
                    <div data-ndb-livewire-components>
                        @include('newdebugbar::livewire.livewire.components')
                    </div>
                </template>
            </x-slot:list>
        </x-newdebugbar::inspector-list-panel>

        <x-newdebugbar::inspector-detail-pane
            detail-open="livewireDetailOpen"
            detail-ref="livewireDetail"
            detail-label="Selected Livewire details"
            back-label="Livewire"
            close-action="livewireDetailOpen = false"
            id="newdebugbar-livewire-detail"
            data-ndb-livewire-detail-pane
            class="ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
        >
            <x-slot:back>
                <x-newdebugbar::inspector-detail-back
                    data-ndb-livewire-detail-back
                    @click="livewireDetailOpen = false"
                    label="Livewire"
                    class="ndb:bg-transparent"
                />
            </x-slot:back>

            <template x-if="livewireTab === 'activity'">
                <div class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col">
                    <template x-if="selectedLivewireActivity">
                        @include('newdebugbar::livewire.livewire.activity-detail')
                    </template>

                    <x-newdebugbar::inspector-detail-empty
                        data-ndb-livewire-activity-detail-empty="selection"
                        label="Choose an interaction to inspect what changed."
                        x-show.important="! selectedLivewireActivity && filteredLivewireActivity.length > 0"
                        class="ndb:flex-1"
                    />
                    <x-newdebugbar::inspector-detail-empty
                        data-ndb-livewire-activity-detail-empty="filter"
                        label="No activity matches this view."
                        x-show.important="filteredLivewireActivity.length === 0"
                        class="ndb:flex-1"
                    />
                </div>
            </template>

            <template x-if="livewireTab === 'components'">
                <div class="ndb:flex ndb:min-h-0 ndb:flex-1 ndb:flex-col">
                    <template x-if="selectedLivewireComponent">
                        @include('newdebugbar::livewire.livewire.component-detail')
                    </template>

                    <x-newdebugbar::inspector-detail-empty
                        data-ndb-livewire-component-detail-empty="selection"
                        label="Choose a mounted component to inspect its state."
                        x-show.important="! selectedLivewireComponent && matchingLivewireComponents.length > 0"
                        class="ndb:flex-1"
                    />
                    <x-newdebugbar::inspector-detail-empty
                        data-ndb-livewire-component-detail-empty="filter"
                        label="No components match this search."
                        x-show.important="matchingLivewireComponents.length === 0"
                        class="ndb:flex-1"
                    />
                </div>
            </template>
        </x-newdebugbar::inspector-detail-pane>
    </x-newdebugbar::inspector-workspace>
</div>

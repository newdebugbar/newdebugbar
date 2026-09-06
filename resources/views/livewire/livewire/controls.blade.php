<div>
    @include('newdebugbar::livewire.livewire.view-tabs')

    <div class="ndb:mt-3">
        <template x-if="livewireTab === 'activity'">
            <x-newdebugbar::inspector-list-controls :show-search="true">
                <x-slot:search>
                    <x-newdebugbar::search-field
                        label="Search Livewire activity"
                        placeholder="Search activity"
                        data-ndb-livewire-search
                        x-model="livewireSearch"
                        @input.debounce.100ms="syncLivewireSelection()"
                    />
                </x-slot:search>

                <x-slot:filter>
                    <x-newdebugbar::select-field
                        label="Filter Livewire activity"
                        data-ndb-livewire-type
                        x-model="livewireActivityType"
                        @change="setLivewireActivityType($event.target.value)"
                    >
                        <option value="all">All activity</option>
                        <template x-for="type in livewireActivityTypes" :key="type">
                            <option
                                :value="type"
                                x-text="type.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())"
                            ></option>
                        </template>
                    </x-newdebugbar::select-field>
                </x-slot:filter>
            </x-newdebugbar::inspector-list-controls>
        </template>

        <template x-if="livewireTab === 'components'">
            <x-newdebugbar::inspector-list-controls :show-search="true">
                <x-slot:search>
                    <x-newdebugbar::search-field
                        label="Search mounted components"
                        placeholder="Search components"
                        data-ndb-livewire-search
                        x-model="livewireSearch"
                        @input.debounce.100ms="syncLivewireSelection()"
                    />
                </x-slot:search>
            </x-newdebugbar::inspector-list-controls>
        </template>
    </div>
</div>

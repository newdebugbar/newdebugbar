<x-newdebugbar::filter-tabs label="Livewire view" variant="segmented" class="ndb:w-full">
    <x-newdebugbar::filter-tab
        variant="segmented"
        data-ndb-livewire-tab="activity"
        @click="setLivewireTab('activity')"
        ::aria-pressed="livewireTab === 'activity'"
    >
        <span>Activity</span>
        <span class="ndb:text-xs ndb:font-bold ndb:tabular-nums ndb:opacity-65" x-text="livewireActivity.length"></span>
    </x-newdebugbar::filter-tab>
    <x-newdebugbar::filter-tab
        variant="segmented"
        data-ndb-livewire-tab="components"
        @click="setLivewireTab('components')"
        ::aria-pressed="livewireTab === 'components'"
    >
        <span>Components</span>
        <span
            class="ndb:text-xs ndb:font-bold ndb:tabular-nums ndb:opacity-65"
            x-text="livewireComponents.length"
        ></span>
    </x-newdebugbar::filter-tab>
</x-newdebugbar::filter-tabs>

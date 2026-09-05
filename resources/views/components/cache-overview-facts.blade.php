<x-newdebugbar::inspector-facts columns="4" :bordered="false" data-ndb-cache-metadata>
    <x-newdebugbar::inspector-fact label="Result">
        <x-slot:value
            ::class="selectedCacheOperation.failed
                ? 'ndb:text-red-700 ndb:dark:text-red-300'
                : selectedCacheOperation.result === 'miss' || selectedCacheOperation.result === 'flushed'
                  ? 'ndb:text-amber-700 ndb:dark:text-amber-300'
                  : ''"
            class="ndb:truncate"
            x-text="selectedCacheOperation.result_label"
        ></x-slot:value>
    </x-newdebugbar::inspector-fact>
    <x-newdebugbar::inspector-fact label="Runtime">
        <x-slot:value
            class="ndb:truncate ndb:tabular-nums"
            x-text="selectedCacheOperation.duration_label"
        ></x-slot:value>
    </x-newdebugbar::inspector-fact>
    <x-newdebugbar::inspector-fact label="Store">
        <x-slot:value
            ::title="selectedCacheOperation.store_label"
            class="ndb:truncate"
            x-text="selectedCacheOperation.store_label"
        ></x-slot:value>
    </x-newdebugbar::inspector-fact>
    <x-newdebugbar::inspector-fact label="Driver" x-show.important="selectedCacheOperation.driver_label">
        <x-slot:value
            ::title="selectedCacheOperation.driver_label"
            class="ndb:truncate"
            x-text="selectedCacheOperation.driver_label"
        ></x-slot:value>
    </x-newdebugbar::inspector-fact>
</x-newdebugbar::inspector-facts>

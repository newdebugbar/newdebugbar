<div data-ndb-cache-detail-content class="ndb:flex ndb:flex-col">
    <div class="ndb:p-3 ndb:sm:p-4">
        <x-newdebugbar::cache-overview-facts />

        <template x-if="selectedCacheOperation.has_value">
            <x-newdebugbar::inspector-evidence label="Value" data-ndb-cache-value class="ndb:mt-4">
                <x-slot:value x-text="selectedCacheOperation.value_display"></x-slot:value>
            </x-newdebugbar::inspector-evidence>
        </template>

        <x-newdebugbar::inspector-definition-list
            x-show.important="
                ['write', 'write_failed'].includes(selectedCacheOperation.operation) ||
                selectedCacheOperation.duration_scope === 'batch' ||
                (selectedCacheOperation.failed && selectedCacheOperation.exception_message)
            "
            class="ndb:mt-3 ndb:border-t ndb:border-zinc-200/90 ndb:pt-3 ndb:sm:mt-4 ndb:sm:pt-4 ndb:dark:border-zinc-800"
        >
            <x-newdebugbar::inspector-definition-row
                label="Lifetime"
                x-show.important="['write', 'write_failed'].includes(selectedCacheOperation.operation)"
            >
                <x-slot:value x-text="selectedCacheOperation.lifetime_label"></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Timing context"
                x-show.important="selectedCacheOperation.duration_scope === 'batch'"
            >
                <x-slot:value x-text="'Shared across a batch of ' + selectedCacheOperation.batch_size + ' operations.'"></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
            <x-newdebugbar::inspector-definition-row
                label="Failure"
                tone="danger"
                x-show.important="selectedCacheOperation.failed && selectedCacheOperation.exception_message"
            >
                <x-slot:value x-text="selectedCacheOperation.exception_message"></x-slot:value>
            </x-newdebugbar::inspector-definition-row>
        </x-newdebugbar::inspector-definition-list>
    </div>

    <x-newdebugbar::inspector-source-panel
        frames="selectedCacheOperation.stack ?? []"
        reset-on="selectedCacheOperation.execution"
        data-ndb-cache-source
        class="ndb:border-t ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
    >
        <x-newdebugbar::inspector-source-fact label="Source">
            <x-slot:value>
                <template x-if="selectedCacheOperation.callsite?.file">
                    <x-newdebugbar::inspector-source-link
                        ::aria-label="'Copy source ' + selectedCacheOperation.source_label"
                        @click="copyText(selectedCacheOperation.source_label)"
                    >
                        <x-slot:value
                            ::title="selectedCacheOperation.source_label"
                            x-text="selectedCacheOperation.source_label"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-source-link>
                </template>
                <template x-if="! selectedCacheOperation.callsite?.file">
                    <span x-text="selectedCacheOperation.source_label"></span>
                </template>
            </x-slot:value>
        </x-newdebugbar::inspector-source-fact>
    </x-newdebugbar::inspector-source-panel>
</div>

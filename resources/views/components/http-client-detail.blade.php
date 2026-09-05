<x-newdebugbar::inspector-detail-pane
    detail-open="httpClientDetailOpen"
    detail-ref="httpClientDetail"
    detail-label="Selected outbound HTTP request details"
    back-label="Requests"
    close-action="httpClientDetailOpen = false"
    data-ndb-http-client-detail
>
    <x-slot:back>
        <x-newdebugbar::inspector-detail-back
            data-ndb-http-client-detail-back
            @click="httpClientDetailOpen = false"
            label="Requests"
        />
    </x-slot:back>

    <template x-if="selectedHttpClientRequest">
        <div class="ndb:flex ndb:flex-col">
            <x-newdebugbar::http-client-header />
            <x-newdebugbar::http-client-detail-tabs />

            <div>
                <template x-if="httpClientDetailTab === 'response'">
                    <x-newdebugbar::http-client-response-panel />
                </template>

                <template x-if="httpClientDetailTab === 'request'">
                    <x-newdebugbar::http-client-request-panel />
                </template>

                <template x-if="selectedHttpClientRequest.has_source">
                    <x-newdebugbar::inspector-source-panel
                        frames="selectedHttpClientRequest.stack ?? []"
                        reset-on="selectedHttpClientRequest.execution + ':' + httpClientDetailTab"
                        data-ndb-http-client-source-facts
                    >
                        <template x-if="selectedHttpClientRequest.callsite_label">
                            <x-newdebugbar::inspector-source-fact label="Source" data-ndb-http-client-primary-source>
                                <x-slot:value>
                                    <x-newdebugbar::inspector-source-link
                                        ::aria-label="'Copy source ' + selectedHttpClientRequest.callsite_label"
                                        @click="copyText(selectedHttpClientRequest.callsite_label)"
                                    >
                                        <x-slot:value
                                            data-ndb-http-client-detail-source
                                            ::title="selectedHttpClientRequest.callsite_label"
                                            x-text="selectedHttpClientRequest.callsite_label"
                                        ></x-slot:value>
                                    </x-newdebugbar::inspector-source-link>
                                </x-slot:value>
                            </x-newdebugbar::inspector-source-fact>
                        </template>
                    </x-newdebugbar::inspector-source-panel>
                </template>
            </div>
        </div>
    </template>

    <x-newdebugbar::inspector-detail-empty
        label="Choose a request to inspect its evidence."
        x-show.important="! selectedHttpClientRequest"
    />
</x-newdebugbar::inspector-detail-pane>

<div data-ndb-http-client-detail-panel="request" class="ndb:p-3 ndb:sm:p-4">
    <div class="ndb:flex ndb:flex-wrap ndb:items-center ndb:justify-between ndb:gap-3">
        <x-newdebugbar::inspector-facts :bordered="false" layout="inline" data-ndb-http-client-request-facts>
            <x-newdebugbar::inspector-fact
                label="Request size"
                x-show.important="selectedHttpClientRequest.request_body_size_label !== '—'"
            >
                <x-slot:value
                    class="ndb:tabular-nums"
                    x-text="selectedHttpClientRequest.request_body_size_label"
                ></x-slot:value>
            </x-newdebugbar::inspector-fact>
        </x-newdebugbar::inspector-facts>

        <x-newdebugbar::inspector-action
            icon="code"
            data-ndb-http-client-copy-curl
            @click="copyText(selectedHttpClientRequest.curl)"
        >
            Copy safe cURL
        </x-newdebugbar::inspector-action>
    </div>

    <template x-if="selectedHttpClientRequest.request_has_headers || selectedHttpClientRequest.request_has_body">
        <div class="ndb:mt-4 ndb:space-y-4">
            <template x-if="selectedHttpClientRequest.request_has_body">
                <div>
                    <x-newdebugbar::inspector-evidence label="Request body" data-ndb-http-client-body="request">
                        <x-slot:aside>
                            <x-newdebugbar::inspector-action
                                icon="copy"
                                data-ndb-http-client-copy-body="request"
                                @click="copyText(formatHttpClientEvidence(selectedHttpClientRequest.request?.body))"
                            >
                                Copy body
                            </x-newdebugbar::inspector-action>
                        </x-slot:aside>
                        <x-slot:value x-text="formatHttpClientEvidence(selectedHttpClientRequest.request?.body)"></x-slot:value>
                    </x-newdebugbar::inspector-evidence>
                    <p
                        data-ndb-http-client-capture-limit="request"
                        x-show.important="
                            /\[maximum depth reached\]|&quot;__truncated__&quot;\s*:/.test(
                                JSON.stringify(selectedHttpClientRequest.request?.body ?? null),
                            )
                        "
                        class="ndb:mt-2 ndb:text-xs ndb:leading-5"
                    >
                        Some body values were omitted at the capture limits.
                    </p>
                </div>
            </template>
            <template x-if="selectedHttpClientRequest.request_has_headers">
                <x-newdebugbar::inspector-disclosure
                    label="Request headers"
                    reset-on="selectedHttpClientRequest.execution + ':' + httpClientDetailTab"
                    data-ndb-http-client-headers="request"
                >
                    <x-slot:count
                        x-show.important="
                            selectedHttpClientRequest.request?.headers &&
                            typeof selectedHttpClientRequest.request.headers === 'object'
                        "
                        x-text="
                            Object.keys(selectedHttpClientRequest.request?.headers ?? {}).filter(
                                (name) => name !== '__truncated__',
                            ).length
                        "
                    ></x-slot:count>
                    <x-newdebugbar::inspector-evidence>
                        <x-slot:value x-text="formatHttpClientEvidence(selectedHttpClientRequest.request?.headers)"></x-slot:value>
                    </x-newdebugbar::inspector-evidence>
                </x-newdebugbar::inspector-disclosure>
            </template>
        </div>
    </template>
</div>

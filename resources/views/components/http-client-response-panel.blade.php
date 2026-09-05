<div data-ndb-http-client-detail-panel="response" class="ndb:p-3 ndb:sm:p-4">
    <div data-ndb-http-client-response-facts class="ndb:space-y-2">
        <div class="ndb:flex ndb:flex-wrap ndb:items-center ndb:gap-x-4 ndb:gap-y-2">
            <p
                data-ndb-http-client-detail-status
                aria-label="Status"
                :class="selectedHttpClientRequest.failed ? 'ndb:text-red-700 ndb:dark:text-red-300' : ''"
                class="ndb:break-words ndb:text-base ndb:font-bold"
                x-text="selectedHttpClientRequest.status_label"
            ></p>
            <x-newdebugbar::inspector-facts :bordered="false" layout="inline">
                <x-newdebugbar::inspector-fact label="Duration">
                    <x-slot:value
                        data-ndb-http-client-detail-runtime
                        ::title="selectedHttpClientRequest.timing_summary"
                        ::class="selectedHttpClientRequest.slow ? 'ndb:text-amber-700 ndb:dark:text-amber-300' : ''"
                        class="ndb:tabular-nums"
                        x-text="selectedHttpClientRequest.duration_label"
                    ></x-slot:value>
                </x-newdebugbar::inspector-fact>
                <template
                    x-for="
                        [name, values] in
                        Object.entries(selectedHttpClientRequest.response?.headers ?? {}).filter(
                            ([name]) => name.toLowerCase() === 'content-type',
                        )
                    "
                    :key="name"
                >
                    <x-newdebugbar::inspector-fact label="Content type">
                        <x-slot:value
                            class="ndb:break-all"
                            x-text="Array.isArray(values) ? values.join(', ') : values"
                        ></x-slot:value>
                    </x-newdebugbar::inspector-fact>
                </template>
                <x-newdebugbar::inspector-fact
                    label="Response size"
                    x-show.important="
                        selectedHttpClientRequest.response && selectedHttpClientRequest.response_body_size_label !== '—'
                    "
                >
                    <x-slot:value
                        class="ndb:tabular-nums"
                        x-text="selectedHttpClientRequest.response_body_size_label"
                    ></x-slot:value>
                </x-newdebugbar::inspector-fact>
            </x-newdebugbar::inspector-facts>
        </div>
        <x-newdebugbar::inspector-facts
            :bordered="false"
            layout="inline"
            x-show.important="selectedHttpClientRequest.redirect_location"
        >
            <x-newdebugbar::inspector-fact label="Redirect to">
                <x-slot:value class="ndb:break-all" x-text="selectedHttpClientRequest.redirect_location"></x-slot:value>
            </x-newdebugbar::inspector-fact>
        </x-newdebugbar::inspector-facts>
    </div>

    <template x-if="selectedHttpClientRequest.response_has_headers || selectedHttpClientRequest.response_has_body">
        <div class="ndb:mt-4 ndb:space-y-4">
            <template x-if="selectedHttpClientRequest.response_has_body">
                <div>
                    <x-newdebugbar::inspector-evidence label="Response body" data-ndb-http-client-body="response">
                        <x-slot:aside>
                            <x-newdebugbar::inspector-action
                                icon="copy"
                                data-ndb-http-client-copy-body="response"
                                @click="copyText(formatHttpClientEvidence(selectedHttpClientRequest.response?.body))"
                            >
                                Copy body
                            </x-newdebugbar::inspector-action>
                        </x-slot:aside>
                        <x-slot:value x-text="formatHttpClientEvidence(selectedHttpClientRequest.response?.body)"></x-slot:value>
                    </x-newdebugbar::inspector-evidence>
                    <p
                        data-ndb-http-client-capture-limit="response"
                        x-show.important="
                            /\[maximum depth reached\]|&quot;__truncated__&quot;\s*:/.test(
                                JSON.stringify(selectedHttpClientRequest.response?.body ?? null),
                            )
                        "
                        class="ndb:mt-2 ndb:text-xs ndb:leading-5"
                    >
                        Some body values were omitted at the capture limits.
                    </p>
                </div>
            </template>
            <template x-if="selectedHttpClientRequest.response_has_headers">
                <x-newdebugbar::inspector-disclosure
                    label="Response headers"
                    reset-on="selectedHttpClientRequest.execution + ':' + httpClientDetailTab"
                    data-ndb-http-client-headers="response"
                >
                    <x-slot:count
                        x-show.important="
                            selectedHttpClientRequest.response?.headers &&
                            typeof selectedHttpClientRequest.response.headers === 'object'
                        "
                        x-text="
                            Object.keys(selectedHttpClientRequest.response?.headers ?? {}).filter(
                                (name) => name !== '__truncated__',
                            ).length
                        "
                    ></x-slot:count>
                    <x-newdebugbar::inspector-evidence>
                        <x-slot:value x-text="formatHttpClientEvidence(selectedHttpClientRequest.response?.headers)"></x-slot:value>
                    </x-newdebugbar::inspector-evidence>
                </x-newdebugbar::inspector-disclosure>
            </template>
        </div>
    </template>

    <x-newdebugbar::http-client-no-response />
</div>

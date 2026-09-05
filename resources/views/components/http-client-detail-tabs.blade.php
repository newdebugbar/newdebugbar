<x-newdebugbar::inspector-detail-tabs label="Outbound HTTP request detail">
    @foreach (['response' => 'Response', 'request' => 'Request'] as $tab => $label)
        <x-newdebugbar::filter-tab
            variant="segmented"
            data-ndb-http-client-detail-tab="{{ $tab }}"
            @click="setHttpClientDetailTab({{ \Illuminate\Support\Js::from($tab) }})"
            ::aria-pressed="httpClientDetailTab === {{ \Illuminate\Support\Js::from($tab) }}"
        >
            {{ $label }}
        </x-newdebugbar::filter-tab>
    @endforeach
</x-newdebugbar::inspector-detail-tabs>

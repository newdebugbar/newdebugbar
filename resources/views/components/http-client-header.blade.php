<x-newdebugbar::inspector-detail-header layout="wrap" data-ndb-http-client-header>
    <x-slot:title>
        <div class="ndb:grid ndb:min-w-0 ndb:flex-1 ndb:grid-cols-[3rem_minmax(0,1fr)] ndb:items-center ndb:gap-x-2 ndb:gap-y-1">
            <x-newdebugbar::inspector-operation-badge
                outlined
                data-ndb-http-client-detail-method
                x-text="selectedHttpClientRequest.method"
            ></x-newdebugbar::inspector-operation-badge>
            <h3
                data-ndb-http-client-detail-host
                :title="selectedHttpClientRequest.host"
                class="ndb:min-w-0 ndb:break-all ndb:text-sm ndb:font-bold ndb:leading-5"
                x-text="selectedHttpClientRequest.host"
            ></h3>
            <p
                data-ndb-http-client-detail-path
                :title="selectedHttpClientRequest.url"
                class="ndb:col-span-2 ndb:min-w-0 ndb:break-all ndb:text-xs ndb:leading-5"
                x-text="
                    selectedHttpClientRequest.path +
                    (selectedHttpClientRequest.query ? '?' + selectedHttpClientRequest.query : '')
                "
            ></p>
        </div>
    </x-slot:title>

    <x-slot:aside>
        <x-newdebugbar::inspector-action
            icon="link"
            data-ndb-http-client-copy-url
            @click="copyText(selectedHttpClientRequest.url)"
            class="ndb:shrink-0"
        >
            Copy URL
        </x-newdebugbar::inspector-action>
    </x-slot:aside>
</x-newdebugbar::inspector-detail-header>

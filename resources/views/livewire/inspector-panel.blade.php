{{-- Renders the one collector inspector selected by the inspector. --}}
@if ($profile !== [] && is_array($inspector))
    <div
        data-ndb-loaded-inspector="{{ $inspectorKey }}"
        x-data="createInspector(@js($inspectorKey), @js($profileId))"
        wire:key="profile-inspector-{{ $profileId }}-{{ $inspectorKey }}"
        x-cloak
        x-show.important="loadedInspector === @js($inspectorKey) || requestedInspector === @js($inspectorKey)"
        @class([
            'ndb:px-3 ndb:py-3 ndb:sm:px-0 ndb:sm:py-6 ndb:lg:min-h-0',
            'ndb:lg:shrink-0' => $inspectorKey === 'request',
            'ndb:lg:flex-1' => $inspectorKey !== 'request',
        ])
    >
        <section
            data-ndb-inspector-panel="{{ $inspectorKey }}"
            class="ndb:space-y-3 ndb:sm:space-y-4 ndb:lg:flex ndb:lg:h-full ndb:lg:min-h-0 ndb:lg:flex-col ndb:lg:gap-4 ndb:lg:space-y-0"
        >
            @php($collectionDropped = (int) ($inspector['summary']['dropped_count'] ?? 0))
            @php($collectionRetained = (int) ($inspector['summary']['retained_count'] ?? count($inspector['payload']['items'] ?? [])))
            @php($collectionTotal = (int) ($inspector['summary']['count'] ?? ($collectionRetained + $collectionDropped)))
            @if ($inspectorKey !== 'notifications' && $collectionDropped > 0)
                <div
                    data-ndb-collection-status="{{ $inspectorKey }}"
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Showing {{ number_format($collectionRetained) }} of {{ number_format($collectionTotal) }} {{ strtolower($inspector['label']) }}.
                </div>
            @endif
            @if ($inspectorKey === 'notifications' && $collectionDropped > 0)
                <div
                    data-ndb-collection-status="notifications"
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Showing {{ number_format($collectionRetained) }} of {{ number_format((int) ($inspector['summary']['delivery_count'] ?? $collectionTotal)) }} channel
                    attempts.
                </div>
            @endif
            @if ($inspectorKey === 'queries' && (int) ($inspector['summary']['transaction_dropped_count'] ?? 0) > 0)
                <div
                    data-ndb-collection-status="query-transactions"
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Showing {{ number_format((int) ($inspector['summary']['transaction_retained_count'] ?? count($inspector['payload']['transactions'] ?? []))) }} of {{ number_format((int) ($inspector['summary']['transaction_count'] ?? 0)) }} query
                    transaction events.
                </div>
            @endif
            @if ($inspectorKey === 'timeline' && ($inspector['payload']['incomplete'] ?? false))
                <div
                    data-ndb-timeline-incomplete
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Timeline incomplete: {{ number_format((int) ($inspector['payload']['omitted_count'] ?? 0)) }} source
                    events were omitted.
                </div>
            @endif
            @includeFirst(['newdebugbar::livewire.inspectors.'.$inspectorKey, 'newdebugbar::livewire.inspectors.default'])
        </section>
    </div>
@else
    <div data-ndb-inspector-expired class="ndb:p-8 ndb:text-center">
        <p class="ndb:text-sm ndb:font-semibold">This request is no longer available.</p>
        <p class="ndb:mt-1 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
            It may have expired or been cleared.
        </p>
        <p class="ndb:mt-3 ndb:text-xs ndb:font-semibold">Reload the page to capture a new request.</p>
    </div>
@endif

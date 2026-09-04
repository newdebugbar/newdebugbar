{{-- Renders the one collector section selected by the inspector. --}}
@if ($profile !== [] && is_array($section))
    <div
        data-ndb-loaded-section="{{ $sectionKey }}"
        wire:key="profile-section-{{ $profileId }}-{{ $sectionKey }}"
        x-cloak
        x-show.important="loadedSection === @js($sectionKey) || requestedSection === @js($sectionKey)"
        @class([
            'ndb:px-3 ndb:py-3 ndb:sm:px-0 ndb:sm:py-6 ndb:lg:min-h-0',
            'ndb:lg:shrink-0' => $sectionKey === 'request',
            'ndb:lg:flex-1' => $sectionKey !== 'request',
        ])
    >
        <section
            data-ndb-section-panel="{{ $sectionKey }}"
            class="ndb:space-y-3 ndb:sm:space-y-4 ndb:lg:flex ndb:lg:h-full ndb:lg:min-h-0 ndb:lg:flex-col ndb:lg:gap-4 ndb:lg:space-y-0"
        >
            @php($collectionDropped = (int) ($section['summary']['dropped_count'] ?? 0))
            @php($collectionRetained = (int) ($section['summary']['retained_count'] ?? count($section['payload']['items'] ?? [])))
            @php($collectionTotal = (int) ($section['summary']['count'] ?? ($collectionRetained + $collectionDropped)))
            @if ($sectionKey !== 'notifications' && $collectionDropped > 0)
                <div
                    data-ndb-collection-status="{{ $sectionKey }}"
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Showing {{ number_format($collectionRetained) }} of {{ number_format($collectionTotal) }} {{ strtolower($section['label']) }}.
                </div>
            @endif
            @if ($sectionKey === 'notifications' && $collectionDropped > 0)
                <div
                    data-ndb-collection-status="notifications"
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Showing {{ number_format($collectionRetained) }} of {{ number_format((int) ($section['summary']['delivery_count'] ?? $collectionTotal)) }} channel
                    attempts.
                </div>
            @endif
            @if ($sectionKey === 'queries' && (int) ($section['summary']['transaction_dropped_count'] ?? 0) > 0)
                <div
                    data-ndb-collection-status="query-transactions"
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Showing {{ number_format((int) ($section['summary']['transaction_retained_count'] ?? count($section['payload']['transactions'] ?? []))) }} of {{ number_format((int) ($section['summary']['transaction_count'] ?? 0)) }} query
                    transaction events.
                </div>
            @endif
            @if ($sectionKey === 'timeline' && ($section['payload']['incomplete'] ?? false))
                <div
                    data-ndb-timeline-incomplete
                    role="status"
                    class="ndb:rounded-lg ndb:border ndb:border-amber-200 ndb:bg-amber-50/60 ndb:px-3 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-amber-800 ndb:dark:border-amber-950 ndb:dark:bg-amber-950/25 ndb:dark:text-amber-300"
                >
                    Timeline incomplete: {{ number_format((int) ($section['payload']['omitted_count'] ?? 0)) }} source
                    events were omitted.
                </div>
            @endif
            @includeFirst(['newdebugbar::livewire.sections.'.$sectionKey, 'newdebugbar::livewire.sections.default'])
        </section>
    </div>
@else
    <div data-ndb-section-expired class="ndb:p-8 ndb:text-center">
        <p class="ndb:text-sm ndb:font-semibold">This request is no longer available.</p>
        <p class="ndb:mt-1 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
            It may have expired or been cleared.
        </p>
        <p class="ndb:mt-3 ndb:text-xs ndb:font-semibold">Reload the page to capture a new request.</p>
    </div>
@endif

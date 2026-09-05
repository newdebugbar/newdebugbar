@props(['item', 'index'])

@php
    $fieldCount = count($item['fields'] ?? []);
    $failureLabel = $fieldCount.' '.\Illuminate\Support\Str::plural('field', $fieldCount).' failed validation';
    $fromPreviousRequest = (bool) ($item['from_previous_request'] ?? false);
    $sessionExplanation = "These messages came from Laravel's session, usually after a redirect. Failed rules and source code are not available on this request.";
    $callsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;
    $responseStatus = isset($item['response_status']) ? (int) $item['response_status'] : null;
    $responseLabel = $responseStatus !== null && $responseStatus >= 300 && $responseStatus < 400
        ? 'Redirect '.$responseStatus
        : ($responseStatus === null ? null : 'Response '.$responseStatus);
@endphp

<article data-ndb-validation-item="{{ $index }}" wire:key="validation-{{ $index }}" class="ndb:min-w-0">
    <header class="ndb:flex ndb:flex-col ndb:items-start ndb:gap-2 ndb:p-3 ndb:sm:flex-row ndb:sm:gap-3 ndb:sm:p-4">
        <div class="ndb:min-w-0 ndb:flex-1">
            <h3 class="ndb:text-sm ndb:font-bold">{{ $failureLabel }}</h3>
            <p class="ndb:mt-0.5 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                {{ $fromPreviousRequest ? 'Carried from the previous request.' : ($item['exception_message'] ?? 'Laravel rejected the submitted data.') }}
            </p>
        </div>
        <div class="ndb:flex ndb:w-full ndb:max-w-full ndb:flex-wrap ndb:gap-x-3 ndb:gap-y-1 ndb:text-xs ndb:font-semibold ndb:text-zinc-500 ndb:sm:w-auto ndb:dark:text-zinc-400">
            @if ($fromPreviousRequest)
                <span class="ndb:text-amber-700 ndb:dark:text-amber-300">Previous request</span>
            @endif
            <span>{{ $item['error_bag'] }} bag</span>
            @if (($item['exception_status'] ?? null) !== null)
                <span class="ndb:tabular-nums">Validation {{ $item['exception_status'] }}</span>
            @endif
            @if ($responseLabel !== null)
                <span class="ndb:tabular-nums">{{ $responseLabel }}</span>
            @endif
        </div>
    </header>

    @if ($callsite !== null)
        <div class="ndb:border-t ndb:border-zinc-200/90 ndb:px-3 ndb:py-2.5 ndb:sm:px-4 ndb:dark:border-zinc-800">
            <x-newdebugbar::inspector-source-link
                data-ndb-validation-callsite="{{ $index }}"
                :copy="$callsite['file'].':'.$callsite['line']"
                aria-label="Copy validation source"
            >
                {{ $callsite['file'] }}:{{ $callsite['line'] }}
            </x-newdebugbar::inspector-source-link>
        </div>
    @endif

    <div
        role="table"
        aria-label="{{ $failureLabel }}"
        data-ndb-validation-table
        class="ndb:border-t ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
    >
        <div
            role="row"
            data-ndb-validation-table-header
            class="ndb:hidden ndb:grid-cols-[minmax(8rem,0.8fr)_minmax(14rem,2fr)_minmax(9rem,1fr)] ndb:gap-4 ndb:border-b ndb:border-zinc-200/90 ndb:bg-zinc-50/75 ndb:px-4 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:dark:border-zinc-800 ndb:dark:bg-zinc-900/55 ndb:sm:grid"
        >
            <span role="columnheader" data-ndb-validation-column="field">Field</span>
            <span role="columnheader" data-ndb-validation-column="message">Message</span>
            <span role="columnheader" data-ndb-validation-column="rules">Failed rules</span>
        </div>

        <div role="rowgroup" class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
            @foreach ($item['fields'] as $field)
                @php
                    $rules = (array) ($item['rules'][$field] ?? []);
                    $messages = (array) ($item['messages'][$field] ?? []);
                @endphp
                <div
                    role="row"
                    data-ndb-validation-field-row="{{ $field }}"
                    class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:px-3 ndb:py-3 ndb:sm:grid-cols-[minmax(8rem,0.8fr)_minmax(14rem,2fr)_minmax(9rem,1fr)] ndb:sm:gap-4 ndb:sm:px-4"
                >
                    <div role="cell" data-ndb-validation-field="{{ $field }}" class="ndb:min-w-0">
                        <span
                            data-ndb-validation-mobile-label
                            class="ndb:mb-1 ndb:block ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:sm:hidden"
                        >Field</span>
                        <code class="ndb:block ndb:break-words ndb:text-xs ndb:font-bold">{{ $field }}</code>
                    </div>

                    <div role="cell" data-ndb-validation-messages="{{ $field }}" class="ndb:min-w-0">
                        <span
                            data-ndb-validation-mobile-label
                            class="ndb:mb-1 ndb:block ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:sm:hidden"
                        >Message</span>
                        <ul class="ndb:m-0 ndb:list-none ndb:space-y-1 ndb:p-0">
                            @forelse ($messages as $message)
                                <li
                                    data-ndb-validation-message="{{ $field }}"
                                    class="ndb:text-xs ndb:font-medium ndb:leading-5"
                                >
                                    {{ $message }}
                                </li>
                            @empty
                                <li class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                    No validation message was returned.
                                </li>
                            @endforelse
                        </ul>
                    </div>

                    <div role="cell" data-ndb-validation-rules="{{ $field }}" class="ndb:min-w-0">
                        <span
                            data-ndb-validation-mobile-label
                            class="ndb:mb-1 ndb:block ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:sm:hidden"
                        >Failed rules</span>
                        @if ($rules !== [])
                            <div class="ndb:flex ndb:flex-wrap ndb:gap-1">
                                @foreach ($rules as $rule)
                                    <code class="ndb:rounded-md ndb:bg-zinc-100 ndb:px-1.5 ndb:py-0.5 ndb:font-mono ndb:text-xs ndb:font-semibold ndb:text-zinc-700 ndb:dark:bg-zinc-900 ndb:dark:text-zinc-300">{{ $rule }}</code>
                                @endforeach
                            </div>
                        @else
                            <span class="ndb:text-xs ndb:text-zinc-400">Not captured</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($fromPreviousRequest)
        <x-newdebugbar::inspector-explanation
            title="Why rules and source may be missing"
            :description="$sessionExplanation"
            class="ndb:border-t ndb:border-zinc-200/90 ndb:px-3 ndb:py-3 ndb:sm:px-4 ndb:dark:border-zinc-800"
        />
    @endif
</article>

@props(['entry'])

@php
    $level = (string) ($entry['level'] ?? 'log');
    $attention = (bool) ($entry['attention'] ?? false);
    $repeatCount = max(1, (int) ($entry['repeat_count'] ?? 1));
    $firstSequence = (int) ($entry['first_sequence'] ?? $entry['sequence'] ?? 1);
    $firstAt = $entry['first_at_ms'] ?? $entry['at_ms'] ?? null;
    $requestTimeLabel = $firstAt === null
        ? '—'
        : '+'.\NewDebugBar\Support\DurationFormatter::format($firstAt);
    $channelLabel = (string) ($entry['channel_label'] ?? 'No channel');
    $severityClasses = match ($level) {
        'info' => 'ndb:text-blue-700 ndb:dark:text-blue-300',
        'notice' => 'ndb:text-violet-700 ndb:dark:text-violet-300',
        'warning' => 'ndb:text-amber-700 ndb:dark:text-amber-300',
        'error', 'critical', 'alert', 'emergency' => 'ndb:text-red-700 ndb:dark:text-red-300',
        default => 'ndb:text-zinc-500 ndb:dark:text-zinc-400',
    };
@endphp

<button
    type="button"
    data-ndb-log-entry
    data-ndb-log-summary
    data-ndb-log-level="{{ $level }}"
    data-ndb-log-attention="{{ $attention ? 'true' : 'false' }}"
    data-ndb-log-channel="{{ $entry['channel_filter'] ?? '__unknown__' }}"
    data-ndb-log-search-text="{{ $entry['search'] ?? '' }}"
    data-ndb-log-record-count="{{ $repeatCount }}"
    data-ndb-log-first-sequence="{{ $firstSequence }}"
    wire:key="log-entry-{{ $firstSequence }}"
    @click="selectLogEntry({{ $firstSequence }})"
    aria-controls="newdebugbar-log-detail"
    :aria-pressed="logDetailSequence === {{ $firstSequence }}"
    :class="logDetailSequence === {{ $firstSequence }}
        ? 'ndb:bg-indigo-50/90 ndb:dark:bg-indigo-950/35'
        : 'ndb:bg-transparent ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
    class="ndb:grid ndb:h-auto ndb:w-full ndb:grid-cols-[4.75rem_minmax(0,1fr)] ndb:items-baseline ndb:gap-x-2.5 ndb:border-0 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:text-xs ndb:text-zinc-900 ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:sm:py-3 ndb:dark:text-zinc-100"
>
    <span
        data-ndb-log-severity
        class="ndb:min-w-0 ndb:bg-transparent ndb:text-xs ndb:font-bold ndb:uppercase ndb:leading-5 ndb:tracking-wide {{ $severityClasses }}"
    >{{ $entry['level_label'] ?? ucfirst($level) }}</span>

    <span class="ndb:min-w-0">
        <span
            data-ndb-log-message
            class="ndb:block ndb:max-h-10 ndb:overflow-hidden ndb:whitespace-pre-wrap ndb:break-words ndb:text-xs ndb:font-semibold ndb:leading-5 ndb:[overflow-wrap:anywhere]"
        >{{ ($entry['message'] ?? '') === '' ? '—' : $entry['message'] }}</span>
        <span
            data-ndb-log-metadata
            class="ndb:mt-0.5 ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:items-baseline ndb:gap-x-3 ndb:gap-y-0.5 ndb:text-xs ndb:leading-4 ndb:text-zinc-500 ndb:dark:text-zinc-400"
        >
            <span
                data-ndb-log-channel-label
                class="ndb:min-w-0 ndb:truncate"
                title="{{ $channelLabel }}"
            >{{ $channelLabel }}</span>
            <span data-ndb-log-request-time class="ndb:shrink-0 ndb:tabular-nums">{{ $requestTimeLabel }}</span>
            @if ($repeatCount > 1)
                <span data-ndb-log-repeat-label class="ndb:shrink-0 ndb:font-medium ndb:tabular-nums">
                    {{ $repeatCount }} records
                </span>
            @endif
        </span>
    </span>
</button>

@props(['entry'])

@php
    $level = (string) ($entry['level'] ?? 'log');
    $repeatCount = max(1, (int) ($entry['repeat_count'] ?? 1));
    $firstSequence = (int) ($entry['first_sequence'] ?? $entry['sequence'] ?? 1);
    $lastSequence = (int) ($entry['last_sequence'] ?? $firstSequence);
    $firstAt = $entry['first_at_ms'] ?? $entry['at_ms'] ?? null;
    $lastAt = $entry['last_at_ms'] ?? $firstAt;
    $contextFields = array_values($entry['context_fields'] ?? []);
    $message = (string) ($entry['message'] ?? '');
    $displayMessage = $message === '' ? '—' : $message;
    $callsite = is_array($entry['callsite'] ?? null) ? $entry['callsite'] : null;
    $relatedException = is_array($entry['related_exception'] ?? null) ? $entry['related_exception'] : null;
    $stack = array_values(is_array($entry['stack'] ?? null) ? $entry['stack'] : []);
    $occurrences = array_values(is_array($entry['occurrences'] ?? null) ? $entry['occurrences'] : []);
    $sourceLabel = isset($callsite['file'], $callsite['line']) ? $callsite['file'].':'.$callsite['line'] : null;
    $channelLabel = (string) ($entry['channel_label'] ?? 'No channel');
    $recordLabel = $repeatCount === 1 ? '#'.$firstSequence : '#'.$firstSequence.'–#'.$lastSequence;
    $requestTimeLabel = $firstAt === null
        ? '—'
        : '+'.\NewDebugBar\Support\DurationFormatter::format($firstAt);
    $lastRequestTimeLabel = $lastAt === null
        ? '—'
        : '+'.\NewDebugBar\Support\DurationFormatter::format($lastAt);
    $requestTimeRange = $repeatCount > 1 && $lastAt !== null && $lastAt !== $firstAt
        ? $requestTimeLabel.' to '.$lastRequestTimeLabel
        : $requestTimeLabel;
    $wallTime = null;
    $contextValue = static fn (mixed $value): string => match (true) {
        $value === null => 'null',
        is_bool($value) => $value ? 'true' : 'false',
        default => (string) $value,
    };
    $compactContext = array_values(array_filter($contextFields, static fn (array $field): bool => ! $field['structured'] && $contextValue($field['value']) === $field['preview']));
    $expandedContext = array_values(array_filter($contextFields, static fn (array $field): bool => $field['structured'] || $contextValue($field['value']) !== $field['preview']));

    if (is_string($entry['first_occurred_at'] ?? null) && $entry['first_occurred_at'] !== '') {
        try {
            $wallTime = new DateTimeImmutable($entry['first_occurred_at']);
        } catch (Throwable) {
            $wallTime = null;
        }
    }

    $severityClasses = match ($level) {
        'info' => 'ndb:text-blue-700 ndb:dark:text-blue-300',
        'notice' => 'ndb:text-violet-700 ndb:dark:text-violet-300',
        'warning' => 'ndb:text-amber-700 ndb:dark:text-amber-300',
        'error', 'critical', 'alert', 'emergency' => 'ndb:text-red-700 ndb:dark:text-red-300',
        default => 'ndb:text-zinc-500 ndb:dark:text-zinc-400',
    };
@endphp

<div class="ndb:flex ndb:flex-col">
    <x-newdebugbar::inspector-detail-header layout="wrap">
        <x-slot:title>
            <div class="ndb:min-w-0">
                <h3 class="ndb:bg-transparent">
                    <span
                        data-ndb-log-details-title
                        class="ndb:block ndb:whitespace-pre-wrap ndb:break-words ndb:bg-transparent ndb:text-base ndb:font-semibold ndb:leading-6 ndb:text-zinc-900 ndb:[overflow-wrap:anywhere] ndb:dark:text-zinc-100"
                    >{{ $displayMessage }}</span>
                </h3>
            </div>
        </x-slot:title>
        <x-slot:aside>
            <span data-ndb-log-detail-severity class="ndb:text-xs ndb:font-semibold {{ $severityClasses }}">
                {{ $entry['level_label'] ?? ucfirst($level) }}
            </span>
        </x-slot:aside>
    </x-newdebugbar::inspector-detail-header>

    <div data-ndb-log-detail-groups class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
        <section data-ndb-log-detail-group="summary" class="ndb:p-3 ndb:sm:p-4">
            <x-newdebugbar::inspector-facts columns="2" layout="inline" :bordered="false">
                <x-newdebugbar::inspector-fact label="Channel">
                    <x-slot:value class="ndb:break-words ndb:font-semibold" title="{{ $channelLabel }}">
                        {{ $channelLabel }}
                    </x-slot:value>
                </x-newdebugbar::inspector-fact>
                <x-newdebugbar::inspector-fact label="From request start">
                    <x-slot:value class="ndb:font-semibold ndb:tabular-nums">{{ $requestTimeRange }}</x-slot:value>
                </x-newdebugbar::inspector-fact>
            </x-newdebugbar::inspector-facts>
        </section>

        @if ($relatedException !== null)
            @php($exceptionSource = isset($relatedException['file'], $relatedException['line']) ? $relatedException['file'].':'.$relatedException['line'] : null)
            @php($exceptionMessage = trim((string) ($relatedException['message'] ?? '')))
            <section
                data-ndb-log-detail-group="related-exception"
                data-ndb-log-related-exception
                class="ndb:bg-transparent ndb:p-3 ndb:sm:p-4"
                aria-label="Related exception"
            >
                <div class="ndb:flex ndb:flex-wrap ndb:items-center ndb:justify-between ndb:gap-3">
                    <h4 class="ndb:text-xs ndb:font-bold ndb:text-red-700 ndb:dark:text-red-300">Related exception</h4>
                    <x-newdebugbar::inspector-action
                        icon="external-link"
                        data-ndb-log-review-exception
                        @click="navigateToSection('exceptions')"
                        class="ndb:bg-transparent"
                    >
                        Review in Exceptions
                    </x-newdebugbar::inspector-action>
                </div>
                <div class="ndb:mt-3 ndb:min-w-0">
                    <code class="ndb:block ndb:break-words ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-semibold ndb:text-zinc-900 ndb:dark:text-zinc-100">
                        {{ $relatedException['class'] ?? '—' }}
                    </code>
                    <p class="ndb:mt-1 ndb:break-words ndb:bg-transparent ndb:text-sm ndb:leading-5 ndb:text-zinc-700 ndb:[overflow-wrap:anywhere] ndb:dark:text-zinc-200">
                        <span class="ndb:block ndb:whitespace-pre-wrap">{{ $exceptionMessage === '' ? '—' : $exceptionMessage }}</span>
                    </p>
                    @if ($exceptionSource !== null)
                        <p class="ndb:mt-1 ndb:break-all ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                            {{ $exceptionSource }}
                        </p>
                    @endif
                </div>
            </section>
        @endif

        @if ($contextFields !== [])
            <section
                data-ndb-log-detail-group="context"
                data-ndb-log-context
                aria-label="Log context"
                class="ndb:bg-transparent ndb:p-3 ndb:sm:p-4"
            >
                <h4 class="ndb:text-xs ndb:font-bold ndb:text-zinc-800 ndb:dark:text-zinc-100">Context</h4>
                @if ($compactContext !== [])
                    <x-newdebugbar::inspector-definition-list class="ndb:mt-2">
                        @foreach ($compactContext as $field)
                            <x-newdebugbar::inspector-definition-row :label="$field['key']">
                                <x-slot:value>
                                    <span class="ndb:break-words ndb:[overflow-wrap:anywhere]">{{ $contextValue($field['value']) }}</span>
                                </x-slot:value>
                            </x-newdebugbar::inspector-definition-row>
                        @endforeach
                    </x-newdebugbar::inspector-definition-list>
                @endif
                @foreach ($expandedContext as $field)
                    @if ($field['structured'])
                        <x-newdebugbar::inspector-evidence
                            :label="$field['key']"
                            language="json"
                            data-ndb-log-context-payload
                            class="ndb:mt-3"
                        >
                            <x-slot:value>
                                {{ json_encode($field['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}
                            </x-slot:value>
                        </x-newdebugbar::inspector-evidence>
                    @else
                        <x-newdebugbar::inspector-disclosure
                            :label="$field['key']"
                            data-ndb-log-context-value
                            class="ndb:mt-3"
                        >
                            <x-slot:summary>
                                <span class="ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:items-baseline ndb:gap-x-3 ndb:gap-y-1">
                                    <span class="ndb:shrink-0">{{ $field['key'] }}</span>
                                    <span class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-normal ndb:text-zinc-500 ndb:dark:text-zinc-400">{{ $field['preview'] }}</span>
                                </span>
                            </x-slot:summary>
                            <p class="ndb:break-words ndb:text-sm ndb:leading-5 ndb:[overflow-wrap:anywhere]">
                                <span
                                    data-ndb-log-context-full-value
                                    class="ndb:whitespace-pre-wrap"
                                >{{ $contextValue($field['value']) }}</span>
                            </p>
                        </x-newdebugbar::inspector-disclosure>
                    @endif
                @endforeach
            </section>
        @endif

        <section data-ndb-log-detail-group="capture" class="ndb:p-3 ndb:sm:p-4">
            <x-newdebugbar::inspector-disclosure
                :label="$repeatCount > 1 ? 'Timing and occurrences' : 'Capture details'"
                data-ndb-log-capture-details
            >
                <x-slot:count>
                    {{ $repeatCount }} {{ \Illuminate\Support\Str::plural('record', $repeatCount) }}
                </x-slot:count>
                <x-newdebugbar::inspector-facts columns="2" layout="inline" :bordered="false">
                    <x-newdebugbar::inspector-fact label="Captured at">
                        <x-slot:value
                            class="ndb:tabular-nums"
                            title="{{ $wallTime?->format(DateTimeInterface::ATOM) ?? '' }}"
                        >
                            {{ $wallTime?->format('Y-m-d H:i:s.v P') ?? 'Not captured' }}
                        </x-slot:value>
                    </x-newdebugbar::inspector-fact>
                    <x-newdebugbar::inspector-fact label="Log">
                        <x-slot:value class="ndb:tabular-nums">{{ $recordLabel }}</x-slot:value>
                    </x-newdebugbar::inspector-fact>
                </x-newdebugbar::inspector-facts>
                @if ($repeatCount > 1)
                    <ol
                        data-ndb-log-occurrences
                        aria-label="Repeated log occurrences"
                        class="ndb:mt-3 ndb:list-none ndb:divide-y ndb:divide-zinc-200/90 ndb:p-0 ndb:dark:divide-zinc-800"
                    >
                        @foreach ($occurrences as $occurrence)
                            <li class="ndb:grid ndb:grid-cols-[5rem_minmax(0,1fr)] ndb:gap-3 ndb:py-2 ndb:text-xs">
                                <span class="ndb:font-semibold ndb:tabular-nums">#{{ $occurrence['sequence'] }}</span>
                                <span class="ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                    {{ $occurrence['at_ms'] === null ? '—' : '+'.\NewDebugBar\Support\DurationFormatter::format($occurrence['at_ms']) }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-newdebugbar::inspector-disclosure>
        </section>

        <section data-ndb-log-detail-group="source" data-ndb-log-source class="ndb:bg-transparent ndb:p-0">
            <x-newdebugbar::inspector-source-panel
                :frames="\Illuminate\Support\Js::from($stack)"
                columns="1"
                empty-label="No application stack was captured for this log entry."
                class="ndb:bg-transparent"
            >
                <x-newdebugbar::inspector-source-fact label="Source">
                    <x-slot:value>
                        @if ($sourceLabel !== null)
                            <x-newdebugbar::inspector-source-link :copy="$sourceLabel">
                                <x-slot:value>{{ $sourceLabel }}</x-slot:value>
                            </x-newdebugbar::inspector-source-link>
                        @else
                            <span>—</span>
                        @endif
                    </x-slot:value>
                </x-newdebugbar::inspector-source-fact>
            </x-newdebugbar::inspector-source-panel>
        </section>
    </div>
</div>

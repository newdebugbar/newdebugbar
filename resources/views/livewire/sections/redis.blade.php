{{-- Presents direct Redis commands in the shared inspector workspace. --}}
@php
    $formatDuration = \NewDebugBar\Support\DurationFormatter::format(...);
    $redisItems = collect($section['payload']['items'] ?? [])->values()->map(function (array $item, int $index) use ($formatDuration): array {
        $failed = (bool) ($item['failed'] ?? false);
        $command = strtoupper((string) ($item['command'] ?? 'COMMAND'));
        $connection = is_string($item['connection'] ?? null) && $item['connection'] !== ''
            ? $item['connection']
            : 'default';
        $duration = is_numeric($item['duration_ms'] ?? null) ? max(0, (float) $item['duration_ms']) : 0.0;
        $at = is_numeric($item['at_ms'] ?? null) ? max(0, (float) $item['at_ms']) : null;
        $afterResponse = is_numeric($item['after_response_ms'] ?? null)
            ? max(0, (float) $item['after_response_ms'])
            : null;
        $keys = array_values(array_filter(
            (array) ($item['keys'] ?? []),
            static fn (mixed $key): bool => is_scalar($key),
        ));
        $keys = array_map('strval', $keys);
        $hashes = array_values(array_filter(
            (array) ($item['key_hashes'] ?? []),
            static fn (mixed $hash): bool => is_string($hash) && $hash !== '',
        ));
        $keyCount = max(0, (int) ($item['key_count'] ?? count($keys)));
        $retainedCount = max(0, (int) ($item['key_retained'] ?? max(count($keys), count($hashes))));
        $droppedCount = max(0, (int) ($item['key_dropped'] ?? max(0, $keyCount - $retainedCount)));
        $rawCallsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;
        $callsiteLine = is_numeric($rawCallsite['line'] ?? null)
            && (int) $rawCallsite['line'] > 0
                ? (int) $rawCallsite['line']
                : null;
        $callsite = $rawCallsite !== null
            && is_string($rawCallsite['file'] ?? null)
            && $rawCallsite['file'] !== ''
                ? [
                    'file' => $rawCallsite['file'],
                    'line' => $callsiteLine,
                ]
                : null;
        $keyLabel = match (true) {
            $keys !== [] && $keyCount > 1 => $keys[0].' and '.number_format($keyCount - 1).' more',
            $keys !== [] => $keys[0],
            $keyCount > 0 => number_format($keyCount).' protected '.\Illuminate\Support\Str::plural('key', $keyCount),
            default => 'No key metadata retained',
        };

        return [
            'execution' => $index + 1,
            'command' => $command,
            'connection' => $connection,
            'duration_ms' => $duration,
            'duration_label' => $failed ? '—' : $formatDuration($duration),
            'failed' => $failed,
            'status_label' => $failed ? 'Failed' : 'Completed',
            'exception_class' => is_string($item['exception_class'] ?? null) ? $item['exception_class'] : null,
            'at_ms' => $at,
            'at_label' => $at === null ? '—' : $formatDuration($at),
            'lifecycle' => is_string($item['lifecycle'] ?? null) ? $item['lifecycle'] : 'request',
            'phase_label' => ($item['lifecycle'] ?? null) === 'after_response' ? 'After response' : 'During request',
            'after_response_ms' => $afterResponse,
            'after_response_label' => $afterResponse === null ? null : $formatDuration($afterResponse),
            'key_count' => $keyCount,
            'key_dropped' => $droppedCount,
            'key_label' => $keyLabel,
            'keys' => $keys,
            'key_hashes' => $hashes,
            'callsite' => $callsite,
            'source_label' => $callsite === null
                ? null
                : $callsite['file'].($callsite['line'] === null ? '' : ':'.$callsite['line']),
        ];
    })->all();
    $redisCount = count($redisItems);
    $failureCount = (int) ($section['summary']['failed_count'] ?? collect($redisItems)->where('failed', true)->count());
    $totalDuration = (float) ($section['summary']['duration_ms'] ?? 0);
    $summaryParts = [$formatDuration($totalDuration).' total'];

    if ($failureCount > 0) {
        $summaryParts[] = number_format($failureCount).' '.\Illuminate\Support\Str::plural('failure', $failureCount);
    }
@endphp

<div
    data-ndb-redis
    x-init="initializeRedis(JSON.parse(atob($el.querySelector('[data-ndb-redis-payload]').textContent.trim())))"
    class="ndb:border-l-0 ndb:bg-transparent ndb:text-zinc-950 ndb:dark:text-white ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    <script type="application/json" data-ndb-redis-payload>
        {{ base64_encode(json_encode($redisItems, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)) }}
    </script>

    @if ($redisItems !== [])
        <x-newdebugbar::inspector-workspace frame="top" data-ndb-redis-workspace class="ndb:border-x-0">
            <x-newdebugbar::inspector-list-panel detail-open="redisDetailOpen" list-ref="redisList">
                <x-slot:controls>
                    <div class="ndb:min-w-0">
                        <p class="ndb:text-xs ndb:font-bold ndb:text-zinc-700 ndb:dark:text-zinc-200">
                            {{ number_format($redisCount) }} {{ \Illuminate\Support\Str::plural('command', $redisCount) }}
                            <span
                                x-show.important="visibleRedisCount !== redisCommands.length"
                                class="ndb:ml-1 ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            ><span data-ndb-redis-visible-count x-text="visibleRedisCount"></span> shown</span>
                        </p>
                        <p class="ndb:mt-0.5 ndb:text-xs ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
                            {{ implode(', ', $summaryParts) }}
                        </p>
                    </div>

                    @if ($redisCount >= 5 || $failureCount > 0)
                        <x-newdebugbar::inspector-list-controls :show-search="$redisCount >= 5">
                            <x-slot:search>
                                <x-newdebugbar::search-field
                                    label="Search Redis commands"
                                    placeholder="Search commands or keys"
                                    data-ndb-redis-search
                                    x-model="redisSearch"
                                    @input.debounce.100ms="applyRedisView()"
                                />
                            </x-slot:search>
                            @if ($failureCount > 0)
                                <x-slot:filter>
                                    <x-newdebugbar::select-field
                                        label="Filter Redis commands"
                                        data-ndb-redis-filter
                                        x-model="redisFilter"
                                        @change="setRedisFilter($event.target.value)"
                                    >
                                        <option value="all">All ({{ $redisCount }})</option>
                                        <option value="failed">Failed ({{ $failureCount }})</option>
                                    </x-newdebugbar::select-field>
                                </x-slot:filter>
                            @endif
                        </x-newdebugbar::inspector-list-controls>
                    @endif
                </x-slot:controls>

                <x-slot:list data-ndb-redis-list>
                    @foreach ($redisItems as $item)
                        <button
                            type="button"
                            wire:key="redis-command-{{ $item['execution'] }}"
                            data-ndb-redis-item="{{ $item['execution'] }}"
                            data-ndb-redis-execution="{{ $item['execution'] }}"
                            data-ndb-redis-failed="{{ $item['failed'] ? 'true' : 'false' }}"
                            aria-controls="newdebugbar-redis-detail"
                            @click="selectRedisCommand({{ $item['execution'] }})"
                            :aria-pressed="redisSelected === {{ $item['execution'] }}"
                            :class="redisSelected === {{ $item['execution'] }}
                                ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                                : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
                            class="ndb:grid ndb:h-auto ndb:min-h-0 ndb:w-full ndb:min-w-0 ndb:grid-cols-[4.75rem_minmax(0,1fr)_4.75rem] ndb:items-center ndb:gap-x-2 ndb:border-l-0 ndb:bg-transparent ndb:px-3 ndb:py-2.5 ndb:text-left ndb:text-xs ndb:text-zinc-950 ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-white"
                        >
                            <x-newdebugbar::inspector-operation-badge
                                wide
                                data-ndb-redis-command
                                class="ndb:row-span-2 ndb:self-center"
                            >{{ $item['command'] }}</x-newdebugbar::inspector-operation-badge>
                            <span
                                data-ndb-redis-key-label
                                class="ndb:min-w-0 ndb:truncate ndb:bg-transparent ndb:text-xs ndb:font-semibold ndb:text-zinc-800 ndb:dark:text-zinc-200"
                                title="{{ $item['key_label'] }}"
                            >{{ $item['key_label'] }}</span>
                            <span
                                @class([
                                    'ndb:text-right ndb:text-xs ndb:font-bold',
                                    'ndb:text-red-600 ndb:dark:text-red-300' => $item['failed'],
                                    'ndb:text-zinc-500 ndb:dark:text-zinc-400' => ! $item['failed'],
                                ])
                            >{{ $item['status_label'] }}</span>
                            <span class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">{{ $item['connection'] }}</span>
                            <span class="ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">{{ $item['duration_label'] }}</span>
                        </button>
                    @endforeach
                </x-slot:list>

                <x-slot:empty x-show.important="visibleRedisCount === 0">
                    <x-newdebugbar::empty-state label="No Redis commands match these controls." />
                </x-slot:empty>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::inspector-detail-pane
                detail-open="redisDetailOpen"
                detail-ref="redisDetail"
                detail-label="Selected Redis command details"
                back-label="Redis"
                close-action="closeRedisDetail()"
                id="newdebugbar-redis-detail"
                data-ndb-redis-detail
                class="ndb:border-x-0 ndb:bg-transparent"
            >
                <x-slot:back>
                    <x-newdebugbar::inspector-detail-back
                        data-ndb-redis-back
                        @click="closeRedisDetail()"
                        label="Redis"
                    />
                </x-slot:back>

                <template x-if="selectedRedisCommand">
                    <div class="ndb:flex ndb:flex-col">
                        <x-newdebugbar::inspector-detail-header layout="wrap" data-ndb-redis-detail-header>
                            <x-slot:title>
                                <div class="ndb:grid ndb:min-w-0 ndb:flex-1 ndb:grid-cols-[4rem_minmax(0,1fr)] ndb:items-center ndb:gap-2">
                                    <x-newdebugbar::inspector-operation-badge
                                        outlined
                                        wide
                                        data-ndb-redis-command
                                        x-text="selectedRedisCommand.command"
                                    ></x-newdebugbar::inspector-operation-badge>
                                    <h3
                                        data-ndb-redis-key-label
                                        :title="selectedRedisCommand.key_label"
                                        class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-bold ndb:leading-5 ndb:text-zinc-700 ndb:dark:text-zinc-200"
                                        x-text="selectedRedisCommand.key_label"
                                    ></h3>
                                </div>
                            </x-slot:title>
                            <x-slot:aside>
                                <span
                                    data-ndb-redis-detail-status
                                    class="ndb:inline-flex ndb:rounded-md ndb:px-2 ndb:py-1 ndb:text-xs ndb:font-bold"
                                    :class="selectedRedisCommand.failed
                                        ? 'ndb:bg-red-100 ndb:text-red-700 ndb:dark:bg-red-950 ndb:dark:text-red-300'
                                        : 'ndb:bg-zinc-100 ndb:text-zinc-600 ndb:dark:bg-zinc-800 ndb:dark:text-zinc-300'"
                                    x-text="selectedRedisCommand.status_label"
                                ></span>
                            </x-slot:aside>
                        </x-newdebugbar::inspector-detail-header>

                        <div data-ndb-redis-detail-body class="ndb:flex ndb:flex-col">
                            <div class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-4 ndb:sm:p-4">
                                <x-newdebugbar::inspector-facts columns="4" :bordered="false" data-ndb-redis-facts>
                                    <x-newdebugbar::inspector-fact label="Connection"
                                        ><x-slot:value x-text="selectedRedisCommand.connection"></x-slot:value
                                    ></x-newdebugbar::inspector-fact>
                                    <x-newdebugbar::inspector-fact label="Duration"
                                        ><x-slot:value
                                            class="ndb:tabular-nums"
                                            x-text="selectedRedisCommand.duration_label"
                                        ></x-slot:value
                                    ></x-newdebugbar::inspector-fact>
                                    <x-newdebugbar::inspector-fact label="Captured at"
                                        ><x-slot:value
                                            class="ndb:tabular-nums"
                                            x-text="selectedRedisCommand.at_label"
                                        ></x-slot:value
                                    ></x-newdebugbar::inspector-fact>
                                    <x-newdebugbar::inspector-fact label="Phase"
                                        ><x-slot:value x-text="selectedRedisCommand.phase_label"></x-slot:value
                                    ></x-newdebugbar::inspector-fact>
                                </x-newdebugbar::inspector-facts>

                                <section
                                    data-ndb-redis-failure
                                    x-show.important="selectedRedisCommand.failed"
                                    class="ndb:rounded-lg ndb:border ndb:border-red-200 ndb:bg-red-50/55 ndb:p-3 ndb:dark:border-red-950 ndb:dark:bg-red-950/20"
                                >
                                    <code
                                        data-ndb-language="php"
                                        x-show.important="selectedRedisCommand.exception_class"
                                        class="ndb:block ndb:break-all ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-semibold ndb:text-red-700 ndb:dark:text-red-300"
                                        x-text="selectedRedisCommand.exception_class"
                                    ></code>
                                    <p
                                        x-show.important="! selectedRedisCommand.exception_class"
                                        class="ndb:text-xs ndb:font-semibold ndb:text-red-700 ndb:dark:text-red-300"
                                    >
                                        Exception class was not retained.
                                    </p>
                                </section>

                                <p
                                    data-ndb-redis-after-response
                                    x-show.important="selectedRedisCommand.lifecycle === 'after_response'"
                                    class="ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                    x-text="
                                        selectedRedisCommand.after_response_label
                                            ? `This command ran ${selectedRedisCommand.after_response_label} after the response was sent, so its time is not part of the response time.`
                                            : 'This command ran after the response was sent, so its time is not part of the response time.'
                                    "
                                ></p>
                            </div>

                            <section
                                data-ndb-redis-key-evidence
                                aria-labelledby="newdebugbar-redis-keys-heading"
                                class="ndb:space-y-3 ndb:border-t ndb:border-zinc-200 ndb:p-3 ndb:sm:space-y-4 ndb:sm:p-4 ndb:dark:border-zinc-800"
                            >
                                <div class="ndb:flex ndb:min-w-0 ndb:items-center ndb:justify-between ndb:gap-3">
                                    <h4
                                        id="newdebugbar-redis-keys-heading"
                                        class="ndb:text-sm ndb:font-bold ndb:text-zinc-950 ndb:dark:text-white"
                                    >
                                        Keys used
                                    </h4>
                                    <x-newdebugbar::inspector-action
                                        icon="copy"
                                        data-ndb-redis-copy-keys
                                        x-show.important="
                                            selectedRedisCommand.keys.length || selectedRedisCommand.key_hashes.length
                                        "
                                        @click="
                                            copyText(
                                                (selectedRedisCommand.keys.length
                                                    ? selectedRedisCommand.keys
                                                    : selectedRedisCommand.key_hashes
                                                ).join('\n'),
                                            )
                                        "
                                        class="ndb:shrink-0"
                                        ><span
                                            x-text="selectedRedisCommand.keys.length ? 'Copy keys' : 'Copy identifiers'"
                                        ></span
                                    ></x-newdebugbar::inspector-action>
                                </div>

                                <x-newdebugbar::inspector-explanation
                                    x-show.important="
                                        selectedRedisCommand.keys.length === 0 && selectedRedisCommand.key_hashes.length
                                    "
                                    title="Why are these identifiers protected?"
                                    description="Full key text was not retained. Use these stable identifiers to match repeated access; use full local capture only when you need the key itself."
                                />

                                <template x-if="selectedRedisCommand.keys.length">
                                    <x-newdebugbar::inspector-definition-list data-ndb-redis-keys>
                                        <template
                                            x-for="(key, index) in selectedRedisCommand.keys"
                                            :key="`${index}:${key}`"
                                        >
                                            <x-newdebugbar::inspector-definition-row>
                                                <x-slot:term x-text="`Key ${index + 1}`"></x-slot:term>
                                                <x-slot:value
                                                    data-ndb-redis-key
                                                    class="ndb:break-all"
                                                    x-text="key"
                                                ></x-slot:value>
                                            </x-newdebugbar::inspector-definition-row>
                                        </template>
                                    </x-newdebugbar::inspector-definition-list>
                                </template>

                                <template
                                    x-if="
                                        selectedRedisCommand.keys.length === 0 && selectedRedisCommand.key_hashes.length
                                    "
                                >
                                    <x-newdebugbar::inspector-definition-list data-ndb-redis-protected-keys>
                                        <template
                                            x-for="(hash, index) in selectedRedisCommand.key_hashes"
                                            :key="`${index}:${hash}`"
                                        >
                                            <x-newdebugbar::inspector-definition-row>
                                                <x-slot:term x-text="`Identifier ${index + 1}`"></x-slot:term>
                                                <x-slot:value
                                                    data-ndb-redis-key-hash
                                                    class="ndb:break-all"
                                                    x-text="hash"
                                                ></x-slot:value>
                                            </x-newdebugbar::inspector-definition-row>
                                        </template>
                                    </x-newdebugbar::inspector-definition-list>
                                </template>

                                <x-newdebugbar::empty-state
                                    label="No key metadata was retained for this command."
                                    x-show.important="
                                        selectedRedisCommand.keys.length === 0 &&
                                        selectedRedisCommand.key_hashes.length === 0
                                    "
                                />
                                <p
                                    data-ndb-redis-key-limit
                                    x-show.important="selectedRedisCommand.key_dropped > 0"
                                    class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                >
                                    <span class="ndb:tabular-nums" x-text="selectedRedisCommand.key_dropped"></span>
                                    <span
                                        x-text="
                                            selectedRedisCommand.key_dropped === 1
                                                ? 'more key was not retained because this command reached the capture limit.'
                                                : 'more keys were not retained because this command reached the capture limit.'
                                        "
                                    ></span>
                                </p>
                            </section>

                            <template x-if="selectedRedisCommand.callsite">
                                <x-newdebugbar::inspector-source-panel
                                    frames="[]"
                                    data-ndb-redis-source
                                    class="ndb:border-t ndb:border-zinc-200/90 ndb:dark:border-zinc-800"
                                >
                                    <x-newdebugbar::inspector-source-fact label="Source">
                                        <x-newdebugbar::inspector-source-link
                                            ::title="'Copy ' + selectedRedisCommand.source_label"
                                            ::aria-label="'Copy Redis source ' + selectedRedisCommand.source_label"
                                            @click="copyText(selectedRedisCommand.source_label)"
                                        >
                                            <x-slot:value x-text="selectedRedisCommand.source_label"></x-slot:value>
                                        </x-newdebugbar::inspector-source-link>
                                    </x-newdebugbar::inspector-source-fact>
                                </x-newdebugbar::inspector-source-panel>
                            </template>
                        </div>
                    </div>
                </template>

                <x-newdebugbar::inspector-detail-empty
                    label="Choose a command to inspect its Redis evidence."
                    x-show.important="! selectedRedisCommand"
                />
            </x-newdebugbar::inspector-detail-pane>
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No direct Redis commands were captured." />
    @endif
</div>

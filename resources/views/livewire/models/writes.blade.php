@if ($writeOperations !== [])
    <section
        data-ndb-model-write-table
        @class([
            'ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white',
            'ndb:mt-3 ndb:sm:mt-5' => $retrievalCount > 0,
        ])
    >
        <x-newdebugbar::inspector-explanation
            title="How this model changed"
            description="Each row is one completed write. If a write is unexpected, inspect its changed fields and application source."
        />

        <div class="ndb:mt-3 ndb:border-y ndb:border-zinc-200/90 ndb:dark:border-zinc-800">
            <div class="ndb:hidden ndb:grid-cols-[6rem_4rem_minmax(0,1fr)] ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:dark:border-zinc-800 ndb:sm:grid">
                <span>Operation</span>
                <span>Record</span>
                <span>Source</span>
            </div>

            <div class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                @foreach ($writeOperations as $operation)
                    @php
                        $writeKey = $operation['key'] ?? null;
                        $writeSource = $operation['callsite'] ?? null;
                        $writeSourceCopy = $sourceCopy($writeSource);
                        $changedFields = is_array($operation['changes'] ?? null) ? $operation['changes'] : [];
                    @endphp
                    <article
                        data-ndb-model-write-operation
                        class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:border-l-0 ndb:bg-transparent ndb:px-0 ndb:py-2.5 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white ndb:sm:grid-cols-[6rem_4rem_minmax(0,1fr)] ndb:sm:items-center ndb:sm:gap-3"
                    >
                        <span class="ndb:text-xs ndb:font-semibold">
                            <span class="ndb:text-zinc-400 ndb:sm:hidden">Operation </span
                            >{{ $formatEvent((string) ($operation['event'] ?? 'changed')) }}
                        </span>
                        <span @class([
                            'ndb:min-w-0 ndb:break-all ndb:text-sm ndb:font-semibold',
                            'ndb:font-mono ndb:tabular-nums' => is_numeric($writeKey),
                        ])>
                            <span class="ndb:font-sans ndb:text-zinc-400 ndb:sm:hidden">Record </span
                            >{{ $writeKey === null || $writeKey === '' ? '—' : (string) $writeKey }}
                        </span>
                        @if ($writeSourceCopy !== null)
                            <x-newdebugbar::inspector-source-link
                                :copy="$writeSourceCopy"
                                :title="$sourceTitle($writeSource)"
                                class="ndb:justify-self-start ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            >
                                <span class="ndb:sm:hidden">Source </span>{{ $sourceShortLabel($writeSource) }}
                            </x-newdebugbar::inspector-source-link>
                        @else
                            <span class="ndb:text-xs ndb:text-zinc-400">—</span>
                        @endif
                        <x-newdebugbar::inspector-disclosure
                            label="Changed fields"
                            data-ndb-model-write-details
                            class="ndb:sm:col-span-3"
                        >
                            <x-slot:count>{{ count($changedFields) }}</x-slot:count>
                            <x-newdebugbar::inspector-facts layout="inline" :bordered="false" class="ndb:mb-3">
                                <x-newdebugbar::inspector-fact label="From request start">
                                    <x-slot:value data-ndb-model-write-time class="ndb:tabular-nums">
                                        {{ is_numeric($operation['at_ms'] ?? null) ? '+'.\NewDebugBar\Support\DurationFormatter::format($operation['at_ms']) : 'Not captured' }}
                                    </x-slot:value>
                                </x-newdebugbar::inspector-fact>
                            </x-newdebugbar::inspector-facts>
                            @if ($changedFields !== [])
                                <x-newdebugbar::inspector-evidence language="json" data-ndb-model-changed-fields>
                                    <x-slot:value>
                                        {{ json_encode($changedFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}
                                    </x-slot:value>
                                </x-newdebugbar::inspector-evidence>
                            @else
                                <p class="ndb:text-sm ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                    No changed field values were retained for this write.
                                </p>
                            @endif
                        </x-newdebugbar::inspector-disclosure>
                    </article>
                @endforeach
            </div>
        </div>

        @if ($hiddenWriteCount > 0)
            <p class="ndb:mt-2 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                Showing {{ number_format(count($writeOperations)) }} of {{ number_format($changeCount) }} writes.
            </p>
        @endif
    </section>
@endif

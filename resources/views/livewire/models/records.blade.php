@if ($retrievalCount > 0)
    <section
        data-ndb-model-records
        class="ndb:border-l-0 ndb:bg-transparent ndb:p-0 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white"
    >
        <x-newdebugbar::inspector-explanation
            title="How this model was loaded"
            description="Retrieved counts each load of a record. If it exceeds 1, check whether the repeated loads are expected."
        />

        <div class="ndb:mt-3 ndb:border-y ndb:border-zinc-200/90 ndb:dark:border-zinc-800">
            <div class="ndb:hidden ndb:grid-cols-[5rem_4.5rem_minmax(0,1fr)] ndb:gap-3 ndb:border-b ndb:border-zinc-200/90 ndb:py-2 ndb:text-xs ndb:font-semibold ndb:text-zinc-400 ndb:dark:border-zinc-800 ndb:sm:grid">
                <span>Identifier</span>
                <span class="ndb:text-right">Retrieved</span>
                <span>Source</span>
            </div>

            <div class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                @foreach ($group['records'] ?? [] as $record)
                    @php
                        $recordSource = $record['sources'][0]['callsite'] ?? null;
                        $recordSourceCopy = $sourceCopy($recordSource);
                        $recordLoads = (int) ($record['loads'] ?? 0);
                        $recordKey = $record['key'] ?? null;
                        $recordSources = array_values($record['sources'] ?? []);
                        $hiddenRecordSources = (int) ($record['hidden_source_count'] ?? 0);
                    @endphp
                    <article
                        data-ndb-model-record
                        data-ndb-model-record-retrievals="{{ $recordLoads }}"
                        class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:border-l-0 ndb:bg-transparent ndb:px-0 ndb:py-2.5 ndb:text-xs ndb:text-zinc-950 ndb:dark:text-white ndb:sm:grid-cols-[5rem_4.5rem_minmax(0,1fr)] ndb:sm:items-center ndb:sm:gap-3"
                    >
                        <p @class([
                            'ndb:min-w-0 ndb:break-all ndb:text-sm ndb:font-semibold',
                            'ndb:font-mono ndb:tabular-nums' => is_numeric($recordKey),
                        ])>
                            {{ (string) $recordKey }}
                        </p>
                        <span @class([
                            'ndb:text-sm ndb:font-semibold ndb:tabular-nums ndb:sm:text-right',
                            'ndb:text-amber-700 ndb:dark:text-amber-300' => $recordLoads > 1,
                            'ndb:text-zinc-600 ndb:dark:text-zinc-300' => $recordLoads === 1,
                        ])>
                            <span class="ndb:text-zinc-400 ndb:sm:hidden">Retrieved </span
                            >{{ number_format($recordLoads) }}
                        </span>
                        @if ($recordSourceCopy !== null)
                            <x-newdebugbar::inspector-source-link
                                :copy="$recordSourceCopy"
                                :title="$sourceTitle($recordSource)"
                                class="ndb:justify-self-start ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            >
                                <span class="ndb:sm:hidden">Source </span>{{ $sourceShortLabel($recordSource) }}
                            </x-newdebugbar::inspector-source-link>
                        @else
                            <span class="ndb:text-xs ndb:text-zinc-400">—</span>
                        @endif

                        @if (count($recordSources) > 1 || $hiddenRecordSources > 0 || (int) ($record['unknown_source_count'] ?? 0) > 0)
                            <x-newdebugbar::inspector-disclosure
                                label="Record sources"
                                data-ndb-model-record-sources
                                class="ndb:sm:col-span-3"
                            >
                                <x-slot:count>{{ count($recordSources) }}</x-slot:count>
                                <ul class="ndb:list-none ndb:space-y-3 ndb:p-0">
                                    @foreach ($recordSources as $retainedSource)
                                        @php
                                            $retainedCallsite = $retainedSource['callsite'] ?? null;
                                            $retainedSourceCopy = $sourceCopy($retainedCallsite);
                                        @endphp
                                        <li class="ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:items-baseline ndb:justify-between ndb:gap-2">
                                            @if ($retainedSourceCopy !== null)
                                                <x-newdebugbar::inspector-source-link
                                                    :copy="$retainedSourceCopy"
                                                    :title="$sourceTitle($retainedCallsite)"
                                                    class="ndb:min-w-0 ndb:break-all"
                                                >{{ $retainedSourceCopy }}</x-newdebugbar::inspector-source-link>
                                            @else
                                                <span class="ndb:text-sm ndb:text-zinc-500 ndb:dark:text-zinc-400">Source unavailable</span>
                                            @endif
                                            <span class="ndb:shrink-0 ndb:text-xs ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                                {{ $formatActivity((int) ($retainedSource['retrieval_count'] ?? 0), (int) ($retainedSource['change_count'] ?? 0)) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($hiddenRecordSources > 0)
                                    <p class="ndb:mt-3 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                        Showing {{ count($recordSources) }} of {{ number_format((int) ($record['source_count'] ?? count($recordSources))) }} retained
                                        sources.
                                    </p>
                                @endif
                                @if ((int) ($record['unknown_source_count'] ?? 0) > 0)
                                    <p class="ndb:mt-3 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                        {{ number_format((int) $record['unknown_source_count']) }} retrievals had no
                                        application source.
                                    </p>
                                @endif
                            </x-newdebugbar::inspector-disclosure>
                        @endif
                    </article>
                @endforeach

                @if ($unidentifiedCount > 0)
                    <article
                        data-ndb-model-missing-identifiers
                        class="ndb:grid ndb:min-w-0 ndb:gap-2 ndb:border-l-0 ndb:bg-transparent ndb:px-0 ndb:py-2.5 ndb:sm:grid-cols-[5rem_4.5rem_minmax(0,1fr)] ndb:sm:items-center ndb:sm:gap-3"
                    >
                        <p class="ndb:text-xs ndb:font-semibold">—</p>
                        <span class="ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-600 ndb:dark:text-zinc-300 ndb:sm:text-right">
                            <span class="ndb:text-zinc-400 ndb:sm:hidden">Retrieved </span
                            >{{ number_format($unidentifiedCount) }}
                        </span>
                        <span class="ndb:text-xs ndb:text-zinc-400">—</span>
                    </article>
                @endif
            </div>
        </div>

        @if ((int) ($group['hidden_record_count'] ?? 0) > 0)
            <p data-ndb-model-record-limit class="ndb:mt-2 ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                Showing {{ number_format(count($group['records'])) }} of {{ number_format($recordCount) }} identified
                records.
            </p>
        @endif

        @if ($unidentifiedCount > 0)
            <p
                data-ndb-model-unidentified
                class="ndb:mt-2 ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
            >
                A dash means the model identifier was unavailable. These retrievals are excluded from the reload count.
            </p>
        @endif
    </section>
@endif

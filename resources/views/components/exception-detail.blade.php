@props([
    'exception',
    'index',
    'profileActionLabel' => 'Open request',
])

@php
    $applicationFrames = array_values($exception['frames']['application'] ?? []);
    $vendorFrames = array_values($exception['frames']['vendor'] ?? []);
    $causes = array_values(array_filter($exception['causes'] ?? [], 'is_array'));
    $detailTabs = ['source' => 'Source', 'stack' => 'Stack'];

    if ($causes !== []) {
        $detailTabs['causes'] = 'Causes';
    }

    $sourceLines = array_values($exception['source']['lines'] ?? []);
    $lineNumberWidth = $sourceLines === []
        ? 0
        : max(array_map(fn (array $line): int => strlen((string) $line['number']), $sourceLines));
    $sourceText = $sourceLines === []
        ? null
        : implode("\n", array_map(
            fn (array $line): string => sprintf(
                '%s%s %s',
                str_repeat("\u{2007}", max(0, $lineNumberWidth - strlen((string) $line['number']))).$line['number'],
                $line['focus'] ? '>' : ' ',
                $line['code'],
            ),
            $sourceLines,
        ));
@endphp

<article data-ndb-exception-detail="{{ $index }}" x-data="{ exceptionDetailTab: 'source' }">
    <x-newdebugbar::inspector-detail-header>
        <x-slot:title>
            <div data-ndb-exception-header-copy class="ndb:min-w-0">
                <h3 class="ndb:min-w-0 ndb:text-sm ndb:font-bold">
                    <code class="ndb:block ndb:break-words ndb:font-mono">{{ $exception['class'] }}</code>
                </h3>
                <p class="ndb:mt-1 ndb:text-xs ndb:font-semibold ndb:leading-5">
                    {{ $exception['message'] ?: 'No exception message was captured.' }}
                </p>
            </div>
        </x-slot:title>
        <x-slot:aside>
            <x-newdebugbar::inspector-source-link
                data-ndb-copy-exception-callsite="{{ $index }}"
                :copy="$exception['file'].':'.$exception['line']"
                aria-label="Copy exception source"
            >
                {{ $exception['file'] }}:{{ $exception['line'] }}
            </x-newdebugbar::inspector-source-link>
        </x-slot:aside>
    </x-newdebugbar::inspector-detail-header>

    <x-newdebugbar::inspector-detail-tabs label="Exception detail">
        @foreach ($detailTabs as $tab => $label)
            <x-newdebugbar::filter-tab
                variant="segmented"
                data-ndb-exception-detail-tab="{{ $tab }}"
                @click="exceptionDetailTab = '{{ $tab }}'"
                ::aria-pressed="exceptionDetailTab === '{{ $tab }}'"
            >
                {{ $label }}
            </x-newdebugbar::filter-tab>
        @endforeach

        <x-slot:aside>
            <x-newdebugbar::inspector-action
                icon="external-link"
                data-ndb-exception-context-action
                @click="navigateToSection('request')"
            >
                {{ $profileActionLabel }}
            </x-newdebugbar::inspector-action>
        </x-slot:aside>
    </x-newdebugbar::inspector-detail-tabs>

    <template x-if="exceptionDetailTab === 'source'">
        <section data-ndb-exception-detail-panel="source">
            @if ($sourceText !== null)
                <x-newdebugbar::code-block
                    language="php"
                    class="ndb:rounded-none ndb:border-0"
                >{{ $sourceText }}</x-newdebugbar::code-block>
            @else
                <x-newdebugbar::empty-state label="No source context was captured for this exception." />
            @endif
        </section>
    </template>

    <template x-if="exceptionDetailTab === 'stack'">
        <section data-ndb-exception-detail-panel="stack" class="ndb:p-3 ndb:sm:p-4">
            <x-newdebugbar::inspector-stack
                :frames="\Illuminate\Support\Js::from($applicationFrames)"
                empty-label="No application frames were captured."
                class="ndb:mt-0"
            />

            <details class="ndb:group ndb:mt-4 ndb:border-t ndb:border-zinc-200/90 ndb:pt-3 ndb:sm:mt-5 ndb:dark:border-zinc-800">
                <summary class="ndb:flex ndb:cursor-pointer ndb:list-none ndb:items-center ndb:justify-between ndb:gap-3 ndb:text-xs ndb:font-bold ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500">
                    <span>Vendor stack</span>
                    <span class="ndb:text-xs ndb:font-medium ndb:tabular-nums ndb:text-zinc-400">
                        {{ number_format(count($vendorFrames)) }} {{ \Illuminate\Support\Str::plural('frame', count($vendorFrames)) }}
                    </span>
                </summary>
                <x-newdebugbar::inspector-stack
                    :frames="\Illuminate\Support\Js::from($vendorFrames)"
                    :show-heading="false"
                    empty-label="No vendor frames were captured."
                    class="ndb:mt-2"
                />
            </details>
        </section>
    </template>

    @if ($causes !== [])
        <template x-if="exceptionDetailTab === 'causes'">
            <section data-ndb-exception-detail-panel="causes" class="ndb:p-3 ndb:sm:p-4">
                <div class="ndb:divide-y ndb:divide-zinc-200/90 ndb:dark:divide-zinc-800">
                    @foreach ($causes as $causeIndex => $cause)
                        <article
                            data-ndb-exception-cause="{{ $causeIndex }}"
                            class="ndb:border-l-0 ndb:bg-transparent ndb:px-0 ndb:py-3 ndb:first:pt-0 ndb:last:pb-0"
                        >
                            <div class="ndb:flex ndb:min-w-0 ndb:flex-wrap ndb:items-center ndb:justify-between ndb:gap-2">
                                <p class="ndb:text-xs ndb:font-bold ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                    Cause {{ $causeIndex + 1 }}
                                </p>
                                @if (isset($cause['file'], $cause['line']))
                                    <x-newdebugbar::inspector-source-link
                                        :copy="$cause['file'].':'.$cause['line']"
                                        aria-label="Copy cause source"
                                    >
                                        {{ $cause['file'] }}:{{ $cause['line'] }}
                                    </x-newdebugbar::inspector-source-link>
                                @endif
                            </div>
                            <code class="ndb:mt-2 ndb:block ndb:min-w-0 ndb:break-words ndb:bg-transparent ndb:font-mono ndb:text-xs ndb:font-semibold ndb:text-zinc-900 ndb:dark:text-zinc-100">
                                {{ $cause['class'] ?? 'Throwable' }}
                            </code>
                            <p class="ndb:mt-1 ndb:break-words ndb:text-xs ndb:font-medium ndb:leading-5 ndb:text-zinc-700 ndb:[overflow-wrap:anywhere] ndb:dark:text-zinc-200">
                                {{ filled($cause['message'] ?? null) ? $cause['message'] : 'No exception message was captured.' }}
                            </p>
                        </article>
                    @endforeach
                </div>

                @if ($exception['chain_truncated'] ?? false)
                    <p class="ndb:mt-3 ndb:text-xs ndb:font-semibold ndb:text-amber-700 ndb:sm:mt-4 ndb:dark:text-amber-300">
                        More causes exist, but only the first five were retained.
                    </p>
                @endif
            </section>
        </template>
    @endif
</article>

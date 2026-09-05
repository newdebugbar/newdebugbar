{{-- Renders the HTTP request trace and captured request data. --}}
@php($requestPayload = $section['payload'])
@php($isHttpRequest = ($profile['profile_type'] ?? 'http') === 'http')
@php($requestStatus = (int) ($requestPayload['status'] ?? 0))
@php($requestSucceeded = $requestStatus >= 200 && $requestStatus < 300)
@php($requestFailed = $requestStatus >= 400)
@php($requestStatusText = \Symfony\Component\HttpFoundation\Response::$statusTexts[$requestStatus] ?? '')
@php($requestResponseTone = $requestFailed ? 'error' : ($requestSucceeded ? 'success' : 'neutral'))
@php($requestDuration = \NewDebugBar\Support\DurationFormatter::format($profile['metrics']['duration_ms'] ?? 0))
@php($requestHeaders = is_array($requestPayload['headers'] ?? null) ? $requestPayload['headers'] : [])
@php($requestInput = is_array($requestPayload['input'] ?? null) ? $requestPayload['input'] : [])
@php($requestQuery = is_array($requestPayload['query'] ?? null) ? $requestPayload['query'] : [])
@php($requestSession = is_array($requestPayload['session'] ?? null) ? $requestPayload['session'] : [])
@php($requestAuthentication = is_array($requestPayload['authentication'] ?? null) ? $requestPayload['authentication'] : [])
@php($requestMiddleware = is_array($requestPayload['middleware'] ?? null) ? $requestPayload['middleware'] : [])
@php($requestPath = ($requestPayload['path'] ?? null) ?: ($requestPayload['url'] ?? null) ?: '—')
@php($requestUrl = (string) ($requestPayload['url'] ?? ''))
@php($requestUrlParts = parse_url($requestUrl) ?: [])
@php($requestOrigin = isset($requestUrlParts['host']) ? ($requestUrlParts['scheme'] ?? 'http').'://'.$requestUrlParts['host'].(isset($requestUrlParts['port']) ? ':'.$requestUrlParts['port'] : '') : '')
@php($requestSize = (int) ($requestPayload['request_size_bytes'] ?? 0))
@php($requestAction = ($requestPayload['action'] ?? null) ?: 'Closure')
@php($requestActionSeparator = strrpos($requestAction, '\\'))
@php($requestActionName = $requestActionSeparator === false ? $requestAction : substr($requestAction, $requestActionSeparator + 1))
@php($requestActionNamespace = $requestActionSeparator === false ? '' : substr($requestAction, 0, $requestActionSeparator))
@php(
    $formatRequestBytes = static fn (int $bytes): string => $bytes >= 1024
        ? number_format($bytes / 1024, 2).' KB'
        : number_format($bytes).' B'
)
@php(
    $formatRequestValue = static function (mixed $value): string {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
)
@php(
    $requestDetailGroups = [
        'headers' => [
            'label' => 'Headers',
            'count' => count($requestHeaders),
            'items' => $requestHeaders,
        ],
        'input' => [
            'label' => 'Input',
            'count' => count($requestInput),
            'items' => $requestInput,
        ],
        'query' => [
            'label' => 'Query',
            'count' => count($requestQuery),
            'items' => $requestQuery,
        ],
        'session' => [
            'label' => 'Session',
            'count' => (int) ($requestSession['key_count'] ?? 0),
            'items' => [
                'started' => (bool) ($requestSession['present'] ?? false),
                'driver' => $requestSession['driver'] ?? '—',
                'keys' => $requestSession['keys'] ?? [],
                'flash keys' => $requestSession['flash_keys'] ?? [],
                'error bags' => $requestSession['error_bags'] ?? [],
            ],
        ],
    ]
)

@if ($isHttpRequest)
    <div data-ndb-request-trace>
        <ol data-ndb-request-timeline class="ndb:list-none ndb:p-0 ndb:sm:px-6" aria-label="Request trace">
            <x-newdebugbar::request-step
                data-ndb-request-step="received"
                label="Received"
                icon="received"
                tone="received"
            >
                <x-slot:primary>
                    <div
                        x-data="{ copyFeedback: '', requestUrl: @js($requestUrl) }"
                        class="ndb:flex ndb:min-w-0 ndb:items-start ndb:gap-3"
                    >
                        <x-newdebugbar::inspector-operation-badge
                            data-ndb-request-method
                            class="ndb:mt-1 ndb:leading-4"
                        >
                            {{ $requestPayload['method'] ?? 'HTTP' }}
                        </x-newdebugbar::inspector-operation-badge>
                        <span
                            data-ndb-request-path
                            class="ndb:min-w-0 ndb:font-semibold ndb:[overflow-wrap:anywhere]"
                        >{{ $requestPath }}</span>
                        @if ($requestUrl !== '')
                            <x-newdebugbar::icon-button
                                data-ndb-request-copy
                                :color-only="true"
                                aria-label="Copy request URL"
                                x-bind:aria-label="copyFeedback || 'Copy request URL'"
                                @click="copyFeedback = (await copyText(requestUrl)) ? 'Copied' : 'Copy failed'"
                                @blur="copyFeedback = ''"
                                class="ndb:relative ndb:size-7 ndb:shrink-0 ndb:rounded-md"
                            >
                                <x-newdebugbar::icon name="copy" class="ndb:size-4" />
                                <span
                                    x-cloak
                                    x-show.important="copyFeedback"
                                    x-text="copyFeedback"
                                    role="status"
                                    aria-live="polite"
                                    class="ndb:pointer-events-none ndb:absolute ndb:bottom-full ndb:left-1/2 ndb:mb-1 ndb:-translate-x-1/2 ndb:rounded-md ndb:bg-zinc-900 ndb:px-2 ndb:py-1 ndb:text-xs ndb:whitespace-nowrap ndb:text-white ndb:dark:bg-zinc-100 ndb:dark:text-zinc-900"
                                ></span>
                            </x-newdebugbar::icon-button>
                        @endif
                    </div>
                </x-slot:primary>

                @if ($requestOrigin !== '')
                    <p class="ndb:text-sm ndb:leading-5 ndb:text-zinc-500 ndb:[overflow-wrap:anywhere] ndb:dark:text-zinc-400">
                        {{ $requestOrigin }}
                    </p>
                @endif
                @if ($requestSize > 0)
                    <dl data-ndb-request-size class="ndb:mt-3 ndb:flex ndb:gap-3 ndb:text-sm ndb:leading-5">
                        <dt class="ndb:text-zinc-500 ndb:dark:text-zinc-400">Request size</dt>
                        <dd class="ndb:tabular-nums">{{ $formatRequestBytes($requestSize) }}</dd>
                    </dl>
                @endif
            </x-newdebugbar::request-step>

            <x-newdebugbar::request-step data-ndb-request-step="matched" label="Matched" icon="matched" tone="matched">
                <x-slot:primary>
                    <code
                        data-ndb-request-controller
                        data-ndb-language="php"
                        class="ndb:font-medium ndb:[overflow-wrap:anywhere]"
                    >{{ $requestActionName }}</code>
                </x-slot:primary>

                @if ($requestActionNamespace !== '')
                    <code
                        data-ndb-language="php"
                        class="ndb:block ndb:text-sm ndb:leading-5 ndb:text-zinc-500 ndb:[overflow-wrap:anywhere] ndb:dark:text-zinc-400"
                    >{{ $requestActionNamespace }}</code>
                @endif
                <dl class="ndb:mt-4 ndb:grid ndb:max-w-3xl ndb:grid-cols-2 ndb:gap-x-5 ndb:gap-y-4 ndb:text-sm ndb:leading-5 ndb:lg:grid-cols-3">
                    <div class="ndb:min-w-0">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Route</dt>
                        <dd class="ndb:mt-1 ndb:[overflow-wrap:anywhere]">
                            {{ ($requestPayload['route'] ?? null) ?: 'Unnamed route' }}
                        </dd>
                    </div>
                    <div class="ndb:min-w-0">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Middleware</dt>
                        <dd class="ndb:mt-1">
                            @if ($requestMiddleware !== [])
                                <x-newdebugbar::request-middleware :middleware="$requestMiddleware" />
                            @else
                                None
                            @endif
                        </dd>
                    </div>
                    <div class="ndb:min-w-0">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Guard</dt>
                        <dd class="ndb:mt-1 ndb:[overflow-wrap:anywhere]">
                            {{ $requestAuthentication['guard'] ?? 'unknown' }}
                        </dd>
                    </div>
                    <div class="ndb:col-span-full ndb:min-w-0">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Authentication</dt>
                        <dd class="ndb:mt-1 ndb:[overflow-wrap:anywhere]">
                            @if (($requestPayload['authenticated'] ?? false) && ! empty($requestAuthentication['model']))
                                <code
                                    data-ndb-language="php"
                                    class="ndb:text-sm"
                                >{{ $requestAuthentication['model'] }}</code>
                            @else
                                {{ ($requestPayload['authenticated'] ?? false) ? 'Authenticated' : 'Guest' }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-newdebugbar::request-step>

            <x-newdebugbar::request-step
                data-ndb-request-step="responded"
                label="Responded"
                :icon="$requestResponseTone"
                :tone="$requestResponseTone"
                :last="true"
            >
                <x-slot:primary>
                    <p
                        data-ndb-request-status
                        @class([
                            'ndb:text-xl ndb:font-medium ndb:leading-7 ndb:tabular-nums ndb:[overflow-wrap:anywhere] ndb:lg:text-2xl',
                            'ndb:text-emerald-600 ndb:dark:text-emerald-400' => $requestSucceeded,
                            'ndb:text-red-600 ndb:dark:text-red-400' => $requestFailed,
                        ])
                    >
                        {{ trim(($requestStatus ?: '—').' '.$requestStatusText) }}
                    </p>
                </x-slot:primary>

                <dl class="ndb:mt-2 ndb:grid ndb:max-w-3xl ndb:grid-cols-2 ndb:gap-x-5 ndb:gap-y-4 ndb:text-sm ndb:leading-5 ndb:lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
                    <div class="ndb:col-span-full ndb:min-w-0 ndb:lg:col-span-1">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Content type</dt>
                        <dd class="ndb:mt-1 ndb:[overflow-wrap:anywhere]">
                            {{ ($requestPayload['content_type'] ?? null) ?: '—' }}
                        </dd>
                    </div>
                    <div class="ndb:min-w-0">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Response size</dt>
                        <dd class="ndb:mt-1 ndb:tabular-nums">
                            {{ $formatRequestBytes((int) ($requestPayload['response_size_bytes'] ?? 0)) }}
                        </dd>
                    </div>
                    <div class="ndb:min-w-0">
                        <dt class="ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Total duration</dt>
                        <dd class="ndb:mt-1 ndb:tabular-nums">{{ $requestDuration }}</dd>
                    </div>
                </dl>
            </x-newdebugbar::request-step>
        </ol>
    </div>

    <details
        data-ndb-request-details
        class="ndb:group ndb:mt-4 ndb:overflow-hidden ndb:rounded-xl ndb:border ndb:border-zinc-200/90 ndb:bg-white/45 ndb:sm:mx-6 ndb:sm:mt-8 ndb:dark:border-zinc-800 ndb:dark:bg-zinc-900/25"
    >
        <summary class="ndb:flex ndb:cursor-pointer ndb:list-none ndb:items-center ndb:gap-3 ndb:px-3 ndb:py-3 ndb:focus-visible:outline-2 ndb:focus-visible:outline-inset ndb:focus-visible:outline-indigo-500 ndb:sm:px-4">
            <span class="ndb:min-w-0 ndb:flex-1">
                <span class="ndb:block ndb:text-sm ndb:font-semibold">Request details</span>
                <span class="ndb:mt-0.5 ndb:block ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">Headers, input, query parameters, and session</span>
            </span>
            <x-newdebugbar::icon
                name="chevron-down"
                class="ndb:size-3.5 ndb:text-zinc-400 ndb:transition ndb:group-open:rotate-180"
            />
        </summary>
        <div
            x-data="{ requestDetail: 'headers' }"
            class="ndb:border-t ndb:border-zinc-200/90 ndb:sm:grid ndb:sm:grid-cols-[11rem_minmax(0,1fr)] ndb:dark:border-zinc-800"
        >
            <div class="ndb:grid ndb:grid-cols-2 ndb:gap-1 ndb:border-b ndb:border-zinc-200/90 ndb:bg-zinc-50/70 ndb:p-2 ndb:sm:block ndb:sm:border-r ndb:sm:border-b-0 ndb:dark:border-zinc-800 ndb:dark:bg-zinc-900/50">
                @foreach ($requestDetailGroups as $requestDetailKey => $requestDetailGroup)
                    <button
                        type="button"
                        data-ndb-request-detail="{{ $requestDetailKey }}"
                        @click="requestDetail = @js($requestDetailKey)"
                        :aria-pressed="requestDetail === @js($requestDetailKey)"
                        :class="requestDetail === @js($requestDetailKey) ? 'ndb:bg-indigo-50 ndb:text-indigo-700 ndb:dark:bg-indigo-950/70 ndb:dark:text-indigo-300' : 'ndb:text-zinc-600 ndb:hover:bg-white ndb:hover:text-zinc-950 ndb:dark:text-zinc-400 ndb:dark:hover:bg-zinc-800 ndb:dark:hover:text-white'"
                        class="ndb:flex ndb:w-full ndb:min-w-0 ndb:items-center ndb:gap-2 ndb:rounded-lg ndb:px-3 ndb:py-2 ndb:text-left ndb:transition ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-1 ndb:focus-visible:outline-indigo-500"
                    >
                        <span class="ndb:min-w-0 ndb:flex-1 ndb:truncate ndb:text-xs ndb:font-bold">{{ $requestDetailGroup['label'] }}</span>
                        <span
                            data-ndb-request-detail-count
                            class="ndb:shrink-0 ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-400"
                        >{{ $requestDetailGroup['count'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="ndb:min-w-0 ndb:p-3 ndb:sm:p-4">
                @foreach ($requestDetailGroups as $requestDetailKey => $requestDetailGroup)
                    <div
                        data-ndb-request-detail-panel="{{ $requestDetailKey }}"
                        x-show.important="requestDetail === @js($requestDetailKey)"
                    >
                        <div class="ndb:flex ndb:items-center ndb:justify-between ndb:gap-3">
                            <div class="ndb:flex ndb:min-w-0 ndb:items-baseline ndb:gap-3">
                                <h3 class="ndb:text-xs ndb:font-bold">{{ $requestDetailGroup['label'] }}</h3>
                                <span
                                    data-ndb-request-detail-panel-count
                                    class="ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-400"
                                >{{ $requestDetailGroup['count'] }}</span>
                            </div>
                            <button
                                type="button"
                                @click="copyText(@js(json_encode($requestDetailGroup['items'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)))"
                                class="ndb:shrink-0 ndb:text-xs ndb:font-bold ndb:text-indigo-600 ndb:focus-visible:outline-2 ndb:focus-visible:outline-offset-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-indigo-300"
                            >
                                Copy all
                            </button>
                        </div>

                        <div class="ndb:mt-3 ndb:overflow-x-auto">
                            @if ($requestDetailGroup['items'] !== [])
                                <table class="ndb:w-full ndb:table-fixed ndb:border-collapse ndb:text-left">
                                    <thead>
                                        <tr class="ndb:border-b ndb:border-zinc-200/90 ndb:dark:border-zinc-800">
                                            <th
                                                scope="col"
                                                class="ndb:w-2/5 ndb:pb-2 ndb:pr-4 ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                                            >
                                                Name
                                            </th>
                                            <th
                                                scope="col"
                                                class="ndb:pb-2 ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400"
                                            >
                                                Value
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($requestDetailGroup['items'] as $requestDetailName => $requestDetailValue)
                                            <tr class="ndb:border-b ndb:border-zinc-200/70 ndb:last:border-b-0 ndb:dark:border-zinc-800/80">
                                                <th
                                                    scope="row"
                                                    class="ndb:py-2 ndb:pr-4 ndb:align-top ndb:text-xs ndb:font-medium ndb:text-zinc-600 ndb:dark:text-zinc-300"
                                                >
                                                    {{ $requestDetailName }}
                                                </th>
                                                <td class="ndb:break-words ndb:py-2 ndb:align-top ndb:text-xs ndb:text-zinc-800 ndb:dark:text-zinc-200">
                                                    {{ $formatRequestValue($requestDetailValue) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="ndb:rounded-lg ndb:bg-zinc-50 ndb:px-3 ndb:py-3 ndb:text-xs ndb:text-zinc-500 ndb:sm:py-4 ndb:dark:bg-zinc-900 ndb:dark:text-zinc-400">
                                    No {{ strtolower($requestDetailGroup['label']) }} were captured.
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </details>
@else
    <div class="ndb:rounded-xl ndb:border ndb:border-zinc-200 ndb:p-3 ndb:sm:p-4 ndb:dark:border-zinc-800">
        <h3 class="ndb:text-xs ndb:font-bold">Runtime summary</h3>
        <dl class="ndb:mt-3 ndb:grid ndb:grid-cols-2 ndb:gap-x-3 ndb:gap-y-2 ndb:sm:mt-4 ndb:sm:grid-cols-4 ndb:sm:gap-x-5 ndb:sm:gap-y-3">
            @foreach ([
                ['Type', str($profile['profile_type'] ?? 'runtime')->replace('_', ' ')->title()],
                ['Name', ($requestPayload['name'] ?? null) ?: $requestPath],
                ['Status', $requestPayload['exit_code'] ?? $requestPayload['status'] ?? '—'],
                ['Duration', $requestDuration],
            ] as [$label, $value])
                <div class="ndb:min-w-0">
                    <dt class="ndb:text-xs ndb:font-semibold ndb:uppercase ndb:tracking-wider ndb:text-zinc-400">
                        {{ $label }}
                    </dt>
                    <dd class="ndb:mt-1 ndb:truncate ndb:text-xs ndb:font-semibold">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
@endif

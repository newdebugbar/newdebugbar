@php
    $statusLabels = [
        'queued' => 'Queued',
        'delayed' => 'Delayed',
        'processing' => 'Processing',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'waiting' => 'Retry pending',
        'completed' => 'Completed',
    ];
    $statusDescriptions = [
        'queued' => 'Laravel handed this job to the queue. A worker outcome has not been linked yet.',
        'delayed' => 'Laravel queued this job with a delay. Check again after the delay has passed.',
        'processing' => 'A worker is processing this job now.',
        'sent' => 'The linked worker sent this communication.',
        'failed' => 'The worker failed this job and no retry remains.',
        'waiting' => 'A worker attempt failed, but Laravel can try the job again.',
        'completed' => 'The linked worker completed this job.',
    ];
    $statusClasses = [
        'queued' => 'ndb:bg-sky-100 ndb:text-sky-700 ndb:dark:bg-sky-950 ndb:dark:text-sky-300',
        'delayed' => 'ndb:bg-amber-100 ndb:text-amber-700 ndb:dark:bg-amber-950 ndb:dark:text-amber-300',
        'processing' => 'ndb:bg-indigo-100 ndb:text-indigo-700 ndb:dark:bg-indigo-950 ndb:dark:text-indigo-300',
        'sent' => 'ndb:bg-emerald-100 ndb:text-emerald-700 ndb:dark:bg-emerald-950 ndb:dark:text-emerald-300',
        'completed' => 'ndb:bg-emerald-100 ndb:text-emerald-700 ndb:dark:bg-emerald-950 ndb:dark:text-emerald-300',
        'failed' => 'ndb:bg-red-100 ndb:text-red-700 ndb:dark:bg-red-950 ndb:dark:text-red-300',
        'waiting' => 'ndb:bg-amber-100 ndb:text-amber-700 ndb:dark:bg-amber-950 ndb:dark:text-amber-300',
    ];
    $formatDuration = \NewDebugBar\Support\DurationFormatter::format(...);
    $queueItems = collect($section['payload']['items'] ?? [])->values()->map(function (array $item, int $index) use ($profileId, $statusDescriptions, $statusLabels, $formatDuration): array {
        $fallbackStatus = match ($item['kind'] ?? null) {
            'failed' => 'failed',
            'executed' => 'completed',
            default => 'queued',
        };
        $status = is_string($item['status'] ?? null) && $item['status'] !== '' ? $item['status'] : $fallbackStatus;
        $statusGroup = match ($status) {
            'queued', 'delayed', 'processing', 'waiting' => 'waiting',
            'failed' => 'failed',
            default => 'completed',
        };
        $isOrigin = (bool) ($item['is_origin'] ?? false);
        $relatedProfileId = $isOrigin ? ($item['worker_profile_id'] ?? null) : ($item['origin_profile_id'] ?? null);
        $relatedProfileId = is_string($relatedProfileId) && $relatedProfileId !== $profileId ? $relatedProfileId : null;
        $channels = array_values(array_filter(
            (array) ($item['channels'] ?? []),
            static fn (mixed $channel): bool => is_string($channel) && $channel !== '',
        ));
        $notifiableTypes = array_values(array_filter(
            (array) ($item['notifiable_types'] ?? []),
            static fn (mixed $type): bool => is_string($type) && $type !== '',
        ));
        $communicationType = is_string($item['communication_type'] ?? null) && $item['communication_type'] !== ''
            ? $item['communication_type']
            : null;
        $mailChannel = $communicationType === 'mail' || in_array('mail', $channels, true);
        $relatedSection = $isOrigin && $mailChannel && $status === 'sent' ? 'mail' : 'queue';
        $job = is_string($item['job'] ?? null) && $item['job'] !== '' ? $item['job'] : 'Job';
        $connection = is_string($item['connection'] ?? null) && $item['connection'] !== ''
            ? $item['connection']
            : 'default connection';
        $queue = is_string($item['queue'] ?? null) && $item['queue'] !== '' ? $item['queue'] : 'default queue';
        $attempt = $item['attempt'] ?? $item['activity_attempt'] ?? null;
        $delay = is_numeric($item['delay_seconds'] ?? null) ? max(0, (int) $item['delay_seconds']) : null;
        $duration = is_numeric($item['duration_ms'] ?? null) ? max(0, (float) $item['duration_ms']) : 0.0;
        $at = is_numeric($item['at_ms'] ?? null) ? max(0, (float) $item['at_ms']) : null;
        $afterResponse = is_numeric($item['after_response_ms'] ?? null)
            ? max(0, (float) $item['after_response_ms'])
            : null;
        $attempts = collect($item['attempts'] ?? [])->filter(
            static fn (mixed $attempt): bool => is_array($attempt),
        )->values()->map(static function (array $attempt, int $attemptIndex): array {
            $status = is_string($attempt['status'] ?? null) && $attempt['status'] !== '' ? $attempt['status'] : 'recorded';

            return [
                'sequence' => $attemptIndex + 1,
                'attempt' => is_numeric($attempt['attempt'] ?? null) ? (int) $attempt['attempt'] : null,
                'status' => $status,
                'status_label' => \Illuminate\Support\Str::headline($status),
                'profile_id' => is_string($attempt['profile_id'] ?? null) ? $attempt['profile_id'] : null,
                'exception_class' => is_string($attempt['exception_class'] ?? null) ? $attempt['exception_class'] : null,
                'recorded_at' => is_string($attempt['recorded_at'] ?? null) ? $attempt['recorded_at'] : null,
            ];
        })->all();
        $exceptionClass = is_string($item['exception_class'] ?? null) ? $item['exception_class'] : null;
        $jobId = is_scalar($item['job_id'] ?? null) && (string) $item['job_id'] !== '' ? (string) $item['job_id'] : null;

        return [
            'execution' => $index + 1,
            'kind' => (string) ($item['kind'] ?? 'queued'),
            'status' => $status,
            'status_group' => $statusGroup,
            'status_label' => $statusLabels[$status] ?? \Illuminate\Support\Str::headline($status),
            'status_description' => $statusDescriptions[$status] ?? 'Queue activity was captured for this job.',
            'job' => $job,
            'job_label' => class_basename($job),
            'connection' => $connection,
            'queue' => $queue,
            'job_id' => $jobId,
            'delay_seconds' => $delay,
            'delay_label' => $delay === null || $delay === 0 ? 'None' : $delay.' s',
            'duration_ms' => $duration,
            'duration_label' => $formatDuration($duration),
            'at_ms' => $at,
            'at_label' => $at === null ? '—' : $formatDuration($at),
            'after_response_ms' => $afterResponse,
            'after_response_label' => $afterResponse === null ? null : $formatDuration($afterResponse),
            'attempt' => is_numeric($attempt) ? (int) $attempt : null,
            'lifecycle' => is_string($item['lifecycle'] ?? null) ? $item['lifecycle'] : null,
            'communication_type' => $communicationType,
            'communication_label' => $communicationType === null ? null : \Illuminate\Support\Str::headline($communicationType),
            'communication_class' => is_string($item['communication_class'] ?? null) ? $item['communication_class'] : null,
            'channels' => $channels,
            'display_channels' => $channels === [$communicationType] ? [] : $channels,
            'notifiable_types' => $notifiableTypes,
            'notifiable_count' => max(0, (int) ($item['notifiable_count'] ?? 0)),
            'recipient_count' => max(0, (int) ($item['recipient_count'] ?? 0)),
            'exception_class' => $exceptionClass,
            'will_retry' => (bool) ($item['will_retry'] ?? $status === 'waiting'),
            'attempts' => $attempts,
            'related_profile_id' => $relatedProfileId,
            'related_section' => $relatedSection,
            'related_label' => $isOrigin
                ? ($relatedSection === 'mail' ? 'Open mail preview' : 'Open worker')
                : 'Open request',
        ];
    })->all();
    $queueCount = count($queueItems);
    $groupCounts = collect($queueItems)->countBy('status_group');
    $filters = array_filter([
        'all' => ['All', $queueCount],
        'waiting' => ['Waiting', (int) $groupCounts->get('waiting', 0)],
        'failed' => ['Failed', (int) $groupCounts->get('failed', 0)],
        'completed' => ['Completed', (int) $groupCounts->get('completed', 0)],
    ], static fn (array $filter, string $key): bool => $key === 'all' || $filter[1] > 0, ARRAY_FILTER_USE_BOTH);
    $totalDuration = (float) ($section['summary']['duration_ms'] ?? 0);
    $failureCount = (int) ($section['summary']['failed_count'] ?? 0);
    $waitingCount = (int) $groupCounts->get('waiting', 0);
    $summaryParts = [$formatDuration($totalDuration).' total'];

    if ($waitingCount > 0) {
        $summaryParts[] = number_format($waitingCount).' waiting';
    }

    if ($failureCount > 0) {
        $summaryParts[] = number_format($failureCount).' '.\Illuminate\Support\Str::plural('failure', $failureCount);
    }
@endphp

<div
    data-ndb-queue
    x-init="initializeQueue(JSON.parse(atob($el.querySelector('[data-ndb-queue-payload]').textContent.trim())))"
    class="ndb:border-l-0 ndb:bg-transparent ndb:text-zinc-950 ndb:dark:text-white ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col"
>
    <script type="application/json" data-ndb-queue-payload>
        {{ base64_encode(json_encode($queueItems, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)) }}
    </script>

    @if ($queueItems !== [])
        <x-newdebugbar::inspector-workspace frame="top" data-ndb-queue-workspace class="ndb:border-x-0">
            <x-newdebugbar::inspector-list-panel detail-open="queueDetailOpen" list-ref="queueList">
                <x-slot:controls>
                    <div class="ndb:flex ndb:items-start ndb:justify-between ndb:gap-3">
                        <div class="ndb:min-w-0">
                            <p class="ndb:text-xs ndb:font-bold ndb:text-zinc-700 ndb:dark:text-zinc-200">
                                {{ number_format($queueCount) }} {{ \Illuminate\Support\Str::plural('job', $queueCount) }}
                                <span
                                    x-show.important="visibleQueueCount !== queueActivities.length"
                                    class="ndb:ml-1 ndb:text-xs ndb:font-medium ndb:text-zinc-500 ndb:dark:text-zinc-400"
                                ><span data-ndb-queue-visible-count x-text="visibleQueueCount"></span> shown</span>
                            </p>
                            <p class="ndb:mt-0.5 ndb:text-xs ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                {{ implode(', ', $summaryParts) }}
                            </p>
                        </div>
                        @if (($profile['background_activity']['pending'] ?? false) === true)
                            <button
                                type="button"
                                data-ndb-background-refresh
                                @click="refreshBackgroundActivity(true)"
                                class="ndb:inline-flex ndb:h-9 ndb:min-h-0 ndb:shrink-0 ndb:items-center ndb:gap-1.5 ndb:rounded-lg ndb:border ndb:border-zinc-200 ndb:bg-transparent ndb:px-2.5 ndb:text-xs ndb:font-bold ndb:text-indigo-600 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:border-zinc-700 ndb:dark:text-indigo-300"
                            >
                                <x-newdebugbar::icon name="activity" size="3.5" />
                                Check worker
                            </button>
                        @endif
                    </div>

                    <x-newdebugbar::inspector-list-controls :show-search="$queueCount >= 5">
                        <x-slot:search>
                            <x-newdebugbar::search-field
                                label="Search queue activity"
                                placeholder="Search jobs or queues"
                                data-ndb-queue-search
                                x-model="queueSearch"
                                @input.debounce.100ms="applyQueueView()"
                            />
                        </x-slot:search>
                        <x-slot:filter>
                            <x-newdebugbar::select-field
                                label="Filter queue activity"
                                data-ndb-queue-filter
                                x-model="queueFilter"
                                @change="setQueueFilter($event.target.value)"
                            >
                                @foreach ($filters as $filter => [$label, $count])
                                    <option value="{{ $filter }}">{{ $label }} ({{ $count }})</option>
                                @endforeach
                            </x-newdebugbar::select-field>
                        </x-slot:filter>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list data-ndb-queue-list>
                    @foreach ($queueItems as $item)
                        <button
                            type="button"
                            wire:key="queue-activity-{{ $item['execution'] }}"
                            data-ndb-queue-item="{{ $item['execution'] }}"
                            data-ndb-queue-execution="{{ $item['execution'] }}"
                            data-ndb-queue-status="{{ $item['status'] }}"
                            data-ndb-queue-group="{{ $item['status_group'] }}"
                            aria-controls="newdebugbar-queue-detail"
                            @click="selectQueueActivity({{ $item['execution'] }})"
                            :aria-pressed="queueSelected === {{ $item['execution'] }}"
                            :class="queueSelected === {{ $item['execution'] }}
                                ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                                : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
                            class="ndb:grid ndb:h-auto ndb:min-h-0 ndb:w-full ndb:min-w-0 ndb:grid-cols-[4.75rem_minmax(0,1fr)_4.5rem] ndb:items-center ndb:gap-x-2 ndb:border-l-0 ndb:bg-transparent ndb:px-3 ndb:py-2.5 ndb:text-left ndb:text-xs ndb:text-zinc-950 ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:dark:text-white"
                        >
                            <span class="ndb:row-span-2 ndb:inline-flex ndb:w-fit ndb:rounded-md ndb:px-2 ndb:py-1 ndb:text-xs ndb:font-bold {{ $statusClasses[$item['status']] ?? 'ndb:bg-zinc-100 ndb:text-zinc-600 ndb:dark:bg-zinc-900 ndb:dark:text-zinc-300' }}">
                                {{ $item['status_label'] }}
                            </span>
                            <span
                                class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-bold"
                                title="{{ $item['job'] }}"
                            >{{ $item['job_label'] }}</span>
                            <span class="ndb:text-right ndb:text-xs ndb:font-semibold ndb:tabular-nums ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                {{ $item['kind'] === 'queued' ? $item['delay_label'] : $item['duration_label'] }}
                            </span>
                            <span class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:text-zinc-500 ndb:dark:text-zinc-400">
                                {{ $item['connection'] }}, {{ $item['queue'] }}
                            </span>
                            <span class="ndb:text-right ndb:text-xs ndb:text-zinc-400">{{ $item['attempt'] === null ? '' : 'Attempt '.$item['attempt'] }}</span>
                        </button>
                    @endforeach
                </x-slot:list>

                <x-slot:empty x-show.important="visibleQueueCount === 0">
                    <x-newdebugbar::empty-state label="No queue activity matches these controls." />
                </x-slot:empty>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::inspector-detail-pane
                detail-open="queueDetailOpen"
                detail-ref="queueDetail"
                detail-label="Selected queue activity details"
                back-label="Queue"
                close-action="closeQueueDetail()"
                id="newdebugbar-queue-detail"
                data-ndb-queue-detail
                class="ndb:border-x-0 ndb:bg-transparent"
            >
                <x-slot:back>
                    <x-newdebugbar::inspector-detail-back
                        data-ndb-queue-back
                        @click="closeQueueDetail()"
                        label="Queue"
                    />
                </x-slot:back>

                <template x-if="selectedQueueActivity">
                    <div class="ndb:flex ndb:flex-col">
                        <x-newdebugbar::inspector-detail-header data-ndb-queue-detail-header>
                            <x-slot:title>
                                <h3
                                    class="ndb:min-w-0 ndb:break-all ndb:font-mono ndb:text-sm ndb:font-bold"
                                    x-text="selectedQueueActivity.job"
                                ></h3>
                            </x-slot:title>
                            <x-slot:aside>
                                <div class="ndb:flex ndb:flex-wrap ndb:items-center ndb:gap-2">
                                    <span
                                        data-ndb-queue-detail-status
                                        class="ndb:inline-flex ndb:justify-self-end ndb:rounded-md ndb:px-2 ndb:py-1 ndb:text-xs ndb:font-bold"
                                        :class="{
                                            'ndb:bg-red-100 ndb:text-red-700 ndb:dark:bg-red-950 ndb:dark:text-red-300':
                                                selectedQueueActivity.status === 'failed',
                                            'ndb:bg-amber-100 ndb:text-amber-700 ndb:dark:bg-amber-950 ndb:dark:text-amber-300':
                                                ['delayed', 'waiting'].includes(selectedQueueActivity.status),
                                            'ndb:bg-indigo-100 ndb:text-indigo-700 ndb:dark:bg-indigo-950 ndb:dark:text-indigo-300':
                                                selectedQueueActivity.status === 'processing',
                                            'ndb:bg-sky-100 ndb:text-sky-700 ndb:dark:bg-sky-950 ndb:dark:text-sky-300':
                                                selectedQueueActivity.status === 'queued',
                                            'ndb:bg-emerald-100 ndb:text-emerald-700 ndb:dark:bg-emerald-950 ndb:dark:text-emerald-300':
                                                ['sent', 'completed'].includes(selectedQueueActivity.status),
                                        }"
                                        x-text="selectedQueueActivity.status_label"
                                    ></span>
                                    <x-newdebugbar::inspector-action
                                        icon="external-link"
                                        data-ndb-queue-profile-link
                                        x-show.important="selectedQueueActivity.related_profile_id"
                                        @click="
                                            openRelatedProfile(
                                                selectedQueueActivity.related_profile_id,
                                                selectedQueueActivity.related_section,
                                            )
                                        "
                                        class="ndb:h-8 ndb:min-h-0 ndb:bg-transparent ndb:px-2"
                                        ><span x-text="selectedQueueActivity.related_label"></span
                                    ></x-newdebugbar::inspector-action>
                                </div>
                            </x-slot:aside>
                            <x-slot:metadata>
                                <div>
                                    <dt class="ndb:text-zinc-400">Connection</dt>
                                    <dd class="ndb:font-semibold" x-text="selectedQueueActivity.connection"></dd>
                                </div>
                                <div>
                                    <dt class="ndb:text-zinc-400">Queue</dt>
                                    <dd class="ndb:font-semibold" x-text="selectedQueueActivity.queue"></dd>
                                </div>
                                <div>
                                    <dt class="ndb:text-zinc-400">Captured at</dt>
                                    <dd
                                        class="ndb:font-semibold ndb:tabular-nums"
                                        x-text="selectedQueueActivity.at_label"
                                    ></dd>
                                </div>
                            </x-slot:metadata>
                        </x-newdebugbar::inspector-detail-header>

                        <div data-ndb-queue-detail-content class="ndb:space-y-3 ndb:p-3 ndb:sm:space-y-4 ndb:sm:p-4">
                            <p
                                class="ndb:text-xs ndb:leading-5 ndb:text-zinc-600 ndb:dark:text-zinc-300"
                                x-text="selectedQueueActivity.status_description"
                            ></p>

                            <x-newdebugbar::inspector-facts columns="4" data-ndb-queue-facts>
                                <x-newdebugbar::inspector-fact label="Job ID"
                                    ><x-slot:value x-text="selectedQueueActivity.job_id ?? '—'"></x-slot:value
                                ></x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Duration"
                                    ><x-slot:value
                                        class="ndb:tabular-nums"
                                        x-text="selectedQueueActivity.duration_label"
                                    ></x-slot:value
                                ></x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Delay"
                                    ><x-slot:value
                                        class="ndb:tabular-nums"
                                        x-text="selectedQueueActivity.delay_label"
                                    ></x-slot:value
                                ></x-newdebugbar::inspector-fact>
                                <x-newdebugbar::inspector-fact label="Attempt"
                                    ><x-slot:value
                                        class="ndb:tabular-nums"
                                        x-text="selectedQueueActivity.attempt ?? '—'"
                                    ></x-slot:value
                                ></x-newdebugbar::inspector-fact>
                            </x-newdebugbar::inspector-facts>

                            <dl
                                data-ndb-queue-communication
                                x-show.important="selectedQueueActivity.communication_type"
                                class="ndb:divide-y ndb:divide-zinc-200/90 ndb:bg-transparent ndb:dark:divide-zinc-800"
                            >
                                <x-newdebugbar::inspector-definition-row label="Type"
                                    ><x-slot:value x-text="selectedQueueActivity.communication_label"></x-slot:value
                                ></x-newdebugbar::inspector-definition-row>
                                <template x-if="selectedQueueActivity.display_channels.length">
                                    <x-newdebugbar::inspector-definition-row label="Channels"
                                        ><x-slot:value
                                            x-text="selectedQueueActivity.display_channels.join(', ')"
                                        ></x-slot:value
                                    ></x-newdebugbar::inspector-definition-row>
                                </template>
                                <x-newdebugbar::inspector-definition-row label="Targets"
                                    ><x-slot:value
                                        x-text="
                                            selectedQueueActivity.recipient_count ||
                                            selectedQueueActivity.notifiable_count ||
                                            '—'
                                        "
                                    ></x-slot:value
                                ></x-newdebugbar::inspector-definition-row>
                                <x-newdebugbar::inspector-definition-row label="Source"
                                    ><x-slot:value
                                        class="ndb:break-all ndb:font-mono"
                                        x-text="selectedQueueActivity.communication_class ?? selectedQueueActivity.job"
                                    ></x-slot:value
                                ></x-newdebugbar::inspector-definition-row>
                            </dl>

                            <section
                                x-show.important="selectedQueueActivity.exception_class"
                                class="ndb:rounded-lg ndb:border ndb:border-red-200 ndb:bg-red-50/55 ndb:p-3 ndb:dark:border-red-950 ndb:dark:bg-red-950/20"
                            >
                                <p class="ndb:text-xs ndb:font-bold ndb:text-red-700 ndb:dark:text-red-300">
                                    Worker exception
                                </p>
                                <p
                                    class="ndb:mt-1 ndb:break-all ndb:font-mono ndb:text-xs ndb:text-red-700 ndb:dark:text-red-300"
                                    x-text="selectedQueueActivity.exception_class"
                                ></p>
                                <p
                                    class="ndb:mt-1 ndb:text-xs ndb:leading-5 ndb:text-red-700/80 ndb:dark:text-red-300/80"
                                    x-text="
                                        selectedQueueActivity.will_retry && selectedQueueActivity.attempts.length > 0
                                            ? 'Laravel can retry this job. Check the retained worker attempts below.'
                                            : selectedQueueActivity.will_retry
                                              ? 'Laravel can retry this job. No worker attempt has been retained yet.'
                                              : 'Open the linked worker profile to inspect the failure in context.'
                                    "
                                ></p>
                            </section>

                            <p
                                data-ndb-queue-after-response
                                x-show.important="selectedQueueActivity.lifecycle === 'after_response'"
                                class="ndb:text-xs ndb:leading-5 ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            >
                                This job ran after Laravel sent the response<span
                                    x-show.important="selectedQueueActivity.after_response_label"
                                >
                                    at
                                    <span
                                        class="ndb:tabular-nums"
                                        x-text="selectedQueueActivity.after_response_label"
                                    ></span></span
                                >, so its time is not part of the response time.
                            </p>
                        </div>

                        @include('newdebugbar::livewire.sections.queue.attempts')
                    </div>
                </template>

                <x-newdebugbar::inspector-detail-empty
                    label="Choose a job to inspect its queue evidence."
                    x-show.important="! selectedQueueActivity"
                />
            </x-newdebugbar::inspector-detail-pane>
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No queue activity was captured." />
    @endif
</div>

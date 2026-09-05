{{-- Renders logical Laravel notifications with channel-level delivery diagnostics. --}}
@php
    $capturedNotificationItems = array_values($section['payload']['items'] ?? []);
    $notificationSummary = $section['summary'];
    $retainedMailIds = collect($profile['sections']['mail']['payload']['items'] ?? [])
        ->pluck('transport_message_id')
        ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
        ->values()
        ->all();
    $formatNotificationDestinations = static function (mixed $destination): array {
        if (is_scalar($destination)) {
            $label = trim((string) $destination);

            return $label === '' ? [] : [$label];
        }

        if (! is_array($destination) || $destination === []) {
            return [];
        }

        if (is_string($destination['type'] ?? null)) {
            $name = is_string($destination['name'] ?? null) && trim($destination['name']) !== ''
                ? trim($destination['name'])
                : null;
            $type = class_basename($destination['type']);
            $id = is_scalar($destination['id'] ?? null) ? (string) $destination['id'] : null;
            $context = $type.($id === null || $id === '' ? '' : ' #'.$id);

            return [$name === null || $name === $context ? $context : $name.' ('.$context.')'];
        }

        $parts = [];

        foreach ($destination as $key => $value) {
            if (is_string($key) && str_contains($key, '@')) {
                $name = is_scalar($value) ? trim((string) $value) : '';
                $parts[] = $name === '' ? $key : $name.' <'.$key.'>';
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $parts[] = trim((string) $value);
            }
        }

        if ($parts !== []) {
            return $parts;
        }

        $encoded = json_encode($destination, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? [$encoded] : [];
    };
    $notificationGroups = collect($capturedNotificationItems)
        ->groupBy(fn (array $item, int $index): string => (string) (
            $item['group_id'] ?? $item['correlation_key'] ?? 'notification-'.$index
        ))
        ->values()
        ->map(function ($attempts, int $groupIndex) use ($profileId, $retainedMailIds, $formatNotificationDestinations): array {
            $attempts = collect($attempts)->values();
            $first = $attempts->first();
            $notificationClass = (string) ($first['notification'] ?? 'Notification');
            $notifiableType = (string) ($first['notifiable_type'] ?? 'Notifiable');
            $notifiableId = $first['notifiable_id'] ?? null;
            $notificationSource = is_array($first['notification_source'] ?? null)
                ? $first['notification_source']
                : null;
            $notificationData = is_array($first['notification_data'] ?? null)
                ? $first['notification_data']
                : [];
            $deliveries = $attempts
                ->map(function (array $attempt, int $attemptIndex) use ($formatNotificationDestinations, $retainedMailIds): array {
                    $callsite = is_array($attempt['callsite'] ?? null) ? $attempt['callsite'] : null;
                    $stack = array_values(is_array($attempt['stack'] ?? null) ? $attempt['stack'] : []);
                    $channel = (string) ($attempt['channel'] ?? 'unknown');
                    $status = (string) ($attempt['status'] ?? 'sent');

                    if (! in_array($status, ['queued', 'delayed', 'processing', 'sent', 'failed', 'waiting'], true)) {
                        $status = 'sent';
                    }

                    $statusLabel = match ($status) {
                        'queued' => 'Queued',
                        'delayed' => 'Delayed',
                        'processing' => 'Processing',
                        'failed' => 'Failed',
                        'waiting' => 'Waiting for worker',
                        default => 'Sent to channel',
                    };
                    $response = $attempt['response'] ?? null;
                    $failureData = is_array($attempt['failure_data'] ?? null) ? $attempt['failure_data'] : [];
                    $mailMessageId = is_string($attempt['mail_message_id'] ?? null)
                        ? $attempt['mail_message_id']
                        : null;
                    $responseSummary = null;
                    $failureMessage = is_string($attempt['exception_message'] ?? null)
                        ? $attempt['exception_message']
                        : null;

                    if ($failureMessage === null) {
                        foreach (['reason', 'message', 'error', 'detail'] as $failureKey) {
                            if (is_scalar($failureData[$failureKey] ?? null)) {
                                $failureMessage = trim((string) $failureData[$failureKey]);

                                break;
                            }
                        }
                    }

                    if ($mailMessageId !== null) {
                        $responseSummary = 'Mail message '.$mailMessageId;
                    } elseif (is_array($response) && is_scalar($response['message_id'] ?? null)) {
                        $responseSummary = 'Message '.(string) $response['message_id'];
                    } elseif (is_array($response) && is_scalar($response['provider'] ?? null)) {
                        $responseSummary = (string) $response['provider'].' response';
                    } elseif (is_string($attempt['response_type'] ?? null)) {
                        $responseSummary = class_basename($attempt['response_type']);
                    }

                    $destinationLabels = $formatNotificationDestinations($attempt['destination'] ?? null);
                    $destinationLabel = implode(', ', $destinationLabels);
                    $destinationCount = count($destinationLabels);
                    $channelLabel = str_replace(
                        ['Sms', 'Mms', 'Api', 'Url'],
                        ['SMS', 'MMS', 'API', 'URL'],
                        \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', $channel)),
                    );
                    $evidenceSummary = match (true) {
                        $status === 'failed' => null,
                        in_array($status, ['queued', 'delayed', 'waiting'], true) => 'Waiting for a queue worker.',
                        $status === 'processing' => 'A queue worker is processing this notification.',
                        $channel === 'mail' && $mailMessageId !== null => 'Mail transport accepted the message.',
                        $channel === 'database' => 'Notification stored in the database.',
                        $response !== null => 'Channel returned a provider response.',
                        default => 'Channel completed without throwing.',
                    };

                    return [
                        'execution' => $attemptIndex + 1,
                        'channel' => $channel,
                        'channel_label' => $channelLabel,
                        'status' => $status,
                        'status_label' => $statusLabel,
                        'duration_ms' => (float) ($attempt['duration_ms'] ?? 0),
                        'duration_label' => \NewDebugBar\Support\DurationFormatter::format($attempt['duration_ms'] ?? 0),
                        'connection' => is_string($attempt['connection'] ?? null) ? $attempt['connection'] : null,
                        'queue' => is_string($attempt['queue'] ?? null) ? $attempt['queue'] : null,
                        'job_id' => is_scalar($attempt['job_id'] ?? null) ? (string) $attempt['job_id'] : null,
                        'delay_seconds' => is_numeric($attempt['delay_seconds'] ?? null) ? (int) $attempt['delay_seconds'] : null,
                        'lifecycle' => is_string($attempt['lifecycle'] ?? null) ? $attempt['lifecycle'] : null,
                        'destination' => $attempt['destination'] ?? null,
                        'destination_labels' => $destinationLabels,
                        'destination_label' => $destinationLabel === '' ? 'No destination resolved' : $destinationLabel,
                        'destination_summary_label' => match (true) {
                            $destinationCount === 0 => 'No destination resolved',
                            $destinationCount === 1 => $destinationLabel,
                            $channel === 'mail' => $destinationCount.' recipients',
                            default => $destinationCount.' destinations',
                        },
                        'destination_resolved' => $destinationCount > 0,
                        'response_type' => is_string($attempt['response_type'] ?? null) ? $attempt['response_type'] : null,
                        'response' => $response,
                        'response_summary' => $responseSummary,
                        'evidence_summary' => $evidenceSummary,
                        'failure_data' => $failureData,
                        'failure_message' => $failureMessage,
                        'exception_class' => is_string($attempt['exception_class'] ?? null) ? $attempt['exception_class'] : null,
                        'exception_message' => is_string($attempt['exception_message'] ?? null) ? $attempt['exception_message'] : null,
                        'exception_location' => is_array($attempt['exception_location'] ?? null) ? $attempt['exception_location'] : null,
                        'mail_message_id' => $mailMessageId,
                        'mail_available' => is_string($mailMessageId) && in_array($mailMessageId, $retainedMailIds, true),
                        'callsite' => $callsite,
                        'callsite_label' => $callsite === null ? 'Source unavailable' : $callsite['file'].':'.$callsite['line'],
                        'stack' => $stack,
                    ];
                })
                ->all();
            $sentCount = count(array_filter($deliveries, fn (array $delivery): bool => $delivery['status'] === 'sent'));
            $failedCount = count(array_filter($deliveries, fn (array $delivery): bool => $delivery['status'] === 'failed'));
            $deliveryStatuses = array_values(array_unique(array_column($deliveries, 'status')));
            $pendingStatus = collect(['processing', 'delayed', 'queued', 'waiting'])
                ->first(fn (string $candidate): bool => in_array($candidate, $deliveryStatuses, true));
            $status = match (true) {
                $failedCount === count($deliveries) => 'failed',
                $failedCount > 0 => 'partial',
                is_string($pendingStatus) => $pendingStatus,
                default => 'sent',
            };
            $statusLabel = match ($status) {
                'sent' => 'Sent',
                'failed' => 'Failed',
                'partial' => 'Needs attention',
                'queued' => 'Queued',
                'delayed' => 'Delayed',
                'processing' => 'Processing',
                default => 'Waiting for worker',
            };
            $recipientTypeLabel = class_basename($notifiableType);

            if (is_scalar($notifiableId) && (string) $notifiableId !== '') {
                $recipientTypeLabel .= ' #'.(string) $notifiableId;
            }

            $recipientName = is_string($first['notifiable_name'] ?? null) && trim($first['notifiable_name']) !== ''
                ? trim($first['notifiable_name'])
                : null;
            $recipientLabel = $recipientName ?? $recipientTypeLabel;
            $recipientContextLabel = $recipientName === null ? null : $recipientTypeLabel;

            $channels = array_values(array_unique(array_column($deliveries, 'channel_label')));
            $deliverySummary = implode(', ', array_map(
                fn (array $delivery): string => $delivery['channel_label'].' '.$delivery['status_label'],
                $deliveries,
            ));
            $callsite = collect($deliveries)->pluck('callsite')->first(fn (mixed $value): bool => is_array($value));
            $stack = collect($deliveries)->pluck('stack')->first(fn (mixed $value): bool => is_array($value) && $value !== []) ?? [];
            $notificationId = is_string($first['notification_id'] ?? null) ? $first['notification_id'] : null;
            $sourceLabel = $notificationSource === null
                ? 'Unavailable'
                : basename(str_replace('\\', '/', $notificationSource['file'])).':'.$notificationSource['line'];
            $callsiteLabel = $callsite === null ? 'Source unavailable' : $callsite['file'].':'.$callsite['line'];
            $callsiteShortLabel = $callsite === null
                ? 'Unavailable'
                : basename(str_replace('\\', '/', $callsite['file'])).':'.$callsite['line'];
            $isOrigin = (bool) ($first['is_origin'] ?? false);
            $relatedProfileId = $isOrigin
                ? ($first['worker_profile_id'] ?? null)
                : ($first['origin_profile_id'] ?? null);
            $relatedProfileId = is_string($relatedProfileId) && $relatedProfileId !== $profileId
                ? $relatedProfileId
                : null;
            $hasMailChannel = collect($deliveries)->contains(
                fn (array $delivery): bool => $delivery['channel'] === 'mail',
            );
            $relatedSection = $isOrigin && $hasMailChannel && $status === 'sent'
                ? 'mail'
                : 'notifications';
            $queueConnection = is_string($first['queue_connection'] ?? null)
                ? $first['queue_connection']
                : (is_string($first['connection'] ?? null) ? $first['connection'] : null);
            $queueName = is_string($first['queue_name'] ?? null)
                ? $first['queue_name']
                : (is_string($first['queue'] ?? null) ? $first['queue'] : null);
            $queueable = (bool) ($first['queueable'] ?? false) || $queueConnection !== null;
            $duration = (float) collect($deliveries)->sum('duration_ms');

            return [
                'execution' => $groupIndex + 1,
                'group_id' => (string) ($first['group_id'] ?? $first['correlation_key'] ?? 'notification-'.$groupIndex),
                'notification_id' => $notificationId,
                'notification' => $notificationClass,
                'label' => class_basename($notificationClass),
                'status' => $status,
                'status_label' => $statusLabel,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'duration_ms' => $duration,
                'duration_label' => \NewDebugBar\Support\DurationFormatter::format($duration),
                'delivery_count' => count($deliveries),
                'deliveries' => $deliveries,
                'channels' => $channels,
                'channels_label' => implode(', ', $channels),
                'channel_count_label' => count($channels).' '.\Illuminate\Support\Str::plural('channel', count($channels)),
                'delivery_summary' => $deliverySummary,
                'queueable' => $queueable,
                'queue_connection' => $queueConnection,
                'queue_name' => $queueName,
                'job_id' => is_scalar($first['job_id'] ?? null) ? (string) $first['job_id'] : null,
                'delay_seconds' => is_numeric($first['delay_seconds'] ?? null) ? (int) $first['delay_seconds'] : null,
                'lifecycle' => is_string($first['lifecycle'] ?? null) ? $first['lifecycle'] : null,
                'related_profile_id' => $relatedProfileId,
                'related_section' => $relatedSection,
                'related_label' => $isOrigin
                    ? ($relatedSection === 'mail' ? 'Open mail preview' : 'Open worker')
                    : 'Open request',
                'execution_mode_label' => $queueable
                    ? 'Queueable'.($queueName !== null ? ' on '.$queueName : '')
                    : 'Synchronous',
                'locale' => is_string($first['locale'] ?? null) ? $first['locale'] : null,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'recipient_name' => $recipientName,
                'recipient_label' => $recipientLabel,
                'recipient_context_label' => $recipientContextLabel,
                'routes' => is_array($first['routes'] ?? null) ? $first['routes'] : [],
                'notification_data' => $notificationData,
                'notification_source' => $notificationSource,
                'source_label' => $sourceLabel,
                'callsite' => $callsite,
                'callsite_label' => $callsiteLabel,
                'callsite_short_label' => $callsiteShortLabel,
                'stack' => $stack,
                'search' => mb_strtolower(implode(' ', array_filter([
                    $notificationClass,
                    $notifiableType,
                    is_scalar($notifiableId) ? (string) $notifiableId : null,
                    $recipientName,
                    $recipientLabel,
                    implode(' ', $channels),
                    $deliverySummary,
                    $statusLabel,
                    $queueConnection,
                    $queueName,
                    is_scalar($first['job_id'] ?? null) ? (string) $first['job_id'] : null,
                    $callsite === null ? null : $callsite['file'],
                    ...array_column($deliveries, 'destination_label'),
                    ...array_column($deliveries, 'failure_message'),
                ]))),
            ];
        })
        ->all();
    $notificationFilters = [
        'all' => ['All', count($notificationGroups)],
        'attention' => ['Needs attention', count(array_filter($notificationGroups, fn (array $item): bool => $item['status'] !== 'sent'))],
        'sent' => ['Sent', count(array_filter($notificationGroups, fn (array $item): bool => $item['status'] === 'sent'))],
    ];
@endphp

<div
    data-ndb-notifications
    x-init="
        initializeNotifications(
            JSON.parse(
                new TextDecoder().decode(
                    Uint8Array.from(
                        atob($el.querySelector('[data-ndb-notification-payload]').textContent.trim()),
                        (character) => character.charCodeAt(0),
                    ),
                ),
            ),
        )
    "
    class="ndb:space-y-4 ndb:lg:flex ndb:lg:min-h-0 ndb:lg:flex-1 ndb:lg:flex-col ndb:lg:space-y-0"
>
    <script type="application/json" data-ndb-notification-payload>
        {{ base64_encode(\Illuminate\Support\Js::encode($notificationGroups)) }}
    </script>

    @if ($notificationGroups !== [])
        <x-newdebugbar::inspector-workspace frame="top" data-ndb-notification-workspace>
            <x-newdebugbar::inspector-list-panel detail-open="notificationDetailOpen" list-ref="notificationList">
                <x-slot:controls>
                    <x-newdebugbar::inspector-list-controls :show-search="count($notificationGroups) > 5">
                        <x-slot:leading>
                            <p
                                data-ndb-notification-summary
                                class="ndb:min-w-0 ndb:text-xs ndb:font-semibold ndb:text-zinc-600 ndb:dark:text-zinc-300"
                            >
                                <span data-ndb-notification-summary-count class="ndb:block">
                                    {{ number_format((int) ($notificationSummary['notification_count'] ?? count($notificationGroups))) }} {{ \Illuminate\Support\Str::plural('notification', (int) ($notificationSummary['notification_count'] ?? count($notificationGroups))) }}
                                </span>
                                <span
                                    data-ndb-notification-summary-runtime
                                    class="ndb:mt-0.5 ndb:block ndb:text-[11px] ndb:font-medium ndb:tabular-nums ndb:text-zinc-400"
                                >
                                    {{ \NewDebugBar\Support\DurationFormatter::format($notificationSummary['duration_ms'] ?? 0) }} total
                                </span>
                            </p>
                        </x-slot:leading>

                        <x-slot:search>
                            <x-newdebugbar::search-field
                                label="Search captured notifications"
                                placeholder="Search notification or recipient"
                                data-ndb-notification-search
                                x-model="notificationSearch"
                                @input.debounce.100ms="applyNotificationView()"
                            />
                        </x-slot:search>

                        <x-slot:filter>
                            <x-newdebugbar::select-field
                                label="Filter captured notifications"
                                data-ndb-notification-filter
                                x-model="notificationFilter"
                                @change="setNotificationFilter($event.target.value)"
                            >
                                @foreach ($notificationFilters as $filter => [$label, $count])
                                    <option value="{{ $filter }}">{{ $label }} ({{ $count }})</option>
                                @endforeach
                            </x-newdebugbar::select-field>
                        </x-slot:filter>
                    </x-newdebugbar::inspector-list-controls>
                </x-slot:controls>

                <x-slot:list data-ndb-notification-list>
                    @foreach ($notificationGroups as $notification)
                        <button
                            type="button"
                            data-ndb-notification-item="{{ $notification['execution'] }}"
                            data-ndb-execution="{{ $notification['execution'] }}"
                            data-ndb-status="{{ $notification['status'] }}"
                            data-ndb-search="{{ $notification['search'] }}"
                            @click="selectNotification({{ $notification['execution'] }})"
                            :aria-pressed="notificationSelected === {{ $notification['execution'] }}"
                            :class="notificationSelected === {{ $notification['execution'] }}
                                ? 'ndb:bg-indigo-50/65 ndb:dark:bg-indigo-950/20'
                                : 'ndb:hover:bg-zinc-50/80 ndb:dark:hover:bg-zinc-900/60'"
                            class="ndb:grid ndb:h-auto ndb:w-full ndb:grid-cols-[minmax(0,1fr)_auto] ndb:items-baseline ndb:gap-x-3 ndb:gap-y-1 ndb:px-3 ndb:py-2.5 ndb:text-left ndb:transition-colors ndb:focus-visible:relative ndb:focus-visible:z-10 ndb:focus-visible:outline-2 ndb:focus-visible:outline-indigo-500 ndb:sm:py-3"
                        >
                            <span
                                data-ndb-notification-list-title
                                class="ndb:min-w-0 ndb:truncate ndb:text-xs ndb:font-bold"
                            >{{ $notification['label'] }}</span>
                            <span
                                data-ndb-notification-list-status
                                @class([
                                    'ndb:justify-self-end ndb:text-[11px] ndb:font-bold',
                                    'ndb:text-emerald-600 ndb:dark:text-emerald-300' => $notification['status'] === 'sent',
                                    'ndb:text-amber-600 ndb:dark:text-amber-300' => in_array($notification['status'], ['partial', 'delayed', 'waiting'], true),
                                    'ndb:text-red-600 ndb:dark:text-red-300' => $notification['status'] === 'failed',
                                    'ndb:text-sky-600 ndb:dark:text-sky-300' => $notification['status'] === 'queued',
                                    'ndb:text-indigo-600 ndb:dark:text-indigo-300' => $notification['status'] === 'processing',
                                ])
                            >
                                {{ $notification['status_label'] }}
                            </span>
                            <span
                                data-ndb-notification-list-recipient
                                class="ndb:col-start-1 ndb:min-w-0 ndb:truncate ndb:text-[11px] ndb:text-zinc-500 ndb:dark:text-zinc-400"
                            >To {{ $notification['recipient_label'] }}</span>
                            <span
                                data-ndb-notification-list-activity
                                class="ndb:col-start-2 ndb:justify-self-end ndb:text-right ndb:text-[11px] ndb:font-semibold ndb:tabular-nums ndb:text-zinc-400"
                            >
                                @if ($notification['duration_ms'] > 0 || in_array($notification['status'], ['sent', 'failed', 'partial'], true))
                                    {{ $notification['duration_label'] }}
                                @elseif (($notification['delay_seconds'] ?? null) > 0)
                                    {{ $notification['delay_seconds'] }} s delay
                                @else
                                    Waiting for worker
                                @endif
                            </span>
                        </button>
                    @endforeach
                </x-slot:list>

                <x-slot:empty x-show.important="visibleNotificationCount === 0">
                    <x-newdebugbar::empty-state label="No notifications match these filters." />
                </x-slot:empty>
            </x-newdebugbar::inspector-list-panel>

            <x-newdebugbar::notification-detail />
        </x-newdebugbar::inspector-workspace>
    @else
        <x-newdebugbar::empty-state label="No notifications were sent." />
    @endif
</div>

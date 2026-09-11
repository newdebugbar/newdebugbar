<?php

namespace NewDebugBar\Presentation;

use Illuminate\Support\Str;
use NewDebugBar\Support\DurationFormatter;

/** Prepares retained queue evidence for the inspector and MCP. */
final class QueueActivityPresenter
{
    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    public function present(array $items, string $profileId): array
    {
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
        $formatDuration = DurationFormatter::format(...);

        return collect($items)->values()->map(function (array $item, int $index) use ($profileId, $statusDescriptions, $statusLabels, $formatDuration): array {
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
            $relatedInspector = $isOrigin && $mailChannel && $status === 'sent' ? 'mail' : 'queue';
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
                    'status_label' => Str::headline($status),
                    'profile_id' => is_string($attempt['profile_id'] ?? null) ? $attempt['profile_id'] : null,
                    'exception_class' => is_string($attempt['exception_class'] ?? null) ? $attempt['exception_class'] : null,
                    'recorded_at' => is_string($attempt['recorded_at'] ?? null) ? $attempt['recorded_at'] : null,
                ];
            })->all();
            $exceptionClass = is_string($item['exception_class'] ?? null) ? $item['exception_class'] : null;
            $jobId = is_scalar($item['job_id'] ?? null) && (string) $item['job_id'] !== '' ? (string) $item['job_id'] : null;

            return [
                'execution' => $index + 1,
                'search' => mb_strtolower(implode(' ', array_filter([
                    $job, class_basename($job), $connection, $queue, $jobId, $status,
                    $communicationType, $item['communication_class'] ?? null,
                    ...$channels, ...$notifiableTypes, $exceptionClass,
                    ...array_column($attempts, 'exception_class'),
                ], static fn (mixed $value): bool => is_scalar($value)))),
                'kind' => (string) ($item['kind'] ?? 'queued'),
                'status' => $status,
                'status_group' => $statusGroup,
                'status_label' => $statusLabels[$status] ?? Str::headline($status),
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
                'communication_label' => $communicationType === null ? null : Str::headline($communicationType),
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
                'related_inspector' => $relatedInspector,
                'related_label' => $isOrigin
                    ? ($relatedInspector === 'mail' ? 'Open mail preview' : 'Open worker')
                    : 'Open request',
            ];
        })->all();
    }
}

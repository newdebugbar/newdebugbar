<?php

namespace NewDebugBar\Presentation;

use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use Throwable;

/** Connects stored request and worker profiles through bounded queue facts. */
final class BackgroundActivityPresenter
{
    public const READ_ERROR = 'Background activity could not be refreshed. Showing the last captured details.';

    public function __construct(private readonly BackgroundActivityStore $activities) {}

    /**
     * Adds background_activity with nullable pending and error fields. A read failure
     * leaves captured inspector items intact and pending unknown until a later refresh.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    public function present(array $profile): array
    {
        $profileId = (string) ($profile['id'] ?? '');
        $keys = [];

        foreach (['queue', 'mail', 'notifications'] as $inspector) {
            $items = $profile['inspectors'][$inspector]['payload']['items'] ?? [];

            foreach ($items as $item) {
                if (is_array($item) && is_string($item['correlation_key'] ?? null)) {
                    $keys[] = $item['correlation_key'];
                }
            }
        }

        $runtimeKey = $profile['inspectors']['request']['payload']['context']['correlation_key'] ?? null;

        if (is_string($runtimeKey)) {
            $keys[] = $runtimeKey;
        }

        $error = null;

        try {
            $activities = collect($this->activities->many($keys))->keyBy('key');
        } catch (Throwable) {
            $activities = collect();
            $error = self::READ_ERROR;
        }

        foreach (['queue', 'mail', 'notifications'] as $inspector) {
            if (! isset($profile['inspectors'][$inspector]) || ! is_array($profile['inspectors'][$inspector])) {
                continue;
            }

            $items = $profile['inspectors'][$inspector]['payload']['items'] ?? [];

            if (! is_array($items)) {
                continue;
            }

            $profile['inspectors'][$inspector]['payload']['items'] = array_map(
                function (mixed $item) use ($activities, $profileId): mixed {
                    if (! is_array($item) || ! is_string($item['correlation_key'] ?? null)) {
                        return $item;
                    }

                    $activity = $activities->get($item['correlation_key']);

                    if (! is_array($activity)) {
                        return $item;
                    }

                    $isOrigin = ($activity['origin_profile_id'] ?? null) === $profileId;

                    return [
                        ...$item,
                        'status' => $isOrigin
                            ? ($activity['status'] ?? ($item['status'] ?? null))
                            : ($item['status'] ?? ($activity['status'] ?? null)),
                        'origin_profile_id' => $activity['origin_profile_id'] ?? null,
                        'worker_profile_id' => $activity['worker_profile_id'] ?? null,
                        'activity_attempt' => $activity['attempt'] ?? null,
                        'attempts' => $activity['attempts'] ?? [],
                        'communication_type' => $activity['communication_type'] ?? ($item['communication_type'] ?? null),
                        'communication_class' => $activity['communication_class'] ?? ($item['communication_class'] ?? null),
                        'channels' => $activity['channels'] ?? ($item['channels'] ?? []),
                        'notifiable_types' => $activity['notifiable_types'] ?? ($item['notifiable_types'] ?? []),
                        'notifiable_count' => $activity['notifiable_count'] ?? ($item['notifiable_count'] ?? 0),
                        'is_origin' => $isOrigin,
                    ];
                },
                $items,
            );
        }

        $activityItems = array_values($activities->all());
        $relatedProfileIds = [];

        foreach ($activityItems as $activity) {
            foreach ((array) ($activity['attempts'] ?? []) as $attempt) {
                $relatedProfileIds[] = is_array($attempt) ? ($attempt['profile_id'] ?? null) : null;
            }

            $relatedProfileIds[] = $activity['worker_profile_id'] ?? null;

            if (($activity['origin_profile_id'] ?? null) !== $profileId) {
                $relatedProfileIds[] = $activity['origin_profile_id'] ?? null;
            }
        }

        $relatedProfileIds = array_values(array_unique(array_filter(
            $relatedProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== $profileId && ProfileStore::validId($id),
        )));
        $pending = collect($activityItems)->contains(
            fn (array $activity): bool => in_array($activity['status'] ?? null, ['queued', 'delayed', 'processing', 'waiting'], true),
        );

        $profile['background_activity'] = [
            'count' => count($activityItems),
            'pending' => $error === null ? $pending : null,
            'error' => $error,
            'items' => $activityItems,
            'related_profile_ids' => $relatedProfileIds,
            'origin_profile_id' => $activityItems[0]['origin_profile_id'] ?? null,
        ];

        return $profile;
    }
}

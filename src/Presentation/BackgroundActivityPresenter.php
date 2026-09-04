<?php

namespace NewDebugBar\Presentation;

use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Storage\ProfileStore;
use Throwable;

/**
 * Connects stored request and worker profiles through bounded queue facts.
 * Keeps shared-field masks in correlated copies without changing stored files.
 */
final class BackgroundActivityPresenter
{
    public function __construct(private readonly BackgroundActivityStore $activities) {}

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    public function present(array $profile): array
    {
        $profileId = (string) ($profile['id'] ?? '');
        $keys = [];

        foreach (['queue', 'mail', 'notifications'] as $section) {
            $items = $profile['sections'][$section]['payload']['items'] ?? [];

            foreach ($items as $item) {
                if (is_array($item) && is_string($item['correlation_key'] ?? null)) {
                    $keys[] = $item['correlation_key'];
                }
            }
        }

        $runtimeContext = $profile['sections']['request']['payload']['context'] ?? [];
        $runtimeKey = $runtimeContext['correlation_key'] ?? null;

        if (is_string($runtimeKey)) {
            $keys[] = $runtimeKey;
        }

        try {
            $activities = collect($this->activities->many($keys))->keyBy('key');
        } catch (Throwable) {
            $activities = collect();
        }

        $originKeys = $activities->filter(
            fn (array $activity): bool => ($activity['origin_profile_id'] ?? null) === $profileId,
        )->keys()->all();

        // File-level rules may select only one profile path. Carry its masks into
        // the shared facts before any section or root activity copy is presented.
        if (is_array($runtimeContext) && is_string($runtimeKey) && $activities->has($runtimeKey)) {
            $activities->put($runtimeKey, $this->preserveRedactions($activities->get($runtimeKey), $runtimeContext));
        }

        foreach (['queue', 'mail', 'notifications'] as $section) {
            foreach ((array) ($profile['sections'][$section]['payload']['items'] ?? []) as $item) {
                $key = is_array($item) ? ($item['correlation_key'] ?? null) : null;
                $activity = is_string($key) ? $activities->get($key) : null;

                if (! is_array($activity)) {
                    continue;
                }

                $activity = $this->preserveRedactions($activity, $item);

                if (array_key_exists('activity_attempt', $item)) {
                    $activity['attempt'] = $this->preserveRedactions($activity['attempt'] ?? null, $item['activity_attempt']);
                }

                $activities->put($key, $activity);
            }
        }

        if (is_array($runtimeContext) && is_string($runtimeKey) && $activities->has($runtimeKey)) {
            $profile['sections']['request']['payload']['context'] = $this->preserveRedactions($runtimeContext, $activities->get($runtimeKey));
        }

        foreach (['queue', 'mail', 'notifications'] as $section) {
            if (! isset($profile['sections'][$section]) || ! is_array($profile['sections'][$section])) {
                continue;
            }

            $items = $profile['sections'][$section]['payload']['items'] ?? [];

            if (! is_array($items)) {
                continue;
            }

            $profile['sections'][$section]['payload']['items'] = array_map(
                function (mixed $item) use ($activities, $originKeys): mixed {
                    if (! is_array($item) || ! is_string($item['correlation_key'] ?? null)) {
                        return $item;
                    }

                    $activity = $activities->get($item['correlation_key']);

                    if (! is_array($activity)) {
                        return $item;
                    }

                    $isOrigin = in_array($item['correlation_key'], $originKeys, true);
                    $item = $this->preserveRedactions($item, $activity);

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
            'pending' => $pending,
            'items' => $activityItems,
            'related_profile_ids' => $relatedProfileIds,
            'origin_profile_id' => $activityItems[0]['origin_profile_id'] ?? null,
        ];

        return $profile;
    }

    private function preserveRedactions(mixed $value, mixed $other): mixed
    {
        if ($value === '[redacted]' || $other === '[redacted]') {
            return '[redacted]';
        }

        if (is_array($value) && is_array($other)) {
            foreach (array_intersect_key($value, $other) as $key => $child) {
                $value[$key] = $this->preserveRedactions($child, $other[$key]);
            }
        }

        return $value;
    }
}

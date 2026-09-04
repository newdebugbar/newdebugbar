<?php

namespace NewDebugBar\Storage;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use NewDebugBar\Support\Redactor;
use RuntimeException;
use Throwable;

/** Stores bounded queue correlation state beside the full debug profiles. */
final class BackgroundActivityStore
{
    private const KEY_PATTERN = '/\A[0-9a-f]{64}\z/';

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
        private readonly int $maxActivities = 100,
        private readonly int $maxAgeMinutes = 60,
        private readonly ?Redactor $redactor = null,
    ) {}

    public function key(string $connection, ?string $queue, mixed $jobId): ?string
    {
        if ((! is_string($jobId) && ! is_int($jobId)) || (string) $jobId === '') {
            return null;
        }

        return hash('sha256', implode("\0", [
            $connection,
            $queue ?: 'default',
            (string) $jobId,
        ]));
    }

    /** @param array<string, mixed> $facts @return array<string, mixed>|null */
    public function recordDispatch(array $facts): ?array
    {
        $key = $this->key(
            (string) ($facts['connection'] ?? ''),
            is_string($facts['queue'] ?? null) ? $facts['queue'] : null,
            $facts['job_id'] ?? null,
        );

        if ($key === null || ! ProfileStore::validId((string) ($facts['origin_profile_id'] ?? ''))) {
            return null;
        }

        $delay = is_numeric($facts['delay_seconds'] ?? null)
            ? max(0, (int) $facts['delay_seconds'])
            : null;
        $existing = $this->get($key) ?? [];
        $activity = [
            ...$existing,
            'key' => $key,
            'origin_profile_id' => (string) $facts['origin_profile_id'],
            'job_id' => (string) $facts['job_id'],
            'job' => (string) ($facts['job'] ?? 'Job'),
            'connection' => (string) ($facts['connection'] ?? ''),
            'queue' => (string) (($facts['queue'] ?? null) ?: 'default'),
            'delay_seconds' => $delay,
            'communication_type' => $facts['communication_type'] ?? null,
            'communication_class' => $facts['communication_class'] ?? null,
            'channels' => array_values(array_filter((array) ($facts['channels'] ?? []), 'is_string')),
            'notifiable_types' => array_values(array_filter((array) ($facts['notifiable_types'] ?? []), 'is_string')),
            'notifiable_count' => max(0, (int) ($facts['notifiable_count'] ?? 0)),
            'recipient_count' => max(0, (int) ($facts['recipient_count'] ?? 0)),
            'status' => $delay !== null && $delay > 0 ? 'delayed' : 'queued',
            'attempt' => null,
            'worker_profile_id' => null,
            'attempts' => is_array($existing['attempts'] ?? null) ? $existing['attempts'] : [],
            'queued_at' => $existing['queued_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->put($activity);

        return $activity;
    }

    /** @return array<string, mixed>|null */
    public function markProcessing(string $connection, ?string $queue, mixed $jobId, ?int $attempt): ?array
    {
        $key = $this->key($connection, $queue, $jobId);
        $activity = $key === null ? null : $this->get($key);

        if ($activity === null) {
            return null;
        }

        $activity['status'] = 'processing';
        $activity['attempt'] = $attempt;
        $activity['updated_at'] = now()->toIso8601String();
        $this->put($activity);

        return $activity;
    }

    /** @return array<string, mixed>|null */
    public function recordOutcome(
        ?string $key,
        string $status,
        ?string $workerProfileId,
        ?int $attempt,
        ?string $exceptionClass = null,
    ): ?array {
        $activity = $key === null ? null : $this->get($key);

        if ($activity === null || ! in_array($status, ['completed', 'failed', 'sent', 'waiting'], true)) {
            return null;
        }

        $attemptStatus = $status === 'waiting' ? 'failed' : $status;
        $attempts = is_array($activity['attempts'] ?? null) ? $activity['attempts'] : [];
        $attempts[] = array_filter([
            'attempt' => $attempt,
            'status' => $attemptStatus,
            'profile_id' => ProfileStore::validId((string) $workerProfileId) ? $workerProfileId : null,
            'exception_class' => $exceptionClass,
            'recorded_at' => now()->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null);

        $activity['status'] = $status;
        $activity['attempt'] = $attempt;
        $activity['worker_profile_id'] = ProfileStore::validId((string) $workerProfileId)
            ? $workerProfileId
            : ($activity['worker_profile_id'] ?? null);
        $activity['attempts'] = array_slice($attempts, -self::MAX_ATTEMPTS);
        $activity['exception_class'] = $exceptionClass;
        $activity['updated_at'] = now()->toIso8601String();
        $this->put($activity);

        return $activity;
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return null;
        }

        $filename = $this->filename($key);

        if (! $this->files->isFile($filename)) {
            return null;
        }

        if ($this->files->lastModified($filename) < now()->subMinutes($this->maxAgeMinutes)->getTimestamp()) {
            $this->files->delete($filename);

            return null;
        }

        try {
            $activity = json_decode($this->files->get($filename), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($activity) ? $activity : null;
    }

    /** @param list<string> $keys @return list<array<string, mixed>> */
    public function many(array $keys): array
    {
        $activities = [];

        foreach (array_slice(array_values(array_unique($keys)), 0, $this->maxActivities) as $key) {
            $activity = $this->get($key);

            if ($activity !== null) {
                $activities[] = $activity;
            }
        }

        return $activities;
    }

    /** @param array<string, mixed> $activity */
    private function put(array $activity): void
    {
        $key = (string) ($activity['key'] ?? '');

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            return;
        }

        $this->files->ensureDirectoryExists($this->path, 0700);

        try {
            $activity = ($this->redactor ?? new Redactor)->clean($activity, key: 'background_activity');
            $json = json_encode($activity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The background activity could not be encoded.', previous: $exception);
        }

        $destination = $this->filename($key);
        $temporary = $destination.'.'.bin2hex(random_bytes(6)).'.tmp';

        if ($this->files->put($temporary, $json, true) === false) {
            throw new RuntimeException('The background activity could not be written.');
        }

        @chmod($temporary, 0600);

        if (! @rename($temporary, $destination)) {
            $this->files->delete($temporary);

            throw new RuntimeException('The background activity could not be stored atomically.');
        }

        $this->prune();
    }

    private function prune(): void
    {
        if (! $this->files->isDirectory($this->path)) {
            return;
        }

        $files = collect($this->files->files($this->path))
            ->filter(fn ($file): bool => $file->getExtension() === 'json')
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->values();
        $expiresAt = now()->subMinutes($this->maxAgeMinutes)->getTimestamp();

        $files->each(function ($file, int $index) use ($expiresAt): void {
            if ($index >= $this->maxActivities || $file->getMTime() < $expiresAt) {
                try {
                    $this->files->delete($file->getPathname());
                } catch (Throwable) {
                    // Correlation cleanup must never affect the host application.
                }
            }
        });
    }

    private function filename(string $key): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$key.'.json';
    }
}

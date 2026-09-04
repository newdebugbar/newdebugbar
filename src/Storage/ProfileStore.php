<?php

namespace NewDebugBar\Storage;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use JsonException;
use NewDebugBar\Support\ProfileSanitizer;
use NewDebugBar\Support\ProfileSizeLimiter;
use NewDebugBar\Support\Redactor;
use RuntimeException;
use Throwable;

/** Stores short-lived request profiles as private atomic JSON files outside Git. */
final class ProfileStore
{
    public const ID_PATTERN = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';

    public const ID_REGEX = '/\A'.self::ID_PATTERN.'\z/';

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
        private readonly int $maxProfiles = 20,
        private readonly int $maxAgeMinutes = 60,
        private readonly ?ProfileSanitizer $sanitizer = null,
    ) {}

    /** @param array<string, mixed> $profile */
    public function put(array $profile): string
    {
        $id = (string) ($profile['id'] ?? '');
        $this->assertValidId($id);
        $this->ensureStorageDirectory();

        try {
            $profile = ($this->sanitizer ?? new ProfileSanitizer(new Redactor))->clean($profile);
            $json = (new ProfileSizeLimiter)->encode($profile);
        } catch (JsonException $exception) {
            throw new RuntimeException('The debug profile could not be encoded.', previous: $exception);
        }

        $destination = $this->filename($id);
        $temporary = $destination.'.'.bin2hex(random_bytes(6)).'.tmp';

        if ($this->files->put($temporary, $json, true) === false) {
            throw new RuntimeException('The debug profile could not be written.');
        }

        @chmod($temporary, 0600);

        if (! @rename($temporary, $destination)) {
            $this->files->delete($temporary);

            throw new RuntimeException('The debug profile could not be stored atomically.');
        }

        $this->prune();

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $this->assertValidId($id);
        $filename = $this->filename($id);

        if (! $this->files->isFile($filename)) {
            return null;
        }

        $expiresAt = now()->subMinutes($this->maxAgeMinutes)->getTimestamp();

        if ($this->files->lastModified($filename) < $expiresAt) {
            $this->files->delete($filename);

            return null;
        }

        try {
            $profile = json_decode($this->files->get($filename), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The debug profile could not be decoded.', previous: $exception);
        }

        if (! is_array($profile)) {
            throw new RuntimeException('The debug profile did not contain a JSON object.');
        }

        return $profile;
    }

    /** @return list<array<string, mixed>> */
    public function recent(?int $limit = null): array
    {
        if (! $this->files->isDirectory($this->path)) {
            return [];
        }

        $limit = max(1, min($limit ?? $this->maxProfiles, $this->maxProfiles));
        $profiles = [];

        foreach (collect($this->files->files($this->path))->sortByDesc->getMTime() as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            try {
                $profile = $this->get($file->getBasename('.json'));
            } catch (Throwable) {
                $profile = null;
            }

            if ($profile !== null) {
                $profiles[] = $profile;
            }

            if (count($profiles) >= $limit) {
                break;
            }
        }

        return $profiles;
    }

    public function maxProfiles(): int
    {
        return $this->maxProfiles;
    }

    public static function validId(string $id): bool
    {
        return preg_match(self::ID_REGEX, $id) === 1;
    }

    private function prune(): void
    {
        $files = collect($this->files->files($this->path))
            ->filter(fn ($file): bool => $file->getExtension() === 'json')
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->values();

        $expiresAt = now()->subMinutes($this->maxAgeMinutes)->getTimestamp();

        $files->each(function ($file, int $index) use ($expiresAt): void {
            if ($index >= $this->maxProfiles || $file->getMTime() < $expiresAt) {
                $this->files->delete($file->getPathname());
            }
        });
    }

    private function filename(string $id): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$id.'.json';
    }

    private function ensureStorageDirectory(): void
    {
        $this->files->ensureDirectoryExists($this->path, 0700);

        $ignoreFile = rtrim($this->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'.gitignore';

        if ($this->files->exists($ignoreFile)) {
            return;
        }

        // The broad rule also ignores this generated file, so the package never dirties the host repository.
        if ($this->files->put($ignoreFile, "*\n", true) === false) {
            throw new RuntimeException('The debug profile directory could not be prepared.');
        }

        @chmod($ignoreFile, 0600);
    }

    private function assertValidId(string $id): void
    {
        if (! self::validId($id)) {
            throw new InvalidArgumentException('Invalid debug profile ID.');
        }
    }
}

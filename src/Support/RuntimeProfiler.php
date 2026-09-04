<?php

namespace NewDebugBar\Support;

use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\ProfileStore;
use Throwable;

/** Owns non-HTTP profile lifecycles without disturbing nested request profiles. */
final class RuntimeProfiler
{
    private bool $ownsProfile = false;

    private ?string $ownerKey = null;

    public function __construct(
        private readonly ProfileManager $manager,
        private readonly ProfileStore $store,
    ) {}

    /** @param array<string, mixed> $context */
    public function start(string $type, string $name, array $context = [], ?string $ownerKey = null): bool
    {
        if (! app(ProfileAccess::class)->enabled() || $this->ownsProfile || $this->manager->isCollecting()) {
            return false;
        }

        $this->manager->beginRuntime($type, $name, $context);
        $this->ownsProfile = true;
        $this->ownerKey = $ownerKey;

        return true;
    }

    public function finish(int $exitCode = 0, ?string $ownerKey = null): ?string
    {
        if (! $this->ownsProfile || $this->ownerKey !== $ownerKey) {
            return null;
        }

        try {
            if (! app(ProfileAccess::class)->enabled()) {
                $this->manager->discard();

                return null;
            }

            return $this->store->put($this->manager->finishRuntime($exitCode));
        } catch (Throwable) {
            $this->manager->discard();

            return null;
        } finally {
            $this->ownsProfile = false;
            $this->ownerKey = null;
        }
    }

    public function fail(Throwable $exception, ?string $ownerKey = null): ?string
    {
        if (! $this->ownsProfile || $this->ownerKey !== $ownerKey) {
            return null;
        }

        $this->manager->recordException($exception);

        return $this->finish(1, $ownerKey);
    }
}

<?php

namespace NewDebugBar\Testing;

use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\DurationFormatter;
use PHPUnit\Framework\Assert;

/** Fluent test assertions backed by the production profile analyzers. */
final class ProfileAssertions
{
    /** @param array<string, mixed> $profile */
    private function __construct(private readonly array $profile) {}

    /** @param array<string, mixed> $profile */
    public static function for(array $profile): self
    {
        return new self(app(ProfilePresenter::class)->present($profile));
    }

    public static function stored(string $profileId): self
    {
        $profile = app(ProfileStore::class)->get($profileId);
        Assert::assertNotNull($profile, "Debug profile [{$profileId}] was not found.");

        return self::for($profile);
    }

    public function assertNoRepeatedQueries(): self
    {
        Assert::assertSame(
            0,
            (int) ($this->profile['inspectors']['queries']['summary']['repeated_pattern_count'] ?? 0),
            'The profile contains repeated query patterns.',
        );

        return $this;
    }

    public function assertNoLikelyNPlusOneQueries(): self
    {
        Assert::assertNotContains(
            'query.n_plus_one',
            array_column($this->profile['findings'] ?? [], 'rule_id'),
            'The profile contains a likely N+1 query finding.',
        );

        return $this;
    }

    public function assertQueryCountAtMost(int $maximum): self
    {
        Assert::assertLessThanOrEqual(
            $maximum,
            (int) ($this->profile['inspectors']['queries']['summary']['total_count'] ?? 0),
            "The query count exceeded {$maximum}.",
        );

        return $this;
    }

    public function assertQueryTimeAtMost(float $maximumMs): self
    {
        Assert::assertLessThanOrEqual(
            $maximumMs,
            (float) ($this->profile['inspectors']['queries']['summary']['total_time_ms'] ?? 0),
            'The total query time exceeded '.DurationFormatter::format($maximumMs).'.',
        );

        return $this;
    }

    public function assertDurationAtMost(float $maximumMs): self
    {
        Assert::assertLessThanOrEqual(
            $maximumMs,
            (float) ($this->profile['metrics']['duration_ms'] ?? 0),
            'The request duration exceeded '.DurationFormatter::format($maximumMs).'.',
        );

        return $this;
    }

    public function assertPeakMemoryAtMost(float $maximumMb): self
    {
        Assert::assertLessThanOrEqual(
            $maximumMb,
            (float) ($this->profile['metrics']['peak_memory_mb'] ?? 0),
            "The peak memory exceeded {$maximumMb} MB.",
        );

        return $this;
    }

    public function assertNoErrors(): self
    {
        Assert::assertNotContains(
            'request.error',
            array_column($this->profile['findings'] ?? [], 'rule_id'),
            'The profile contains an error response or exception finding.',
        );

        return $this;
    }
}

<?php

namespace NewDebugBar\Presentation;

use NewDebugBar\Analysis\CacheAnalyzer;
use NewDebugBar\Analysis\HttpClientAnalyzer;
use NewDebugBar\Analysis\LogAnalyzer;
use NewDebugBar\Analysis\ProfileAnalyzer;
use NewDebugBar\Analysis\QueryAnalyzer;
use NewDebugBar\Analysis\SectionAnalyzer;
use NewDebugBar\Analysis\TimelineBuilder;

/** Enriches a stored profile for human-facing and machine-facing views. */
final class ProfilePresenter
{
    public function __construct(
        private readonly QueryAnalyzer $queries,
        private readonly CacheAnalyzer $cache,
        private readonly HttpClientAnalyzer $httpClient,
        private readonly LogAnalyzer $logs,
        private readonly ProfileAnalyzer $profiles,
        private readonly SectionAnalyzer $sections,
        private readonly TimelineBuilder $timeline,
        private readonly BackgroundActivityPresenter $background,
        private readonly QueueActivityPresenter $queue,
        private readonly RedisCommandPresenter $redis,
        private readonly LivewireActivityPresenter $livewire,
        private readonly ProfileSummaryPresenter $summaries,
    ) {}

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    public function present(array $profile): array
    {
        if ($profile === []) {
            return [];
        }

        $profile = $this->background->present($profile);

        $queryItems = $profile['sections']['queries']['payload']['items'] ?? [];
        $queryAnalysis = $this->queries->analyze(
            is_array($queryItems) ? $queryItems : [],
            (float) ($profile['metrics']['duration_ms'] ?? 0),
        );

        if (isset($profile['sections']['queries'])) {
            $collectorSummary = $profile['sections']['queries']['summary'] ?? [];
            $profile['sections']['queries']['summary'] = [
                ...$collectorSummary,
                ...$queryAnalysis['summary'],
                'count' => $collectorSummary['count'] ?? count($queryAnalysis['items']),
                'total_count' => $collectorSummary['count'] ?? count($queryAnalysis['items']),
                'retained_count' => $collectorSummary['retained_count'] ?? count($queryAnalysis['items']),
                'dropped_count' => $collectorSummary['dropped_count'] ?? 0,
                'duration_ms' => $collectorSummary['duration_ms'] ?? $queryAnalysis['summary']['total_time_ms'],
                'total_time_ms' => $collectorSummary['duration_ms'] ?? $queryAnalysis['summary']['total_time_ms'],
            ];
            $profile['sections']['queries']['payload']['items'] = $queryAnalysis['items'];
            $profile['sections']['queries']['payload']['repeated_groups'] = $queryAnalysis['repeated_groups'];
        }

        if (isset($profile['sections']['http_client'])) {
            $httpItems = $profile['sections']['http_client']['payload']['items'] ?? [];
            $httpAnalysis = $this->httpClient->analyze(is_array($httpItems) ? $httpItems : []);
            $collectorSummary = $profile['sections']['http_client']['summary'] ?? [];
            $profile['sections']['http_client']['summary'] = [
                ...$collectorSummary,
                ...$httpAnalysis['summary'],
                'count' => $collectorSummary['count'] ?? count($httpAnalysis['items']),
                'retained_count' => $collectorSummary['retained_count'] ?? count($httpAnalysis['items']),
                'failed_count' => $collectorSummary['failed_count'] ?? $httpAnalysis['summary']['failed_count'],
                'duration_ms' => $collectorSummary['duration_ms'] ?? 0,
            ];
            $profile['sections']['http_client']['payload']['items'] = $httpAnalysis['items'];
        }

        if (isset($profile['sections']['cache'])) {
            $cacheItems = $profile['sections']['cache']['payload']['items'] ?? [];
            $cacheAnalysis = $this->cache->analyze(is_array($cacheItems) ? $cacheItems : []);
            $collectorSummary = $profile['sections']['cache']['summary'] ?? [];
            $profile['sections']['cache']['summary'] = [
                ...$collectorSummary,
                ...$cacheAnalysis['summary'],
                'count' => $collectorSummary['count'] ?? count($cacheAnalysis['items']),
                'retained_count' => $collectorSummary['retained_count'] ?? count($cacheAnalysis['items']),
                'dropped_count' => $collectorSummary['dropped_count'] ?? 0,
            ];
            $profile['sections']['cache']['payload']['items'] = $cacheAnalysis['items'];
            $profile['sections']['cache']['payload']['repeated_misses'] = $cacheAnalysis['repeated_misses'];
        }

        if (isset($profile['sections']['logs'])) {
            $logItems = $profile['sections']['logs']['payload']['items'] ?? [];
            $logAnalysis = $this->logs->analyze(is_array($logItems) ? $logItems : []);
            $profile['sections']['logs']['summary'] = [
                ...($profile['sections']['logs']['summary'] ?? []),
                ...$logAnalysis['summary'],
            ];
            $profile['sections']['logs']['payload']['items'] = $logAnalysis['items'];
            $profile['sections']['logs']['payload']['groups'] = $logAnalysis['groups'];
        }

        $profile = $this->sections->analyze($profile);

        if (isset($profile['sections']['queue'])) {
            $profile['sections']['queue']['payload']['records'] = $this->queue->present(
                (array) ($profile['sections']['queue']['payload']['items'] ?? []),
                (string) ($profile['id'] ?? ''),
            );
        }

        if (isset($profile['sections']['redis'])) {
            $profile['sections']['redis']['payload']['records'] = $this->redis->present(
                (array) ($profile['sections']['redis']['payload']['items'] ?? []),
            );
        }

        if (isset($profile['sections']['livewire'])) {
            $profile['sections']['livewire']['payload']['activity_records'] = $this->livewire->present(
                (array) ($profile['sections']['livewire']['payload']['activity'] ?? []),
                $this->summaries->present($profile),
            );
        }

        if (isset($profile['sections']['request'])) {
            $timeline = $this->timeline->build($profile);
            $omittedSources = $this->timeline->omittedSources($profile);
            $ordered = [];

            foreach ($profile['sections'] as $key => $section) {
                $ordered[$key] = $section;

                if ($key === 'request') {
                    $ordered['timeline'] = [
                        'label' => 'Timeline',
                        'summary' => ['count' => count($timeline)],
                        'payload' => [
                            'items' => $timeline,
                            'incomplete' => $omittedSources !== [],
                            'omitted_count' => array_sum($omittedSources),
                            'omitted_sources' => $omittedSources,
                        ],
                    ];
                }
            }

            $profile['sections'] = $ordered;
        }

        $profile['findings'] = $this->profiles->analyze($profile);

        return $profile;
    }
}

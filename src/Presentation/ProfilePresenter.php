<?php

namespace NewDebugBar\Presentation;

use NewDebugBar\Analysis\CacheAnalyzer;
use NewDebugBar\Analysis\HttpClientAnalyzer;
use NewDebugBar\Analysis\InspectorAnalyzer;
use NewDebugBar\Analysis\LogAnalyzer;
use NewDebugBar\Analysis\ProfileAnalyzer;
use NewDebugBar\Analysis\QueryAnalyzer;
use NewDebugBar\Analysis\TimelineBuilder;

/** Enriches a stored profile for human-facing and machine-facing views. */
final class ProfilePresenter
{
    public function __construct(
        private readonly QueryAnalyzer $queries,
        private readonly QueryRecordPresenter $queryRecords,
        private readonly CacheAnalyzer $cache,
        private readonly HttpClientAnalyzer $httpClient,
        private readonly LogAnalyzer $logs,
        private readonly ProfileAnalyzer $profiles,
        private readonly InspectorAnalyzer $inspectors,
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

        $queryItems = $profile['inspectors']['queries']['payload']['items'] ?? [];
        $queryAnalysis = $this->queries->analyze(
            is_array($queryItems) ? $queryItems : [],
            (float) ($profile['metrics']['duration_ms'] ?? 0),
        );

        if (isset($profile['inspectors']['queries'])) {
            $collectorSummary = $profile['inspectors']['queries']['summary'] ?? [];
            $profile['inspectors']['queries']['summary'] = [
                ...$collectorSummary,
                ...$queryAnalysis['summary'],
                'count' => $collectorSummary['count'] ?? count($queryAnalysis['items']),
                'total_count' => $collectorSummary['count'] ?? count($queryAnalysis['items']),
                'retained_count' => $collectorSummary['retained_count'] ?? count($queryAnalysis['items']),
                'dropped_count' => $collectorSummary['dropped_count'] ?? 0,
                'duration_ms' => $collectorSummary['duration_ms'] ?? $queryAnalysis['summary']['total_time_ms'],
                'total_time_ms' => $collectorSummary['duration_ms'] ?? $queryAnalysis['summary']['total_time_ms'],
            ];
            $profile['inspectors']['queries']['payload']['items'] = $queryAnalysis['items'];
            $profile['inspectors']['queries']['payload']['repeated_groups'] = $queryAnalysis['repeated_groups'];
            $profile['inspectors']['queries']['payload']['records'] = $this->queryRecords->present(
                $profile['inspectors']['queries']['payload'],
            );
        }

        if (isset($profile['inspectors']['http_client'])) {
            $httpItems = $profile['inspectors']['http_client']['payload']['items'] ?? [];
            $httpAnalysis = $this->httpClient->analyze(is_array($httpItems) ? $httpItems : []);
            $collectorSummary = $profile['inspectors']['http_client']['summary'] ?? [];
            $profile['inspectors']['http_client']['summary'] = [
                ...$collectorSummary,
                ...$httpAnalysis['summary'],
                'count' => $collectorSummary['count'] ?? count($httpAnalysis['items']),
                'retained_count' => $collectorSummary['retained_count'] ?? count($httpAnalysis['items']),
                'failed_count' => $collectorSummary['failed_count'] ?? $httpAnalysis['summary']['failed_count'],
                'duration_ms' => $collectorSummary['duration_ms'] ?? 0,
            ];
            $profile['inspectors']['http_client']['payload']['items'] = $httpAnalysis['items'];
        }

        if (isset($profile['inspectors']['cache'])) {
            $cacheItems = $profile['inspectors']['cache']['payload']['items'] ?? [];
            $cacheAnalysis = $this->cache->analyze(is_array($cacheItems) ? $cacheItems : []);
            $collectorSummary = $profile['inspectors']['cache']['summary'] ?? [];
            $profile['inspectors']['cache']['summary'] = [
                ...$collectorSummary,
                ...$cacheAnalysis['summary'],
                'count' => $collectorSummary['count'] ?? count($cacheAnalysis['items']),
                'retained_count' => $collectorSummary['retained_count'] ?? count($cacheAnalysis['items']),
                'dropped_count' => $collectorSummary['dropped_count'] ?? 0,
            ];
            $profile['inspectors']['cache']['payload']['items'] = $cacheAnalysis['items'];
            $profile['inspectors']['cache']['payload']['repeated_misses'] = $cacheAnalysis['repeated_misses'];
        }

        if (isset($profile['inspectors']['logs'])) {
            $logItems = $profile['inspectors']['logs']['payload']['items'] ?? [];
            $logAnalysis = $this->logs->analyze(is_array($logItems) ? $logItems : []);
            $profile['inspectors']['logs']['summary'] = [
                ...($profile['inspectors']['logs']['summary'] ?? []),
                ...$logAnalysis['summary'],
            ];
            $profile['inspectors']['logs']['payload']['items'] = $logAnalysis['items'];
            $profile['inspectors']['logs']['payload']['groups'] = $logAnalysis['groups'];
        }

        $profile = $this->inspectors->analyze($profile);

        if (isset($profile['inspectors']['queue'])) {
            $profile['inspectors']['queue']['payload']['records'] = $this->queue->present(
                (array) ($profile['inspectors']['queue']['payload']['items'] ?? []),
                (string) ($profile['id'] ?? ''),
            );
        }

        if (isset($profile['inspectors']['redis'])) {
            $profile['inspectors']['redis']['payload']['records'] = $this->redis->present(
                (array) ($profile['inspectors']['redis']['payload']['items'] ?? []),
            );
        }

        if (isset($profile['inspectors']['livewire'])) {
            $profile['inspectors']['livewire']['payload']['activity_records'] = $this->livewire->present(
                (array) ($profile['inspectors']['livewire']['payload']['activity'] ?? []),
                $this->summaries->present($profile),
            );
        }

        if (isset($profile['inspectors']['request'])) {
            $timeline = $this->timeline->build($profile);
            $omittedSources = $this->timeline->omittedSources($profile);
            $ordered = [];

            foreach ($profile['inspectors'] as $key => $inspector) {
                $ordered[$key] = $inspector;

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

            $profile['inspectors'] = $ordered;
        }

        $profile['findings'] = $this->profiles->analyze($profile, $queryAnalysis);

        return $profile;
    }
}

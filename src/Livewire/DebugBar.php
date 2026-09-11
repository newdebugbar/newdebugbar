<?php

namespace NewDebugBar\Livewire;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Presentation\ProfileSummaryPresenter;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\QueryExplainer;

/** Loads a request summary first and renders one inspector at a time. */
final class DebugBar extends Component
{
    private const DEFAULT_INSPECTOR = 'request';

    private const TIMELINE_PAGE_SIZE = 50;

    private const TIMELINE_KEY_INSPECTORS = ['request', 'queries', 'http_client', 'exceptions', 'authorization', 'validation', 'queue'];

    /** @var array<string, string> */
    private const INSPECTOR_DESCRIPTIONS = [
        'authorization' => 'See what Laravel allowed or denied, for which user and arguments, then inspect the policy or Gate and source.',
        'cache' => 'Review cache reads, writes, deletes, stores, results, and timing.',
        'events' => 'See which events Laravel dispatched, where they came from, and how they were handled.',
        'exceptions' => 'Inspect reported exceptions, application frames, and the code path that failed.',
        'http_client' => 'Review outbound HTTP requests, responses, timing, and their source.',
        'logs' => 'Review log messages, their context, and the application code that wrote them.',
        'livewire' => 'Inspect Livewire activity and mounted components.',
        'mail' => 'Inspect mail created during the request, including recipients, metadata, and previews.',
        'models' => 'Review Eloquent retrievals, writes, repeated records, and application sources.',
        'notifications' => 'Inspect notification recipients, channel deliveries, failures, payloads, and source code.',
        'queries' => 'Find repeated work, slow SQL, and the application code that triggered it.',
        'queue' => 'Review queued work, its connection and queue, and what happened during dispatch.',
        'redis' => 'Inspect direct Redis commands, their keys, connections, and timing.',
        'request' => 'Inspect the selected request and switch between later requests captured on this page.',
        'timeline' => 'Follow important work in the order it happened across the request.',
        'validation' => 'Review failed fields, messages, rules, and where validation came from.',
        'views' => 'See which Blade templates rendered and the data each received. Use this to spot missing variables, unexpected partials, and repeated renders.',
    ];

    #[Locked]
    public string $profileId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $summary = [];

    #[Locked]
    public bool $inspectorLoaded = false;

    #[Locked]
    public string $selectedInspector = self::DEFAULT_INSPECTOR;

    #[Locked]
    public int $timelineLimit = self::TIMELINE_PAGE_SIZE;

    #[Locked]
    public string $timelineFilter = 'key';

    #[Locked]
    public string $timelineSearch = '';

    #[Locked]
    public int $profileLimit = 20;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $queryExplains = [];

    /** @var array<int, string> */
    #[Locked]
    public array $queryExplainErrors = [];

    public function mount(
        string $profileId,
        ProfileStore $store,
        ProfilePresenter $presenter,
        ProfileSummaryPresenter $summaries,
    ): void {
        $this->profileId = $profileId;
        $this->profileLimit = $store->maxProfiles();
        $profile = $presenter->present($store->get($profileId) ?? []);
        $this->summary = $this->makeSummary($profile, $summaries);
    }

    public function loadInspector(
        string $inspector,
        ProfileStore $store,
        ProfilePresenter $presenter,
    ): void {
        $stored = $store->get($this->profileId);
        abort_if($stored === null, 404);

        $profile = $presenter->present($stored);
        abort_unless(
            $inspector !== 'overview' && array_key_exists($inspector, (array) ($profile['inspectors'] ?? [])),
            422,
        );

        if ($this->selectedInspector !== $inspector) {
            $this->timelineLimit = self::TIMELINE_PAGE_SIZE;
            $this->timelineFilter = 'key';
            $this->timelineSearch = '';
        }

        $this->selectedInspector = $inspector;
        $this->inspectorLoaded = true;
        $this->dispatch('newdebugbar-inspector-loaded', inspector: $inspector, profileId: $this->profileId);
        $this->dispatch('newdebugbar-content-updated');
    }

    public function loadMoreTimeline(
        ProfileStore $store,
        ProfilePresenter $presenter,
    ): void {
        abort_unless($this->inspectorLoaded && $this->selectedInspector === 'timeline', 422);
        $stored = $store->get($this->profileId);
        abort_if($stored === null, 404);

        $profile = $presenter->present($stored);
        $items = $this->filteredTimelineItems((array) ($profile['inspectors']['timeline']['payload']['items'] ?? []));
        $this->timelineLimit = min(count($items), $this->timelineLimit + self::TIMELINE_PAGE_SIZE);
        $this->dispatch('newdebugbar-inspector-loaded', inspector: 'timeline', profileId: $this->profileId);
        $this->dispatch('newdebugbar-content-updated');
    }

    public function filterTimeline(string $filter, string $search): void
    {
        abort_unless($this->inspectorLoaded && $this->selectedInspector === 'timeline', 422);
        abort_unless(in_array($filter, ['all', 'key', ...array_keys(self::INSPECTOR_DESCRIPTIONS)], true), 422);
        abort_if(mb_strlen($search) > 500, 422);

        $this->timelineFilter = $filter;
        $this->timelineSearch = $search;
        $this->timelineLimit = self::TIMELINE_PAGE_SIZE;
        $this->dispatch('newdebugbar-content-updated');
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function loadViewData(
        int $renderOrder,
        ProfileStore $store,
        ProfilePresenter $presenter,
    ): array {
        abort_unless($this->inspectorLoaded && $this->selectedInspector === 'views' && $renderOrder > 0, 422);
        $profile = $presenter->present($store->get($this->profileId) ?? []);

        foreach ((array) ($profile['inspectors']['views']['payload']['groups'] ?? []) as $group) {
            foreach ((array) ($group['items'] ?? []) as $view) {
                if ((int) ($view['render_order'] ?? 0) === $renderOrder) {
                    return is_array($view['data'] ?? null) ? $view['data'] : [];
                }
            }
        }

        abort(404);
    }

    #[Renderless]
    public function refreshRelatedActivity(
        ProfileStore $store,
        ProfilePresenter $presenter,
        ProfileSummaryPresenter $summaries,
    ): void {
        $stored = $store->get($this->profileId);
        abort_if($stored === null, 404);

        $profile = $presenter->present($stored);
        $this->summary = $this->makeSummary($profile, $summaries);
        $relatedProfiles = [];

        foreach (array_slice((array) ($this->summary['related_profile_ids'] ?? []), 0, $this->profileLimit) as $profileId) {
            if (! is_string($profileId) || ! $this->validProfileId($profileId)) {
                continue;
            }

            $related = $store->get($profileId);

            if ($related !== null) {
                $relatedProfiles[] = $summaries->present($presenter->present($related));
            }
        }

        $this->dispatch(
            'newdebugbar-profile-refreshed',
            summary: $this->summary,
            relatedProfiles: $relatedProfiles,
        );

    }

    #[Renderless]
    public function explainQuery(
        int $execution,
        ProfileStore $store,
        ProfilePresenter $presenter,
        QueryExplainer $explainer,
    ): void {
        abort_unless($execution > 0, 422);
        $profile = $presenter->present($store->get($this->profileId) ?? []);
        $query = collect($profile['inspectors']['queries']['payload']['items'] ?? [])
            ->firstWhere('execution', $execution);
        abort_unless(is_array($query), 404);

        try {
            $this->queryExplains[$execution] = $explainer->explain($query);
            unset($this->queryExplainErrors[$execution]);
        } catch (InvalidArgumentException $exception) {
            unset($this->queryExplains[$execution]);
            $this->queryExplainErrors[$execution] = $exception->getMessage();
        }

        $this->dispatch(
            'newdebugbar-query-explained',
            profileId: $this->profileId,
            execution: $execution,
            explain: $this->queryExplains[$execution] ?? null,
            error: $this->queryExplainErrors[$execution] ?? null,
        );
    }

    #[Renderless]
    public function switchProfile(
        string $profileId,
        ProfileStore $store,
        ProfilePresenter $presenter,
        ProfileSummaryPresenter $summaries,
    ): void {
        $this->activateProfile($profileId, $store, $presenter, $summaries);
    }

    #[Renderless]
    public function noticeProfile(
        string $profileId,
        ProfileStore $store,
        ProfilePresenter $presenter,
        ProfileSummaryPresenter $summaries,
    ): void {
        abort_unless($this->validProfileId($profileId), 422);
        $profile = $store->get($profileId);
        abort_if($profile === null, 404);

        $this->dispatch(
            'newdebugbar-profile-noticed',
            summary: $summaries->present($presenter->present($profile)),
        );
    }

    private function activateProfile(
        string $profileId,
        ProfileStore $store,
        ProfilePresenter $presenter,
        ProfileSummaryPresenter $summaries,
    ): void {
        abort_unless($this->validProfileId($profileId), 422);
        $profile = $store->get($profileId);
        abort_if($profile === null, 404);

        $this->profileId = $profileId;
        $this->summary = $this->makeSummary($presenter->present($profile), $summaries);
        $this->inspectorLoaded = false;
        $this->selectedInspector = self::DEFAULT_INSPECTOR;
        $this->timelineLimit = self::TIMELINE_PAGE_SIZE;
        $this->timelineFilter = 'key';
        $this->timelineSearch = '';
        $this->queryExplains = [];
        $this->queryExplainErrors = [];
        $this->dispatch('newdebugbar-profile-switched', summary: $this->summary);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function profile(): array
    {
        if (! $this->inspectorLoaded) {
            return [];
        }

        $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($this->profileId) ?? []);

        if ($this->selectedInspector === 'timeline') {
            $items = (array) ($profile['inspectors']['timeline']['payload']['items'] ?? []);
            $profile['inspectors']['timeline']['payload']['available_inspectors'] = array_values(array_unique(array_column($items, 'inspector')));
            $profile['inspectors']['timeline']['payload']['total_item_count'] = count($items);
            $profile['inspectors']['timeline']['payload']['total_duration_ms'] = max(0.001, ...array_column($items, 'at_ms'));
            $items = $this->filteredTimelineItems($items);
            $profile['inspectors']['timeline']['payload']['matching_item_count'] = count($items);
            $profile['inspectors']['timeline']['payload']['items'] = array_slice($items, 0, $this->timelineLimit);
            $profile['inspectors']['timeline']['payload']['has_more'] = count($items) > $this->timelineLimit;
        }

        if ($this->selectedInspector === 'views') {
            $groups = &$profile['inspectors']['views']['payload']['groups'];

            foreach ($groups as &$group) {
                foreach ($group['items'] as &$view) {
                    unset($view['data']);
                }
                unset($view);
            }
            unset($group);
        }

        return $profile;
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function filteredTimelineItems(array $items): array
    {
        $search = mb_strtolower(trim($this->timelineSearch));

        return array_values(array_filter($items, function (array $item) use ($search): bool {
            $inspector = $item['inspector'];
            $matchesInspector = $this->timelineFilter === 'all'
                || ($this->timelineFilter === 'key' && in_array($inspector, self::TIMELINE_KEY_INSPECTORS, true))
                || $inspector === $this->timelineFilter;
            $source = $item['source'] ?? [];
            $text = mb_strtolower(implode(' ', [
                $item['label'],
                $item['inspector_label'] ?? str_replace('_', ' ', $inspector),
                isset($source['file']) ? $source['file'].':'.($source['line'] ?? 1) : '',
            ]));

            return $matchesInspector && ($search === '' || str_contains($text, $search));
        }));
    }

    public function render(): View
    {
        return view('newdebugbar::livewire.debug-bar');
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function makeSummary(array $profile, ProfileSummaryPresenter $summaries): array
    {
        $inspectors = $profile['inspectors'] ?? [];
        $findings = is_array($profile['findings'] ?? null) ? $profile['findings'] : [];
        $summary = $summaries->present($profile);
        $findingCounts = [];
        $inspectorLinks = [];
        $inspectorCounts = [];

        foreach ($findings as $finding) {
            $inspectorKey = is_array($finding) ? ($finding['inspector'] ?? null) : null;

            if (is_string($inspectorKey)) {
                $findingCounts[$inspectorKey] = ($findingCounts[$inspectorKey] ?? 0) + 1;
            }
        }

        foreach ($inspectors as $key => $inspector) {
            if ($key === 'overview') {
                continue;
            }

            $label = $key === 'request'
                ? 'Requests'
                : (string) ($inspector['label'] ?? ucfirst($key));
            $count = match ($key) {
                'models' => $inspector['summary']['activity_count'] ?? $inspector['summary']['count'] ?? null,
                'notifications' => $inspector['summary']['notification_count'] ?? $inspector['summary']['count'] ?? null,
                default => $inspector['summary']['count'] ?? null,
            };
            $dropped = (int) ($inspector['summary']['dropped_count'] ?? 0);
            $secondaryDropped = (int) ($inspector['summary']['transaction_dropped_count'] ?? 0);
            $truncated = (bool) ($inspector['summary']['truncated'] ?? false)
                || $dropped > 0
                || $secondaryDropped > 0;
            $incomplete = (bool) ($inspector['payload']['incomplete'] ?? false);
            $findingCount = $findingCounts[$key] ?? 0;
            $attention = $findingCount > 0 || $truncated || $incomplete;
            $inspectorLinks[] = [
                'key' => $key,
                'label' => $label,
                'description' => $this->inspectorDescription((string) $key, $label),
                'layout' => 'workspace',
                'count' => $count,
                'active' => $count === null || (int) $count > 0 || $attention,
                'attention' => $attention,
                'finding_count' => $findingCount,
                'truncated' => $truncated,
                'incomplete' => $incomplete,
            ];
            $inspectorCounts[$key] = $count;
        }

        return [
            ...$summary,
            'id' => $summary['id'] ?? $this->profileId,
            'theme' => config('newdebugbar.theme', 'system'),
            'environment' => (string) ($summary['environment'] ?? app()->environment()),
            'method' => $summary['method'] ?? 'GET',
            'path' => $summary['path'] ?? '/',
            'inspectors' => $inspectorLinks,
            'inspector_counts' => $inspectorCounts,
        ];
    }

    private function inspectorDescription(string $key, string $label): string
    {
        return self::INSPECTOR_DESCRIPTIONS[$key]
            ?? 'Review the collected '.strtolower($label).' details for this request.';
    }

    private function validProfileId(string $profileId): bool
    {
        return ProfileStore::validId($profileId);
    }
}

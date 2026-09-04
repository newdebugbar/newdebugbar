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

/** Loads a request summary first and renders one inspector section at a time. */
final class DebugBar extends Component
{
    private const DEFAULT_SECTION = 'request';

    private const TIMELINE_PAGE_SIZE = 50;

    /** @var array<string, string> */
    private const SECTION_DESCRIPTIONS = [
        'authorization' => 'See what Laravel allowed or denied, for which user and arguments, then inspect the policy or Gate and source.',
        'cache' => 'Review cache reads, writes, deletes, stores, results, timing, and source.',
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
    public bool $sectionLoaded = false;

    #[Locked]
    public string $selectedSection = self::DEFAULT_SECTION;

    #[Locked]
    public int $timelineLimit = self::TIMELINE_PAGE_SIZE;

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

    public function loadSection(
        string $section,
        ProfileStore $store,
        ProfilePresenter $presenter,
    ): void {
        $stored = $store->get($this->profileId);
        abort_if($stored === null, 404);

        $profile = $presenter->present($stored);
        abort_unless(
            $section !== 'overview' && array_key_exists($section, (array) ($profile['sections'] ?? [])),
            422,
        );

        if ($this->selectedSection !== $section) {
            $this->timelineLimit = self::TIMELINE_PAGE_SIZE;
        }

        $this->selectedSection = $section;
        $this->sectionLoaded = true;
        $this->dispatch('newdebugbar-section-loaded', section: $section);
        $this->dispatch('newdebugbar-content-updated');
    }

    public function loadMoreTimeline(
        ProfileStore $store,
        ProfilePresenter $presenter,
    ): void {
        abort_unless($this->sectionLoaded && $this->selectedSection === 'timeline', 422);
        $stored = $store->get($this->profileId);
        abort_if($stored === null, 404);

        $profile = $presenter->present($stored);
        $items = (array) ($profile['sections']['timeline']['payload']['items'] ?? []);
        $this->timelineLimit = min(count($items), $this->timelineLimit + self::TIMELINE_PAGE_SIZE);
        $this->dispatch('newdebugbar-section-loaded', section: 'timeline');
        $this->dispatch('newdebugbar-content-updated');
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function loadViewData(
        int $renderOrder,
        ProfileStore $store,
        ProfilePresenter $presenter,
    ): array {
        abort_unless($this->sectionLoaded && $this->selectedSection === 'views' && $renderOrder > 0, 422);
        $profile = $presenter->present($store->get($this->profileId) ?? []);

        foreach ((array) ($profile['sections']['views']['payload']['groups'] ?? []) as $group) {
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
        $query = collect($profile['sections']['queries']['payload']['items'] ?? [])
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
        $this->sectionLoaded = false;
        $this->selectedSection = self::DEFAULT_SECTION;
        $this->timelineLimit = self::TIMELINE_PAGE_SIZE;
        $this->queryExplains = [];
        $this->queryExplainErrors = [];
        $this->dispatch('newdebugbar-profile-switched', summary: $this->summary);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function profile(): array
    {
        if (! $this->sectionLoaded) {
            return [];
        }

        $profile = app(ProfilePresenter::class)->present(app(ProfileStore::class)->get($this->profileId) ?? []);

        if ($this->selectedSection === 'timeline') {
            $items = (array) ($profile['sections']['timeline']['payload']['items'] ?? []);
            $profile['sections']['timeline']['payload']['available_sections'] = array_values(array_unique(array_column($items, 'section')));
            $profile['sections']['timeline']['payload']['total_item_count'] = count($items);
            $profile['sections']['timeline']['payload']['total_duration_ms'] = max(0.001, ...array_column($items, 'at_ms'));
            $profile['sections']['timeline']['payload']['items'] = array_slice($items, 0, $this->timelineLimit);
            $profile['sections']['timeline']['payload']['has_more'] = count($items) > $this->timelineLimit;
        }

        if ($this->selectedSection === 'views') {
            $groups = &$profile['sections']['views']['payload']['groups'];

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
        $sections = $profile['sections'] ?? [];
        $findings = is_array($profile['findings'] ?? null) ? $profile['findings'] : [];
        $summary = $summaries->present($profile);
        $findingCounts = [];
        $sectionLinks = [];
        $sectionCounts = [];

        foreach ($findings as $finding) {
            $sectionKey = is_array($finding) ? ($finding['section'] ?? null) : null;

            if (is_string($sectionKey)) {
                $findingCounts[$sectionKey] = ($findingCounts[$sectionKey] ?? 0) + 1;
            }
        }

        foreach ($sections as $key => $section) {
            if ($key === 'overview') {
                continue;
            }

            $label = $key === 'request'
                ? 'Requests'
                : (string) ($section['label'] ?? ucfirst($key));
            $count = match ($key) {
                'models' => $section['summary']['activity_count'] ?? $section['summary']['count'] ?? null,
                'notifications' => $section['summary']['notification_count'] ?? $section['summary']['count'] ?? null,
                default => $section['summary']['count'] ?? null,
            };
            $dropped = (int) ($section['summary']['dropped_count'] ?? 0);
            $secondaryDropped = (int) ($section['summary']['transaction_dropped_count'] ?? 0);
            $truncated = (bool) ($section['summary']['truncated'] ?? false)
                || $dropped > 0
                || $secondaryDropped > 0;
            $incomplete = (bool) ($section['payload']['incomplete'] ?? false);
            $findingCount = $findingCounts[$key] ?? 0;
            $attention = $findingCount > 0 || $truncated || $incomplete;
            $sectionLinks[] = [
                'key' => $key,
                'label' => $label,
                'description' => $this->sectionDescription((string) $key, $label),
                'layout' => 'workspace',
                'count' => $count,
                'active' => $count === null || (int) $count > 0 || $attention,
                'attention' => $attention,
                'finding_count' => $findingCount,
                'truncated' => $truncated,
                'incomplete' => $incomplete,
            ];
            $sectionCounts[$key] = $count;
        }

        return [
            ...$summary,
            'id' => $summary['id'] ?? $this->profileId,
            'theme' => config('newdebugbar.theme', 'system'),
            'environment' => (string) ($summary['environment'] ?? app()->environment()),
            'method' => $summary['method'] ?? 'GET',
            'path' => $summary['path'] ?? '/',
            'sections' => $sectionLinks,
            'section_counts' => $sectionCounts,
        ];
    }

    private function sectionDescription(string $key, string $label): string
    {
        return self::SECTION_DESCRIPTIONS[$key]
            ?? 'Review the collected '.strtolower($label).' details for this request.';
    }

    private function validProfileId(string $profileId): bool
    {
        return ProfileStore::validId($profileId);
    }
}

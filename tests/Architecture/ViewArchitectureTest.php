<?php

use NewDebugBar\Tests\Support\ProjectFiles;

it('keeps every Blade view focused', function () {
    $oversizedViews = [];
    $views = dirname(__DIR__, 2).'/resources/views';

    foreach (ProjectFiles::bladeFilesIn($views) as $file) {
        $lines = substr_count(file_get_contents($file->getPathname()), "\n") + 1;

        if ($lines > 500) {
            $oversizedViews[] = ProjectFiles::relativePath($file, $views).': '.$lines.' lines';
        }
    }

    expect($oversizedViews)->toBe([]);
});

it('keeps Timeline on shared inspector geometry with namespaced behavior hooks', function () {
    $timeline = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/timeline.blade.php');

    expect($timeline)
        ->toContain(
            '<x-newdebugbar::inspector-workspace',
            '<x-newdebugbar::inspector-list-panel',
            '<x-newdebugbar::inspector-list-controls',
            '<x-newdebugbar::search-field',
            '<x-newdebugbar::select-field',
            'data-ndb-timeline-page-sentinel',
            'observeTimelinePageEnd($el, $wire)',
            'copyText(selectedTimelineItem.source)',
        )
        ->not->toContain(
            'data-section=',
            'data-kind=',
            'data-position=',
            'data-start=',
            'data-duration=',
            'data-search=',
            'ndb:min-w-[760px]',
            'data-ndb-timeline-load-more',
            'wire:click="loadMoreTimeline"',
        );
});

it('keeps Views on one shared workspace with merged lazy detail evidence', function () {
    $root = dirname(__DIR__, 2);
    $views = file_get_contents($root.'/resources/views/livewire/sections/views.blade.php');
    $state = file_get_contents($root.'/resources/js/state.js');

    expect($views)
        ->toContain(
            '<x-newdebugbar::inspector-workspace',
            '<x-newdebugbar::inspector-list-panel',
            '<x-newdebugbar::inspector-list-controls',
            '<x-newdebugbar::search-field',
            '<x-newdebugbar::select-field',
            '<x-newdebugbar::inspector-detail-pane',
            '<x-newdebugbar::inspector-detail-header',
            '<x-newdebugbar::inspector-source-fact',
            'copyText(selectedViewRender.source_label)',
            'copyText(composer.source_label)',
            '<template x-if="selectedViewGroup">',
            '<template x-if="selectedViewRender.composers.length > 0">',
            'data-ndb-view-detail-content',
            'data-ndb-view-data-panel',
            'loadSelectedViewData($wire)',
        )
        ->not->toContain(
            '<details',
            '<x-newdebugbar::popover-surface',
            'viewDetailTab',
            'data-ndb-view-detail-tab',
            'No view composers were captured for this render.',
            'data-ndb-view-sort',
            'newDebugBar.viewData',
        )
        ->and($state)
        ->not->toContain(
            'createViewDataState',
            'viewSort:',
            'viewSortDirection:',
            'toggleViewSort(',
            'applyViewSort(',
            'selectedViewSourceKindLabel',
        );
});

it('keeps package interface text at a readable minimum size', function () {
    $undersizedText = [];
    $packageResources = dirname(__DIR__, 2).'/resources';

    foreach (ProjectFiles::bladeFilesIn($packageResources.'/views') as $file) {
        preg_match_all('/text-\\[(?<size>\\d+(?:\\.\\d+)?)px\\]/', file_get_contents($file->getPathname()), $matches);

        foreach ($matches['size'] as $size) {
            if ((float) $size < 11) {
                $undersizedText[] = ProjectFiles::relativePath($file, $packageResources.'/views').': '.$size.'px';
            }
        }
    }

    foreach (ProjectFiles::filesIn($packageResources.'/css') as $file) {
        preg_match_all('/font-size:\\s*(?<size>\\d+(?:\\.\\d+)?)px/', file_get_contents($file->getPathname()), $matches);

        foreach ($matches['size'] as $size) {
            if ((float) $size < 11) {
                $undersizedText[] = ProjectFiles::relativePath($file, $packageResources.'/css').': '.$size.'px';
            }
        }
    }

    expect($undersizedText)->toBe([]);
});

it('namespaces package-owned Blade identifiers away from host pages', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $attributeViolations = [];
    $literalIdViolations = [];
    $alpineIdViolations = [];

    foreach (ProjectFiles::bladeFilesIn($views) as $file) {
        $relativePath = ProjectFiles::relativePath($file, $views);
        $contents = file_get_contents($file->getPathname());

        preg_match_all('/(?:^|\s):?data-(?<name>[a-z0-9_-]+)/m', $contents, $attributes);

        foreach (array_unique($attributes['name']) as $name) {
            if (! str_starts_with($name, 'ndb-')) {
                $attributeViolations[] = $relativePath.': data-'.$name;
            }
        }

        preg_match_all('/(?:^|\s)(?:::|:)?id="(?<id>[^"]+)"/m', $contents, $ids);

        foreach (array_unique($ids['id']) as $id) {
            if (! str_contains($id, '{{') && ! str_contains($id, '$id(') && ! str_starts_with($id, 'newdebugbar')) {
                $literalIdViolations[] = $relativePath.': '.$id;
            }
        }

        preg_match_all('/x-id="\[(?<ids>[^]]+)]"/', $contents, $alpineGroups);

        foreach ($alpineGroups['ids'] as $group) {
            preg_match_all("/'(?<id>[^']+)'/", $group, $alpineIds);

            foreach ($alpineIds['id'] as $id) {
                if (! str_starts_with($id, 'newdebugbar')) {
                    $alpineIdViolations[] = $relativePath.': '.$id;
                }
            }
        }
    }

    expect($attributeViolations)->toBe([])
        ->and($literalIdViolations)->toBe([])
        ->and($alpineIdViolations)->toBe([]);
});

it('uses one popover surface for toolbar and inspector menus', function () {
    $views = dirname(__DIR__, 2).'/resources/views';

    foreach ([
        'components/mobile-toolbar-popover.blade.php',
        'components/request-switcher.blade.php',
        'components/mail-actions.blade.php',
        'components/theme-toggle.blade.php',
    ] as $view) {
        expect(file_get_contents($views.'/'.$view))
            ->toContain('<x-newdebugbar::popover-surface');
    }

    expect(file_get_contents($views.'/components/theme-toggle.blade.php'))
        ->toContain("'system' => ['System', 'monitor']")
        ->toContain("'light' => ['Light', 'sun']")
        ->toContain("'dark' => ['Dark', 'moon']")
        ->toContain('data-ndb-theme-option="{{ $theme }}"')
        ->toContain('role="menuitemradio"');
});

it('uses one filter tab treatment across inspector sections', function () {
    $views = dirname(__DIR__, 2).'/resources/views';

    foreach (['livewire/livewire/view-tabs.blade.php'] as $view) {
        $contents = file_get_contents($views.'/'.$view);

        expect($contents)
            ->toContain('<x-newdebugbar::filter-tabs')
            ->toContain('<x-newdebugbar::filter-tab');
    }

    foreach ([
        'components/event-detail.blade.php',
        'components/http-client-detail-tabs.blade.php',
        'components/query-detail.blade.php',
    ] as $view) {
        expect(file_get_contents($views.'/'.$view))
            ->toContain('<x-newdebugbar::inspector-detail-tabs')
            ->toContain('<x-newdebugbar::filter-tab');
    }

    expect(file_get_contents($views.'/components/inspector-detail-tabs.blade.php'))
        ->toContain('<x-newdebugbar::filter-tabs');
});

it('composes the HTTP Client workspace from focused view components', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/http_client.blade.php');
    $workspace = file_get_contents($views.'/components/http-client-workspace.blade.php');
    $detail = file_get_contents($views.'/components/http-client-detail.blade.php');
    $controls = file_get_contents($views.'/components/http-client-controls.blade.php');
    $listHeading = file_get_contents($views.'/components/http-client-list-heading.blade.php');
    $header = file_get_contents($views.'/components/http-client-header.blade.php');
    $request = file_get_contents($views.'/components/http-client-request-panel.blade.php');
    $response = file_get_contents($views.'/components/http-client-response-panel.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::http-client-workspace')
        ->toContain('<x-newdebugbar::empty-state')
        ->toContain('data-ndb-http-client-empty')
        ->not->toContain('data-ndb-http-client-list');

    expect($workspace)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::http-client-controls')
        ->toContain('<x-newdebugbar::http-client-list-heading')
        ->toContain('<x-newdebugbar::http-client-list-item')
        ->toContain('<x-newdebugbar::http-client-detail');

    expect($listHeading)
        ->toContain('Method')
        ->toContain('Request')
        ->toContain('Status')
        ->toContain('Time')
        ->toContain('<x-newdebugbar::inspector-sort-heading')
        ->toContain('data-ndb-http-client-sort-heading="duration"')
        ->not->toContain('data-ndb-http-client-sort-heading="method"')
        ->not->toContain('data-ndb-http-client-sort-heading="request"')
        ->not->toContain('data-ndb-http-client-sort-heading="status"');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::http-client-header')
        ->toContain('<x-newdebugbar::http-client-detail-tabs')
        ->toContain('<x-newdebugbar::http-client-request-panel')
        ->toContain('<x-newdebugbar::http-client-response-panel')
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('<x-newdebugbar::inspector-source-link')
        ->not->toContain('<x-newdebugbar::inspector-source-fact');

    expect($controls)
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain(':show-search="true"')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->not->toContain('$itemCount >= 5')
        ->not->toContain('<x-newdebugbar::filter-tabs')
        ->not->toContain('Oldest')
        ->not->toContain('Slowest');

    expect($header)
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-operation-badge')
        ->toContain('<x-newdebugbar::inspector-action');

    expect($request)
        ->toContain('<x-newdebugbar::inspector-facts')
        ->toContain('<x-newdebugbar::inspector-action')
        ->toContain('<x-newdebugbar::inspector-evidence');

    expect($response)
        ->toContain('<x-newdebugbar::inspector-facts')
        ->toContain('<x-newdebugbar::inspector-evidence')
        ->not->toContain('data-ndb-http-client-failure')
        ->toContain('<x-newdebugbar::http-client-no-response');

});

it('composes the Cache workspace from the shared inspector components', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/cache.blade.php');
    $workspace = file_get_contents($views.'/components/cache-workspace.blade.php');
    $detail = file_get_contents($views.'/components/cache-detail.blade.php');
    $controls = file_get_contents($views.'/components/cache-controls.blade.php');
    $header = file_get_contents($views.'/components/cache-header.blade.php');
    $listItem = file_get_contents($views.'/components/cache-list-item.blade.php');
    $overview = file_get_contents($views.'/components/cache-overview-panel.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::cache-workspace')
        ->toContain('<x-newdebugbar::empty-state')
        ->toContain('data-ndb-cache-empty')
        ->not->toContain('data-ndb-cache-list');

    expect($workspace)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::cache-controls')
        ->toContain('<x-newdebugbar::cache-list-item')
        ->toContain('<x-newdebugbar::cache-detail');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::cache-header')
        ->toContain('<x-newdebugbar::cache-overview-panel')
        ->not->toContain('cache-detail-tabs')
        ->not->toContain('cache-raw-panel');

    expect($overview)
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('<x-newdebugbar::inspector-source-fact');

    expect($controls)
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->not->toContain('<x-newdebugbar::filter-tabs')
        ->not->toContain('cacheSort');

    expect($header)
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-operation-badge')
        ->not->toContain('font-mono');

    expect($listItem)
        ->toContain('<x-newdebugbar::inspector-operation-badge')
        ->not->toContain('#{{');

    expect($overview)
        ->toContain('<x-newdebugbar::cache-overview-facts')
        ->toContain('<x-newdebugbar::inspector-definition-list')
        ->not->toContain('What happened')
        ->not->toContain('Check next');
});

it('composes Models as a shared split inspector with reusable explanations', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/models.blade.php');
    $detail = file_get_contents($views.'/components/model-group-detail.blade.php');
    $explanation = file_get_contents($views.'/components/inspector-explanation.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::inspector-sort-heading')
        ->toContain('data-ndb-model-sort-heading="model"')
        ->toContain('data-ndb-model-sort-heading="retrieved"')
        ->toContain('data-ndb-model-sort-heading="writes"')
        ->toContain('data-ndb-model-sort-heading="reloads"')
        ->toContain('data-ndb-model-summary-count')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::model-group')
        ->toContain('<x-newdebugbar::model-group-detail')
        ->not->toContain('Model activity totals');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-detail-tabs')
        ->toContain('variant="segmented"')
        ->toContain('data-ndb-model-detail-panel="records"')
        ->toContain('data-ndb-model-detail-panel="source"')
        ->not->toContain('data-ndb-model-detail-panel="overview"')
        ->not->toContain('Write evidence')
        ->not->toContain('Use the model identity and nearby application activity')
        ->not->toContain('related quer')
        ->not->toContain('navigateToQueriesAtSource');

    expect(substr_count($detail, '<x-newdebugbar::inspector-explanation'))->toBe(2);

    expect($explanation)
        ->toContain("'title' => null")
        ->toContain("'description' => null")
        ->toContain('@isset($heading)')
        ->toContain('@isset($body)')
        ->toContain('ndb:text-xs ndb:font-bold')
        ->toContain('ndb:text-[11px] ndb:leading-5');
});

it('composes Livewire as one shared inspector workspace with focused details', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/livewire.blade.php');
    $controls = file_get_contents($views.'/livewire/livewire/controls.blade.php');
    $activityDetail = file_get_contents($views.'/livewire/livewire/activity-detail.blade.php');
    $componentDetail = file_get_contents($views.'/livewire/livewire/component-detail.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('x-if="livewireTab === \'activity\'"')
        ->toContain('x-if="livewireTab === \'components\'"')
        ->not->toContain('<x-newdebugbar::livewire-split-view');

    expect($controls)
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->not->toContain('livewireActivityOrder')
        ->not->toContain('Newest first')
        ->not->toContain('Oldest first');

    foreach ([$activityDetail, $componentDetail] as $detail) {
        expect($detail)
            ->toContain('<x-newdebugbar::inspector-detail-header')
            ->toContain('<x-newdebugbar::inspector-detail-tabs')
            ->toContain('variant="segmented"')
            ->toContain('<x-newdebugbar::inspector-facts');
    }

    expect($activityDetail)
        ->toContain('data-ndb-livewire-detail-panel="overview"')
        ->toContain('data-ndb-livewire-detail-panel="trace"')
        ->toContain('livewireActivitySourceLabel(selectedLivewireActivity)')
        ->toContain("openRelatedProfile(profileId, 'request')")
        ->toContain('<x-newdebugbar::inspector-explanation');

    expect(file_get_contents($views.'/livewire/livewire/activity.blade.php'))
        ->toContain('aria-label="Livewire activity timeline"')
        ->toContain('data-ndb-livewire-activity-timeline-item')
        ->toContain('data-ndb-livewire-activity-connector')
        ->toContain('data-ndb-livewire-activity-dot');

    expect($componentDetail)
        ->toContain('data-ndb-livewire-detail-panel="properties"')
        ->toContain('data-ndb-livewire-detail-panel="source"')
        ->toContain('<x-newdebugbar::livewire-property-editor')
        ->toContain('<x-newdebugbar::inspector-source-fact')
        ->toContain('<x-newdebugbar::inspector-evidence')
        ->not->toContain('Source and recent activity are still available');
});

it('composes Queries as a bounded shared list detail workspace', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/components/query-section.blade.php');
    $detail = file_get_contents($views.'/components/query-detail.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->toContain('<x-newdebugbar::inspector-sort-heading')
        ->toContain('data-ndb-query-sort-heading')
        ->toContain('data-ndb-query-type-badge')
        ->toContain('data-ndb-query-list-driver')
        ->toContain("'duration_label' =>")
        ->toContain('ndb:bg-amber-50/70')
        ->toContain('ndb:bg-red-50/70')
        ->toContain('<x-newdebugbar::query-detail')
        ->not->toContain('data-ndb-query-attention-badge')
        ->not->toContain('label="Sort queries"')
        ->not->toContain('<details')
        ->not->toContain('<x-newdebugbar::query-execution')
        ->not->toContain('<x-newdebugbar::query-actions')
        ->not->toContain("'runnable_available' => \$runnableAvailable")
        ->not->toContain("'extra_executions' =>")
        ->not->toContain("'bindings_vary' =>")
        ->not->toContain("'source_short_label' =>")
        ->not->toContain('data-ndb-query-list-source');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-back')
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-detail-tabs')
        ->toContain('variant="segmented"')
        ->toContain('<x-newdebugbar::inspector-facts')
        ->toContain('<x-newdebugbar::inspector-evidence')
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('<x-newdebugbar::inspector-source-fact')
        ->toContain('<x-newdebugbar::inspector-explanation')
        ->toContain('<x-newdebugbar::inspector-action')
        ->toContain('data-ndb-query-detail-tab="overview"')
        ->toContain('<template x-if="queryDetailTab === \'overview\'">')
        ->toContain('data-ndb-query-detail-panel="overview"')
        ->not->toContain('<template x-if="queryDetailTab === \'bindings\'">')
        ->not->toContain('data-ndb-query-detail-tab="bindings"')
        ->toContain('data-ndb-query-sql')
        ->toContain('selectedQuery.display_sql')
        ->toContain('<template x-if="selectedQueryHasSource">')
        ->not->toContain('data-ndb-query-detail-tab="source"')
        ->not->toContain('<template x-if="queryDetailTab === \'source\'')
        ->toContain('<template x-if="queryDetailTab === \'explain\'">')
        ->toContain('@click="openQueryExplain($wire)"')
        ->not->toContain('data-ndb-query-explain-action')
        ->not->toContain('runQueryExplain($wire, true)')
        ->not->toContain('Run EXPLAIN again')
        ->not->toContain('Why these executions are grouped')
        ->not->toContain('What to check in this plan')
        ->not->toContain('<x-slot:metadata>')
        ->not->toContain('<dt class="ndb:font-semibold">Connection</dt>')
        ->not->toContain('<details')
        ->not->toContain('<pre');
});

it('uses one calm source presentation across inspector sections', function () {
    $resources = dirname(__DIR__, 2).'/resources';
    $views = $resources.'/views';

    foreach ([
        'components/mail-header.blade.php',
        'components/notification-header.blade.php',
    ] as $view) {
        expect(file_get_contents($views.'/'.$view))
            ->toContain('<x-newdebugbar::inspector-source-link');
    }

    foreach ([
        'components/cache-overview-panel.blade.php',
        'components/mail-message-details.blade.php',
        'components/notification-detail.blade.php',
        'components/query-detail.blade.php',
    ] as $view) {
        expect(file_get_contents($views.'/'.$view))
            ->toContain('<x-newdebugbar::inspector-source-panel')
            ->toContain('<x-newdebugbar::inspector-source-fact');
    }

    expect(file_get_contents($views.'/components/http-client-detail.blade.php'))
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('<x-newdebugbar::inspector-source-link')
        ->not->toContain('<x-newdebugbar::inspector-source-fact');

    expect(file_get_contents($views.'/components/mail-message-details.blade.php'))
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('data-ndb-mail-detail-panel="source"');

    expect(file_get_contents($views.'/components/inspector-source-panel.blade.php'))
        ->toContain('<x-newdebugbar::inspector-stack')
        ->toContain('data-ndb-inspector-source-panel');

    expect(file_get_contents($resources.'/css/newdebugbar.css'))
        ->toContain('@fontsource-variable/jetbrains-mono/files/jetbrains-mono-latin-wght-normal.woff2')
        ->toContain('--font-mono: "JetBrains Mono Variable"')
        ->toContain('font-variant-ligatures: contextual common-ligatures discretionary-ligatures');

    expect(file_get_contents($views.'/components/inspector-source-link.blade.php'))
        ->toContain('ndb:p-0')
        ->toContain('ndb:underline')
        ->not->toContain('<x-newdebugbar::icon')
        ->not->toContain('hover:bg-zinc-100');

    expect(file_get_contents($views.'/components/inspector-source-fact.blade.php'))
        ->not->toContain('<x-newdebugbar::icon');
});

it('routes every syntax-highlighted block through one code component', function () {
    $resources = dirname(__DIR__, 2).'/resources';
    $views = $resources.'/views';
    $rawCodeBlocks = [];

    foreach (ProjectFiles::bladeFilesIn($views) as $file) {
        $relativePath = ProjectFiles::relativePath($file, $views);

        if ($relativePath !== 'components/code-block.blade.php' && str_contains(file_get_contents($file->getPathname()), '<pre')) {
            $rawCodeBlocks[] = $relativePath;
        }
    }

    expect($rawCodeBlocks)->toBe([]);
    expect(file_get_contents($views.'/components/code-block.blade.php'))
        ->toContain('data-ndb-language="{{ $language }}"')
        ->toContain('ndb-code');

    expect(file_get_contents($resources.'/js/newdebugbar.js'))
        ->toContain("registerLanguage('http', http)")
        ->toContain("registerLanguage('json', json)")
        ->toContain("registerLanguage('php', php)")
        ->toContain("registerLanguage('sql', sql)");
});

it('uses the top-only frame across edge-to-edge inspector workspaces', function () {
    $views = dirname(__DIR__, 2).'/resources/views';

    foreach ([
        'components/cache-workspace.blade.php',
        'components/http-client-workspace.blade.php',
        'components/query-section.blade.php',
        'livewire/sections/authorization.blade.php',
        'livewire/sections/events.blade.php',
        'livewire/sections/exceptions.blade.php',
        'livewire/sections/logs.blade.php',
        'livewire/sections/livewire.blade.php',
        'livewire/sections/models.blade.php',
        'livewire/sections/mail.blade.php',
        'livewire/sections/notifications.blade.php',
        'livewire/sections/queue.blade.php',
        'livewire/sections/redis.blade.php',
        'livewire/sections/validation.blade.php',
        'livewire/sections/views.blade.php',
    ] as $view) {
        expect(file_get_contents($views.'/'.$view))
            ->toContain('<x-newdebugbar::inspector-workspace')
            ->toContain('frame="top"');
    }
});

it('keeps Requests as the lifecycle trace exception', function () {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/sections/request.blade.php');

    expect($view)
        ->toContain('data-ndb-request-trace')
        ->toContain('data-ndb-request-step="received"')
        ->toContain('data-ndb-request-step="matched"')
        ->toContain('data-ndb-request-step="responded"')
        ->toContain('data-ndb-request-details')
        ->not->toContain('<x-newdebugbar::inspector-workspace')
        ->not->toContain('requestDetailTab');
});

it('composes Authorization from the shared inspector workspace anatomy', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/authorization.blade.php');
    $detail = file_get_contents($views.'/components/authorization-detail.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->not->toContain('<input')
        ->not->toContain('<select');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-empty')
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-facts')
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain('<x-newdebugbar::inspector-source-fact')
        ->toContain('data-ndb-authorization-detail-panel="combined"')
        ->not->toContain('<x-newdebugbar::inspector-explanation')
        ->not->toContain('<x-newdebugbar::inspector-detail-tabs')
        ->not->toContain('<x-newdebugbar::filter-tab')
        ->not->toContain('authorizationDetailTab');
});

it('composes Events from the shared inspector workspace anatomy', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/events.blade.php');
    $detail = file_get_contents($views.'/components/event-detail.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->not->toContain('<input')
        ->not->toContain('<select')
        ->not->toContain('data-ndb-event-sort');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-empty')
        ->toContain('<x-newdebugbar::inspector-facts')
        ->toContain('<x-newdebugbar::inspector-source-link')
        ->toContain('<template x-if="eventDetailTab === \'overview\'">')
        ->toContain('<template x-if="eventDetailTab === \'payload\'">')
        ->toContain('<template x-if="eventDetailTab === \'source\'">')
        ->not->toContain('<x-newdebugbar::inspector-explanation')
        ->not->toContain('x-show.important="eventDetailTab ===');
});

it('adapts Exceptions between focused and list-detail workspaces', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/exceptions.blade.php');
    $detail = file_get_contents($views.'/components/exception-detail.blade.php');

    expect($section)
        ->toContain('@if (count($exceptions) === 1)')
        ->toContain('data-ndb-exception-layout="focused"')
        ->toContain('mode="stream"')
        ->toContain('data-ndb-exception-focused-detail')
        ->toContain('data-ndb-exception-layout="split"')
        ->toContain('<x-newdebugbar::inspector-workspace frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-back')
        ->toContain('<x-newdebugbar::exception-list-item')
        ->toContain('<x-newdebugbar::exception-detail')
        ->toContain("'queue' => 'Open worker'")
        ->toContain(':profile-action-label="$profileActionLabel"')
        ->not->toContain('ndb:bg-red-50')
        ->not->toContain('name="warning"');

    expect($detail)
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-detail-tabs')
        ->toContain('<x-newdebugbar::inspector-action')
        ->toContain('<x-newdebugbar::inspector-source-link')
        ->toContain('<x-newdebugbar::code-block')
        ->toContain('<x-newdebugbar::inspector-stack')
        ->toContain('<template x-if="exceptionDetailTab === \'source\'">')
        ->toContain('<template x-if="exceptionDetailTab === \'stack\'">')
        ->toContain('<template x-if="exceptionDetailTab === \'causes\'">')
        ->toContain('data-ndb-exception-context-action')
        ->toContain('data-ndb-exception-cause')
        ->not->toContain('handled');
});

it('composes Logs as a shared list-detail workspace', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $section = file_get_contents($views.'/livewire/sections/logs.blade.php');
    $entry = file_get_contents($views.'/components/log-entry.blade.php');
    $detail = file_get_contents($views.'/components/log-detail.blade.php');

    expect($section)
        ->toContain('<x-newdebugbar::inspector-workspace frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls :show-search="true" layout="compact">')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-empty')
        ->toContain('<x-newdebugbar::log-entry')
        ->toContain('<x-newdebugbar::log-detail')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->not->toContain('<input')
        ->not->toContain('<select')
        ->not->toContain('mode="stream"')
        ->not->toContain('data-ndb-log-order');

    expect($entry)
        ->toContain('@click="selectLogEntry(')
        ->not->toContain('View details')
        ->not->toContain('<x-newdebugbar::popover-surface')
        ->and($detail)
        ->toContain('<x-newdebugbar::inspector-detail-header')
        ->toContain('<x-newdebugbar::inspector-facts')
        ->toContain('<x-newdebugbar::inspector-definition-list')
        ->toContain('<x-newdebugbar::inspector-source-panel')
        ->toContain(':frames="\Illuminate\Support\Js::from($stack)"')
        ->toContain('<x-newdebugbar::inspector-action')
        ->not->toContain('<x-newdebugbar::popover-surface');
});

it('composes Validation as a shared diagnostic stream', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $contents = file_get_contents($views.'/livewire/sections/validation.blade.php');

    expect($contents)
        ->toContain('<x-newdebugbar::inspector-workspace mode="stream" frame="top"')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::validation-entry');

    expect(file_get_contents($views.'/components/validation-entry.blade.php'))
        ->toContain('<x-newdebugbar::inspector-source-link')
        ->toContain('<x-newdebugbar::inspector-explanation')
        ->not->toContain('ndb:font-mono ndb:text-xs ndb:font-semibold ndb:text-indigo');
});

it('composes fallback data as a shared full-height stream', function () {
    $views = dirname(__DIR__, 2).'/resources/views';

    expect(file_get_contents($views.'/livewire/sections/default.blade.php'))
        ->toContain('<x-newdebugbar::inspector-workspace mode="stream" frame="top"');
});

it('uses centered segmented controls across inspector detail panels', function () {
    $views = dirname(__DIR__, 2).'/resources/views';

    foreach ([
        'components/event-detail.blade.php',
        'components/http-client-detail-tabs.blade.php',
        'livewire/livewire/activity-detail.blade.php',
        'livewire/livewire/component-detail.blade.php',
        'components/model-group-detail.blade.php',
        'components/notification-detail.blade.php',
        'components/query-detail.blade.php',
    ] as $view) {
        expect(file_get_contents($views.'/'.$view))
            ->toContain('<x-newdebugbar::inspector-detail-tabs')
            ->toContain('variant="segmented"');
    }

    expect(file_get_contents($views.'/components/inspector-detail-tabs.blade.php'))
        ->toContain("'align' => 'center'")
        ->toContain('variant="segmented"')
        ->toContain('ndb:sm:col-start-2');

    expect(file_get_contents($views.'/livewire/sections/mail.blade.php'))
        ->toContain('<x-newdebugbar::inspector-list-panel')
        ->toContain('<x-newdebugbar::inspector-list-controls')
        ->toContain('<x-newdebugbar::search-field')
        ->toContain('<x-newdebugbar::select-field')
        ->toContain('<x-newdebugbar::inspector-detail-pane')
        ->toContain('<x-newdebugbar::inspector-detail-tabs')
        ->toContain('label="Mail detail"')
        ->toContain('align="left"')
        ->toContain('<x-newdebugbar::filter-tabs')
        ->not->toContain('<input')
        ->not->toContain('<select');
});

it('mounts only the active evidence tab in high-payload detail panes', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $notificationDetail = file_get_contents($views.'/components/notification-detail.blade.php');
    $mail = file_get_contents($views.'/livewire/sections/mail.blade.php');
    $mailDetails = file_get_contents($views.'/components/mail-message-details.blade.php');

    expect($notificationDetail)
        ->toContain(
            '<template x-if="notificationDetailTab === \'delivery\'">',
            '<template x-if="notificationDetailTab === \'payload\'">',
            '<template x-if="notificationDetailTab === \'source\'">',
        );

    expect($mail)
        ->toContain('<template x-if="mailDetailTab === \'preview\'">')
        ->not->toContain('x-show.important="mailDetailTab === \'preview\'"');

    expect($mailDetails)
        ->toContain(
            '<template x-if="mailDetailTab === \'message\'">',
            '<template x-if="mailDetailTab === \'source\'">',
        )
        ->not->toContain('x-show.important="mailDetailTab ===');

});

it('uses the shared section heading hierarchy in the inspector shell', function () {
    $inspector = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/inspector.blade.php');

    expect($inspector)
        ->toContain('<x-newdebugbar::section-heading>')
        ->not->toContain('<header data-ndb-section-header');
});

it('uses layout instead of punctuation to separate interface facts', function () {
    $resources = dirname(__DIR__, 2).'/resources';
    $offenders = [];

    foreach (ProjectFiles::filesIn($resources) as $file) {
        $contents = file_get_contents($file->getPathname());

        foreach (['•', '·', '&bull;', '&middot;', '&#8226;', '&#183;', '&#x2022;', '&#xB7;'] as $separator) {
            if (str_contains($contents, $separator)) {
                $offenders[] = ProjectFiles::relativePath($file, $resources).': '.$separator;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('respects reduced motion for toolbar drag animations', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/newdebugbar.css');

    expect($css)
        ->toContain('.ndb-toolbar-draggable')
        ->toMatch('/@media \(prefers-reduced-motion: reduce\)[\s\S]*#newdebugbar \*[\s\S]*transition-duration: 0\.001ms !important;/');
});

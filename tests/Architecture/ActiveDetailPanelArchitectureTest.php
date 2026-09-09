<?php

it('mounts only the active HTTP Client detail evidence', function () {
    $views = dirname(__DIR__, 2).'/resources/views/components';
    $detail = file_get_contents($views.'/http-client-detail.blade.php');
    $request = file_get_contents($views.'/http-client-request-panel.blade.php');
    $response = file_get_contents($views.'/http-client-response-panel.blade.php');

    expect($detail)
        ->toContain('<template x-if="httpClientDetailTab === \'response\'">')
        ->toContain('<template x-if="httpClientDetailTab === \'request\'">')
        ->toContain('<template x-if="selectedHttpClientRequest.has_source">')
        ->and(substr_count($detail, '<template x-if="httpClientDetailTab ==='))->toBe(2)
        ->and($detail)
        ->not->toContain('x-show.important="httpClientDetailTab')
        ->and($request)
        ->not->toContain('x-show.important="httpClientDetailTab')
        ->and($response)
        ->not->toContain('x-show.important="httpClientDetailTab');
});

it('mounts only the active Models detail evidence', function () {
    $detail = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/model-group-detail.blade.php');

    expect($detail)
        ->toContain('<template x-if="modelDetailTab === \'records\'">')
        ->toContain('<template x-if="modelDetailTab === \'source\'">')
        ->and(substr_count($detail, '<template x-if="modelDetailTab ==='))->toBe(2)
        ->and($detail)
        ->not->toContain('x-show.important="modelDetailTab');
});

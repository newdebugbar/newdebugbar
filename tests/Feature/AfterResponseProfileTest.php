<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use NewDebugBar\Storage\ProfileStore;

it('keeps the response profile open through terminate without inflating HTTP duration', function (): void {
    $kernel = app(Kernel::class);
    $request = Request::create('/profiled-after-response', 'GET', server: ['HTTP_ACCEPT' => 'text/html']);
    $response = $kernel->handle($request);
    $profileId = (string) $response->headers->get('X-NewDebugBar-Profile');
    $contentBeforeTerminate = (string) $response->getContent();
    $provisional = app(ProfileStore::class)->get($profileId);

    expect($profileId)->not->toBeEmpty()
        ->and($contentBeforeTerminate)->toContain('Original response', 'id="newdebugbar"')
        ->and($provisional)
        ->completion_state->toBe('terminating')
        ->inspectors->mail->summary->count->toBe(0);

    $kernel->terminate($request, $response);

    $profile = app(ProfileStore::class)->get($profileId);

    expect($response->headers->get('X-NewDebugBar-Profile'))->toBe($profileId)
        ->and((string) $response->getContent())->toBe($contentBeforeTerminate)
        ->and($profile)
        ->completion_state->toBe('complete')
        ->metrics->duration_ms->toBe($provisional['metrics']['duration_ms'])
        ->metrics->after_response_duration_ms->toBeGreaterThanOrEqual(80)
        ->inspectors->queue->summary->executed_count->toBe(1)
        ->inspectors->mail->summary->count->toBe(2)
        ->inspectors->queries->summary->count->toBeGreaterThanOrEqual(2)
        ->and(array_unique(array_column($profile['inspectors']['mail']['payload']['items'], 'lifecycle')))
        ->toBe(['after_response'])
        ->and($profile['inspectors']['queue']['payload']['items'][0])
        ->status->toBe('completed')
        ->lifecycle->toBe('after_response');
});

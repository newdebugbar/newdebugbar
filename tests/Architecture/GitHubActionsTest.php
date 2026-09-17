<?php

function githubActionsWorkflow(): string
{
    return file_get_contents(dirname(__DIR__, 2).'/.github/workflows/tests.yml');
}

it('pins CI runners and external actions', function () {
    $workflow = githubActionsWorkflow();

    preg_match_all('/^\s*runs-on:\s*(\S+)\s*$/m', $workflow, $runnerMatches);
    expect($runnerMatches[1])->not->toBeEmpty();

    foreach ($runnerMatches[1] as $runner) {
        expect($runner)->toBeIn(['ubuntu-24.04', 'windows-2025']);
    }

    preg_match_all('/^\s*- uses:\s+([^\s#]+)/m', $workflow, $actionMatches);
    expect($actionMatches[1])->not->toBeEmpty();

    foreach ($actionMatches[1] as $action) {
        [, $revision] = explode('@', $action, 2);

        expect($revision)->toMatch('/\A[0-9a-f]{40}\z/');
    }
});

it('retries only transient Composer audit infrastructure failures', function () {
    expect(githubActionsWorkflow())
        ->toContain('for audit_attempt in 1 2 3')
        ->toContain('composer audit || audit_exit=$?')
        ->toContain('if [ "$audit_exit" -ne 100 ] || [ "$audit_attempt" -eq 3 ]; then')
        ->toContain('exit "$audit_exit"')
        ->not->toContain('continue-on-error');
});

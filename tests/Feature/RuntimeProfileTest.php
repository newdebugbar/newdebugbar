<?php

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use NewDebugBar\Presentation\ProfilePresenter;
use NewDebugBar\Presentation\ProfileSummaryPresenter;
use NewDebugBar\ProfileManager;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Support\RuntimeProfiler;
use NewDebugBar\Tests\Fixtures\Jobs\ProfiledFailingJob;
use NewDebugBar\Tests\Fixtures\Jobs\ProfiledJob;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

it('stores an Artisan profile with argument names but never values', function () {
    $definition = new InputDefinition([
        new InputArgument('command', InputArgument::REQUIRED),
        new InputArgument('clinic', InputArgument::OPTIONAL),
        new InputOption('token', null, InputOption::VALUE_REQUIRED),
    ]);
    $input = new ArrayInput([
        'command' => 'clinic:sync',
        'clinic' => 'private-clinic-id',
        '--token' => 'private-token',
    ], $definition);
    $output = new BufferedOutput;

    Event::dispatch(new CommandStarting('clinic:sync', $input, $output));
    DB::select('select 1');
    Event::dispatch(new CommandFinished('clinic:sync', $input, $output, 0));

    $profile = app(ProfileStore::class)->recent()[0];

    expect($profile)
        ->profile_type->toBe('artisan')
        ->inspectors->request->label->toBe('Runtime')
        ->inspectors->request->summary->method->toBe('CLI')
        ->inspectors->request->summary->exit_code->toBe(0)
        ->inspectors->request->payload->path->toBe('artisan:clinic:sync')
        ->inspectors->request->payload->context->argument_names->toBe(['command', 'clinic'])
        ->inspectors->request->payload->context->option_names->toBe(['token'])
        ->inspectors->queries->summary->count->toBe(1)
        ->and(json_encode($profile))->not->toContain('private-clinic-id', 'private-token');
});

it('stores a failed test command as a warning profile', function () {
    $definition = new InputDefinition([new InputArgument('command', InputArgument::REQUIRED)]);
    $input = new ArrayInput(['command' => 'test'], $definition);
    $output = new BufferedOutput;

    Event::dispatch(new CommandStarting('test', $input, $output));
    Event::dispatch(new CommandFinished('test', $input, $output, 1));

    $profile = app(ProfileStore::class)->recent()[0];
    $presented = app(ProfilePresenter::class)->present($profile);
    $summary = app(ProfileSummaryPresenter::class)->present($presented);

    expect($profile)
        ->profile_type->toBe('test')
        ->inspectors->request->summary->exit_code->toBe(1)
        ->and($summary)
        ->request_type->toBe('test')
        ->warning->toBeTrue()
        ->and(array_column($presented['findings'], 'rule_id'))->toContain('runtime.error');
});

it('stores successful and failed queue worker jobs as separate profiles', function () {
    Bus::dispatchSync(new ProfiledJob('private successful payload'));

    try {
        Bus::dispatchSync(new ProfiledFailingJob('private failed payload'));
    } catch (RuntimeException) {
        // The worker would report this failure before moving to another job.
    }

    $profiles = app(ProfileStore::class)->recent();
    $failed = collect($profiles)->first(fn (array $profile): bool => $profile['inspectors']['request']['summary']['exit_code'] === 1);
    $successful = collect($profiles)->first(fn (array $profile): bool => $profile['inspectors']['request']['summary']['exit_code'] === 0);

    expect($profiles)->toHaveCount(2)
        ->and($successful)
        ->profile_type->toBe('queue')
        ->inspectors->queue->summary->executed_count->toBe(1)
        ->inspectors->exceptions->summary->count->toBe(0)
        ->and($failed)
        ->profile_type->toBe('queue')
        ->inspectors->queue->summary->failed_count->toBe(1)
        ->inspectors->exceptions->summary->count->toBe(1)
        ->and(json_encode($profiles))->not->toContain('private successful payload', 'private failed payload', 'private failure message');
});

it('resolves the profiler from the worker scope after Laravel forgets scoped instances', function () {
    $bootProfiler = app(RuntimeProfiler::class);

    app()->forgetScopedInstances();
    Bus::dispatchSync(new ProfiledJob('private scoped payload'));

    $profile = collect(app(ProfileStore::class)->recent())
        ->first(fn (array $candidate): bool => ($candidate['profile_type'] ?? null) === 'queue');

    expect(app(RuntimeProfiler::class))->not->toBe($bootProfiler)
        ->and($profile)->toBeArray()
        ->and($profile['inspectors']['queue']['summary']['executed_count'])->toBe(1);
});

it('does not wrap long running commands in one unbounded profile', function (string $command) {
    $definition = new InputDefinition([new InputArgument('command', InputArgument::REQUIRED)]);
    $input = new ArrayInput(['command' => $command], $definition);
    $output = new BufferedOutput;

    Event::dispatch(new CommandStarting($command, $input, $output));
    Event::dispatch(new CommandFinished($command, $input, $output, 0));

    expect(app(ProfileStore::class)->recent())->toBe([])
        ->and(app(ProfileManager::class)->isCollecting())->toBeFalse();
})->with([
    'queue worker' => 'queue:work',
    'MCP server' => 'mcp:start',
    'MCP Inspector' => 'mcp:inspector',
]);

it('does not let a nested runtime event finish its parent profile', function () {
    $runtime = app(RuntimeProfiler::class);

    expect($runtime->start('queue', ProfiledJob::class, ownerKey: 'parent'))->toBeTrue()
        ->and($runtime->finish(ownerKey: 'nested'))->toBeNull()
        ->and(app(ProfileManager::class)->isCollecting())->toBeTrue();

    $id = $runtime->finish(ownerKey: 'parent');

    expect($id)->toBeString()
        ->and(app(ProfileManager::class)->isCollecting())->toBeFalse()
        ->and(app(ProfileStore::class)->get($id))->profile_type->toBe('queue');
});

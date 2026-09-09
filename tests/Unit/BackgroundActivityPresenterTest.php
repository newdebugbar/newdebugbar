<?php

use Illuminate\Filesystem\Filesystem;
use NewDebugBar\Presentation\BackgroundActivityPresenter;
use NewDebugBar\Presentation\ProfileSummaryPresenter;
use NewDebugBar\Storage\BackgroundActivityStore;
use NewDebugBar\Support\Redactor;

it('reports unreadable background activity as unknown while retaining captured jobs', function (string $failure) {
    $files = new class($failure) extends Filesystem
    {
        public function __construct(private string $failure) {}

        public function isFile($path)
        {
            return true;
        }

        public function lastModified($path)
        {
            return time();
        }

        public function get($path, $lock = false)
        {
            if ($this->failure === 'unreadable') {
                throw new RuntimeException('Private storage path must not leak.');
            }

            return $this->failure === 'corrupt' ? '{broken' : 'null';
        }
    };
    $presenter = new BackgroundActivityPresenter(new BackgroundActivityStore($files, '/unused'));
    $profile = $presenter->present([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'completion_state' => 'complete',
        'sections' => ['queue' => ['payload' => ['items' => [[
            'status' => 'queued',
            'correlation_key' => str_repeat('a', 64),
        ]]]]],
    ]);
    $summary = (new ProfileSummaryPresenter(new Redactor))->present($profile);

    expect($profile['sections']['queue']['payload']['items'][0]['status'])->toBe('queued')
        ->and($profile['background_activity']['pending'])->toBeNull()
        ->and($profile['background_activity']['error'])->toBe(BackgroundActivityPresenter::READ_ERROR)
        ->and($summary['background_pending'])->toBeNull()
        ->and($summary['background_error'])->toBe(BackgroundActivityPresenter::READ_ERROR)
        ->and($summary['warning'])->toBeTrue();
})->with(['unreadable', 'corrupt', 'invalid object']);

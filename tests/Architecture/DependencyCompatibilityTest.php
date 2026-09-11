<?php

use Composer\InstalledVersions;
use Composer\Semver\Semver;

it('preserves the supported dependency floors', function () {
    $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    foreach (['php' => '8.1.0', 'laravel/framework' => '10.0.0', 'livewire/livewire' => '4.3.4'] as $package => $minimum) {
        expect(Semver::satisfies($minimum, $manifest['require'][$package]))
            ->toBeTrue("The compatibility contract must allow {$package} {$minimum}.");
    }

    expect(Semver::satisfies('4.999.999', $manifest['require']['livewire/livewire']))->toBeTrue()
        ->and(Semver::satisfies('4.3.3', $manifest['require']['livewire/livewire']))->toBeFalse()
        ->and(Semver::satisfies('3.999.999', $manifest['require']['livewire/livewire']))->toBeFalse()
        ->and(Semver::satisfies('5.0.0', $manifest['require']['livewire/livewire']))->toBeFalse();
});

it('runs against the Livewire version selected for compatibility verification', function () {
    $expected = getenv('NEWDEBUGBAR_TEST_LIVEWIRE') ?: '^4.3.4';
    $installed = InstalledVersions::getVersion('livewire/livewire');

    expect(Semver::satisfies($installed, $expected))
        ->toBeTrue("Expected Livewire {$expected}; installed {$installed}.");
});

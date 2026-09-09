<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NewDebugBar\Storage\ProfileStore;
use NewDebugBar\Tests\Support\FixedTimestampFilesystem;

beforeEach(function () {
    $this->profilePath = sys_get_temp_dir().'/newdebugbar-profile-store-tests';
    $this->files = new Filesystem;
    $this->files->deleteDirectory($this->profilePath);
});

afterEach(function () {
    $this->files->deleteDirectory($this->profilePath);
});

it('stores and reads an atomic profile', function () {
    $store = new ProfileStore($this->files, $this->profilePath);
    $id = (string) Str::uuid();

    expect($store->put(['id' => $id, 'environment' => 'local']))->toBe($id)
        ->and($store->get($id))->toMatchArray([
            'id' => $id,
            'environment' => 'local',
        ])
        ->and(fileperms($this->profilePath.'/'.$id.'.json') & 0777)->toBe(0600)
        ->and($this->files->get($this->profilePath.'/.gitignore'))->toBe("*\n")
        ->and(fileperms($this->profilePath.'/.gitignore') & 0777)->toBe(0600);
});

it('adds an ignore rule to an existing profile directory', function () {
    $this->files->ensureDirectoryExists($this->profilePath);

    $store = new ProfileStore($this->files, $this->profilePath);
    $store->put(['id' => (string) Str::uuid()]);

    expect($this->files->get($this->profilePath.'/.gitignore'))->toBe("*\n")
        ->and(fileperms($this->profilePath.'/.gitignore') & 0777)->toBe(0600);
});

it('preserves an existing profile directory ignore file', function () {
    $this->files->ensureDirectoryExists($this->profilePath);
    $this->files->put($this->profilePath.'/.gitignore', "*.json\n");

    $store = new ProfileStore($this->files, $this->profilePath);
    $store->put(['id' => (string) Str::uuid()]);

    expect($this->files->get($this->profilePath.'/.gitignore'))->toBe("*.json\n");
});

it('rejects unsafe profile identifiers', function () {
    $store = new ProfileStore($this->files, $this->profilePath);

    $store->get('../secrets');
})->throws(InvalidArgumentException::class);

it('uses one UUID v4 rule for stored profile identifiers', function () {
    expect(ProfileStore::validId((string) Str::uuid()))->toBeTrue()
        ->and(ProfileStore::validId('550e8400-e29b-11d4-a716-446655440000'))->toBeFalse()
        ->and(ProfileStore::validId('../secrets'))->toBeFalse();
});

it('distinguishes missing profiles from malformed stored data', function () {
    $store = new ProfileStore($this->files, $this->profilePath);
    $missing = (string) Str::uuid();
    $malformed = (string) Str::uuid();

    $this->files->ensureDirectoryExists($this->profilePath);
    $this->files->put($this->profilePath.'/'.$malformed.'.json', '{broken');

    expect($store->get($missing))->toBeNull();
    expect(fn () => $store->get($malformed))
        ->toThrow(RuntimeException::class, 'The debug profile could not be decoded.');
});

it('lists valid recent profiles within the retention limit', function () {
    $store = new ProfileStore($this->files, $this->profilePath, maxProfiles: 2);
    $first = (string) Str::uuid();
    $latest = (string) Str::uuid();

    $store->put(['id' => $first]);
    touch($this->profilePath.'/'.$first.'.json', now()->subSecond()->getTimestamp());
    clearstatcache(true, $this->profilePath.'/'.$first.'.json');
    $store->put(['id' => $latest]);

    expect(array_column($store->recent(), 'id'))->toBe([$latest, $first])
        ->and($store->recent(1))->toHaveCount(1)
        ->and($store->maxProfiles())->toBe(2);
});

it('retains the newest twenty profiles when a burst shares one filesystem timestamp', function () {
    $store = new ProfileStore(new FixedTimestampFilesystem, $this->profilePath);

    foreach (range(1, 20) as $sequence) {
        $store->put([
            'id' => sprintf('00000000-0000-4000-8000-%012d', $sequence),
            'sequence' => $sequence,
        ]);
    }

    $latest = 'ffffffff-ffff-4fff-bfff-ffffffffffff';
    $store->put(['id' => $latest, 'sequence' => 21]);

    expect($store->get($latest)['sequence'])->toBe(21)
        ->and(array_column($store->recent(), 'sequence'))->toBe(range(21, 2))
        ->and($store->get('00000000-0000-4000-8000-000000000001'))->toBeNull();
});

it('promotes an updated profile before pruning older records', function () {
    $store = new ProfileStore(new FixedTimestampFilesystem, $this->profilePath, maxProfiles: 2);
    $first = '00000000-0000-4000-8000-000000000001';
    $second = '00000000-0000-4000-8000-000000000002';
    $third = 'ffffffff-ffff-4fff-bfff-ffffffffffff';
    $store->put(['id' => $first, 'completion_state' => 'terminating']);
    $store->put(['id' => $second]);
    $updated = $store->get($first);
    $updated['completion_state'] = 'complete';
    $store->put($updated);
    $store->put(['id' => $third]);

    expect(array_column($store->recent(), 'id'))->toBe([$third, $first])
        ->and($store->get($first)['completion_state'])->toBe('complete')
        ->and($store->get($second))->toBeNull();
});

it('deletes an expired profile when it is read', function () {
    $store = new ProfileStore($this->files, $this->profilePath, maxAgeMinutes: 1);
    $id = (string) Str::uuid();

    $store->put(['id' => $id]);
    touch($this->profilePath.'/'.$id.'.json', now()->subMinutes(2)->getTimestamp());
    clearstatcache(true, $this->profilePath.'/'.$id.'.json');

    expect($store->get($id))->toBeNull()
        ->and($this->files->exists($this->profilePath.'/'.$id.'.json'))->toBeFalse();
});

it('prunes old and excess profiles', function () {
    $store = new ProfileStore($this->files, $this->profilePath, maxProfiles: 2, maxAgeMinutes: 1);
    $old = (string) Str::uuid();
    $first = (string) Str::uuid();
    $latest = (string) Str::uuid();

    $store->put(['id' => $old]);
    touch($this->profilePath.'/'.$old.'.json', now()->subMinutes(2)->getTimestamp());
    clearstatcache(true, $this->profilePath.'/'.$old.'.json');
    $store->put(['id' => $first]);
    touch($this->profilePath.'/'.$first.'.json', now()->subSeconds(10)->getTimestamp());
    clearstatcache(true, $this->profilePath.'/'.$first.'.json');
    $store->put(['id' => $latest]);

    expect($store->get($old))->toBeNull()
        ->and($store->get($first))->not->toBeNull()
        ->and($store->get($latest))->not->toBeNull();
});

it('reports profiles that cannot be encoded', function () {
    $store = new ProfileStore($this->files, $this->profilePath);
    $resource = fopen('php://memory', 'rb');

    try {
        $store->put(['id' => (string) Str::uuid(), 'resource' => $resource]);
    } finally {
        fclose($resource);
    }
})->throws(RuntimeException::class, 'The debug profile could not be encoded.');

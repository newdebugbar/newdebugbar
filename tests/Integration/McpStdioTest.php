<?php

use Composer\InstalledVersions;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

test('the real stdio server advertises complete profile access', function (string $protocolVersion) {
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $cachePath = $root.'/.phpunit.cache/newdebugbar-mcp-'.bin2hex(random_bytes(8));
    $files->ensureDirectoryExists($cachePath, 0700);
    $cacheEnvironmentPath = $cachePath;
    if (DIRECTORY_SEPARATOR === '\\'
        && strlen($cachePath) >= 3
        && $cachePath[1] === ':'
        && ($cachePath[2] === '\\' || $cachePath[2] === '/')) {
        $cacheEnvironmentPath = substr($cachePath, 2);
    }

    $modern = $protocolVersion === '2026-07-28';
    $request = function (int $id, string $method, array $params = []) use ($modern, $protocolVersion): array {
        if ($modern) {
            $params['_meta'] = [
                'io.modelcontextprotocol/protocolVersion' => $protocolVersion,
                'io.modelcontextprotocol/clientCapabilities' => (object) [],
            ];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object) $params];
    };
    $requests = [
        $request(1, $modern ? 'server/discover' : 'initialize', $modern ? [] : [
            'protocolVersion' => $protocolVersion,
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'newdebugbar-tests', 'version' => '1.0.0'],
        ]),
        $request(2, 'tools/list'),
    ];
    $missingProfile = ['profile_id' => '00000000-0000-4000-8000-000000000000'];
    $toolArguments = [
        'list-debug-profiles' => ['limit' => 1],
        'get-debug-profile-inspector' => [...$missingProfile, 'inspector' => 'queries'],
        'get-debug-profile-data' => [...$missingProfile, 'path' => '/inspectors'],
        'inspect-debug-queries' => $missingProfile,
        'get-debug-findings' => $missingProfile,
    ];
    foreach ($toolArguments as $name => $arguments) {
        $requests[] = $request(count($requests) + 1, 'tools/call', compact('name', 'arguments'));
    }

    $process = new Process(
        [PHP_BINARY, $root.'/vendor/bin/testbench', 'mcp:start', 'newdebugbar'],
        $root,
        [
            'APP_ENV' => 'local',
            'APP_PACKAGES_CACHE' => $cacheEnvironmentPath.'/packages.php',
            'APP_SERVICES_CACHE' => $cacheEnvironmentPath.'/services.php',
        ],
    );
    $process->setTimeout(15);
    $messages = $requests;
    if (! $modern) {
        array_splice($messages, 1, 0, [['jsonrpc' => '2.0', 'method' => 'notifications/initialized']]);
    }
    $process->setInput(implode("\n", array_map(
        fn (array $message): string => json_encode($message, JSON_THROW_ON_ERROR),
        $messages,
    ))."\n");

    try {
        $process->mustRun();
        $responses = array_map(
            fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            preg_split('/\r?\n/', trim($process->getOutput()), flags: PREG_SPLIT_NO_EMPTY),
        );

        expect($responses)->toHaveCount(count($requests));
        foreach ($responses as $index => $response) {
            expect($response)->not->toHaveKey('error')
                ->and($response['jsonrpc'])->toBe('2.0')
                ->and($response['id'])->toBe($requests[$index]['id']);
        }

        $handshake = $responses[0]['result'];
        $serverInfo = $modern
            ? $handshake['_meta']['io.modelcontextprotocol/serverInfo']
            : $handshake['serverInfo'];
        $tools = array_column($responses[1]['result']['tools'], null, 'name');

        expect($modern ? $handshake['supportedVersions'] : [$handshake['protocolVersion']])->toContain($protocolVersion)
            ->and($serverInfo)->toMatchArray(['name' => 'New Debug Bar', 'version' => '1.1.0'])
            ->and($files->exists($cachePath.'/packages.php'))->toBeTrue()
            ->and($files->exists($cachePath.'/services.php'))->toBeTrue()
            ->and($handshake['instructions'])->toContain('get-debug-profile-data', '/inspectors')
            ->and(array_keys($tools))->toBe(array_keys($toolArguments))
            ->and($tools['get-debug-profile-data']['inputSchema']['properties']['path']['default'])->toBe('/inspectors')
            ->and($tools['get-debug-profile-data']['outputSchema']['properties']['data']['properties'])
            ->toHaveKeys(['path', 'type', 'entries', 'value', 'chunks', 'pagination']);

        $toolResponses = array_combine(array_keys($toolArguments), array_slice($responses, 2));
        foreach ($toolResponses as $name => $response) {
            $result = $response['result'];
            expect($result['isError'] ?? false)->toBeFalse()
                ->and($result['structuredContent']['status'])->toBe($name === 'list-debug-profiles' ? 'ok' : 'not_found');
        }

        expect($toolResponses['get-debug-profile-data']['result']['structuredContent']['data'])->toMatchArray([
            ...$missingProfile,
            'path' => '/inspectors',
        ]);
    } finally {
        $process->stop();
        $files->deleteDirectory($cachePath);
    }
})->with(function (): array {
    $protocols = ['legacy initialize' => ['2025-06-18']];

    if (version_compare(InstalledVersions::getVersion('laravel/mcp'), '1.0.0', '>=')) {
        $protocols['modern discovery'] = ['2026-07-28'];
    }

    return $protocols;
});

<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Schema\Implementation;

test('the real stdio server advertises complete profile access', function () {
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
    $environment = [
        'APP_ENV' => 'local',
        'APP_PACKAGES_CACHE' => $cacheEnvironmentPath.'/packages.php',
        'APP_SERVICES_CACHE' => $cacheEnvironmentPath.'/services.php',
    ];
    $previousEnvironment = [];
    $client = null;

    try {
        foreach ($environment as $name => $value) {
            $previousEnvironment[$name] = [
                'getenv' => getenv($name),
                'env_exists' => array_key_exists($name, $_ENV),
                'env' => $_ENV[$name] ?? null,
            ];
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }

        $client = new Client(
            new class(PHP_BINARY, [$root.'/vendor/bin/testbench', 'mcp:start', 'newdebugbar']) extends StdioTransport
            {
                public function receive(): string
                {
                    $line = parent::receive();

                    if (json_decode($line, true) === null) {
                        throw new RuntimeException('Unexpected MCP subprocess output: '.substr(
                            ($this->process?->getOutput() ?? $line).($this->process?->getErrorOutput() ?? ''),
                            0,
                            4_000,
                        ));
                    }

                    return $line;
                }
            },
            new Implementation('newdebugbar-tests', '1.0.0'),
        );

        $client->withTimeout(10)->connect();
        $discovery = $client->discoverResult();
        $tools = $client->tools();
        $missing = $client->callTool('get-debug-profile-data', [
            'profile_id' => '00000000-0000-4000-8000-000000000000',
            'path' => '/inspectors',
        ]);

        expect($discovery?->serverInfo?->name)->toBe('New Debug Bar')
            ->and($files->exists($cachePath.'/packages.php'))->toBeTrue()
            ->and($files->exists($cachePath.'/services.php'))->toBeTrue()
            ->and($discovery?->serverInfo?->version)->toBe('1.1.0')
            ->and($discovery?->instructions)->toContain('get-debug-profile-data', '/inspectors')
            ->and($tools->keys()->all())->toBe([
                'list-debug-profiles',
                'get-debug-profile-inspector',
                'get-debug-profile-data',
                'inspect-debug-queries',
                'get-debug-findings',
            ])
            ->and($tools['get-debug-profile-data']->inputSchema['properties']['path']['default'])->toBe('/inspectors')
            ->and($tools['get-debug-profile-data']->outputSchema['properties']['data']['properties'])
            ->toHaveKeys(['path', 'type', 'entries', 'value', 'chunks', 'pagination'])
            ->and($missing->isError)->toBeFalse()
            ->and($missing->structuredContent)->toMatchArray([
                'status' => 'not_found',
                'data' => [
                    'profile_id' => '00000000-0000-4000-8000-000000000000',
                    'path' => '/inspectors',
                ],
            ]);
    } finally {
        $client?->disconnect();
        foreach ($previousEnvironment as $name => $environment) {
            $environment['getenv'] === false
                ? putenv($name)
                : putenv("{$name}={$environment['getenv']}");

            if ($environment['env_exists']) {
                $_ENV[$name] = $environment['env'];
            } else {
                unset($_ENV[$name]);
            }
        }
        $files->deleteDirectory($cachePath);
    }
})->skip(
    fn (): bool => ! class_exists(Client::class),
    'The installed Laravel MCP version does not provide its test client.',
);

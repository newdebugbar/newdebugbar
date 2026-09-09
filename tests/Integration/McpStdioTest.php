<?php

use Illuminate\Filesystem\Filesystem;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\Transport\StdioTransport;
use Laravel\Mcp\Schema\Implementation;

test('the real stdio server advertises complete profile access', function () {
    $root = dirname(__DIR__, 2);
    $files = new Filesystem;
    $cachePath = sys_get_temp_dir().'/newdebugbar-mcp-'.bin2hex(random_bytes(8));
    $files->ensureDirectoryExists($cachePath, 0700);
    $client = new Client(
        new class('/usr/bin/env', ['APP_ENV=local', 'APP_PACKAGES_CACHE='.$cachePath.'/packages.php', 'APP_SERVICES_CACHE='.$cachePath.'/services.php', PHP_BINARY, $root.'/vendor/bin/testbench', 'mcp:start', 'newdebugbar']) extends StdioTransport
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

    try {
        $client->withTimeout(10)->connect();
        $initialization = $client->initializeResult();
        $tools = $client->tools();
        $missing = $client->callTool('get-debug-profile-data', [
            'profile_id' => '00000000-0000-4000-8000-000000000000',
            'path' => '/sections',
        ]);

        expect($initialization?->serverInfo->name)->toBe('New Debug Bar')
            ->and($files->exists($cachePath.'/packages.php'))->toBeTrue()
            ->and($files->exists($cachePath.'/services.php'))->toBeTrue()
            ->and($initialization?->serverInfo->version)->toBe('1.1.0')
            ->and($initialization?->instructions)->toContain('get-debug-profile-data', '/sections')
            ->and($tools->keys()->all())->toBe([
                'list-debug-profiles',
                'get-debug-profile-section',
                'get-debug-profile-data',
                'inspect-debug-queries',
                'get-debug-findings',
            ])
            ->and($tools['get-debug-profile-data']->inputSchema['properties']['path']['default'])->toBe('/sections')
            ->and($tools['get-debug-profile-data']->outputSchema['properties']['data']['properties'])
            ->toHaveKeys(['path', 'type', 'entries', 'value', 'chunks', 'pagination'])
            ->and($missing->isError)->toBeFalse()
            ->and($missing->structuredContent)->toMatchArray([
                'status' => 'not_found',
                'data' => [
                    'profile_id' => '00000000-0000-4000-8000-000000000000',
                    'path' => '/sections',
                ],
            ]);
    } finally {
        $client->disconnect();
        $files->deleteDirectory($cachePath);
    }
})->skip(
    fn (): bool => ! class_exists(Client::class),
    'The installed Laravel MCP version does not provide its test client.',
);

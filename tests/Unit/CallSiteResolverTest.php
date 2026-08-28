<?php

use NewDebugBar\Support\CallSiteResolver;

it('captures bounded project relative call sites', function () {
    $root = dirname(__DIR__, 2);
    $location = (new CallSiteResolver(
        projectPath: $root,
        packagePath: $root,
        maxFrames: 2,
    ))->capture();

    expect($location['callsite']['file'])->toBe('tests/Unit/CallSiteResolverTest.php')
        ->and($location['callsite']['file'])->not->toStartWith('/')
        ->and($location['stack'])->toHaveCount(1);
});

it('can disable call site capture', function () {
    $location = (new CallSiteResolver(
        projectPath: dirname(__DIR__, 2),
        packagePath: dirname(__DIR__, 2),
        enabled: false,
    ))->capture();

    expect($location)->toBe(['callsite' => null, 'stack' => []]);
});

it('finds the first application location in a throwable', function () {
    $root = dirname(__DIR__, 2);
    $resolver = new CallSiteResolver(
        projectPath: $root,
        packagePath: $root,
    );
    $exception = new RuntimeException('Validation failed.');

    expect($resolver->fromThrowable($exception))->toMatchArray([
        'file' => 'tests/Unit/CallSiteResolverTest.php',
    ]);
});

it('resolves template files without applying the application call site filter', function () {
    $root = dirname(__DIR__, 2);
    $resolver = new CallSiteResolver(
        projectPath: $root,
        packagePath: $root,
        enabled: false,
    );

    expect($resolver->location($root.'/vendor/autoload.php'))->toBeNull()
        ->and($resolver->templateLocation($root.'/vendor/autoload.php'))->toBe([
            'file' => 'vendor/autoload.php',
            'line' => 1,
        ]);
});

it('exposes compiled Blade provenance only when a collector asks for it', function () {
    $root = sys_get_temp_dir().'/newdebugbar-callsite-'.bin2hex(random_bytes(6));
    $compiledDirectory = $root.'/storage/framework/views';
    $sourceDirectory = $root.'/resources/views';
    mkdir($compiledDirectory, 0777, true);
    mkdir($sourceDirectory, 0777, true);
    $resolvedRoot = realpath($root);
    expect($resolvedRoot)->not->toBeFalse();
    $source = $sourceDirectory.'/plain.blade.php';
    $compiled = $compiledDirectory.'/plain.php';
    file_put_contents($source, '<p>Plain view</p>');
    file_put_contents($compiled, <<<'PHP'
        <?php
        $captureCompiledView = static fn (): array => $resolver->capture(includeCompiledView: true);

        return [
            'default' => $resolver->capture(),
            'included' => $captureCompiledView(),
        ];
        ?>
        PHP.PHP_EOL.'<?php /**PATH '.$source.' ENDPATH**/ ?>');
    $resolver = new CallSiteResolver(
        projectPath: $resolvedRoot,
        packagePath: dirname(__DIR__, 2),
        compiledViewPath: $compiledDirectory,
    );

    $location = (static fn (string $file, CallSiteResolver $resolver): array => include $file)($compiled, $resolver);

    expect($location['default'])->toBe(['callsite' => null, 'stack' => []])
        ->and($location['included']['callsite'])
        ->file->toBe('storage/framework/views/plain.php')
        ->line->toBe(2)
        ->kind->toBe('compiled_view')
        ->template_file->toBe('resources/views/plain.blade.php')
        ->and($location['included']['stack'][0]['function'])->toBe('Compiled Blade view');

    unlink($compiled);
    unlink($source);
    rmdir($compiledDirectory);
    rmdir(dirname($compiledDirectory));
    rmdir(dirname($compiledDirectory, 2));
    rmdir($sourceDirectory);
    rmdir(dirname($sourceDirectory));
    rmdir($root);
});

it('resolves compiled Blade provenance when a customized path is created after construction', function () {
    $root = sys_get_temp_dir().'/newdebugbar-callsite-'.bin2hex(random_bytes(6));
    $compiledDirectory = $root.'/compiled-views';
    $sourceDirectory = $root.'/resources/views';
    mkdir($sourceDirectory, 0777, true);
    $resolvedRoot = realpath($root);
    expect($resolvedRoot)->not->toBeFalse();
    $source = $sourceDirectory.'/custom.blade.php';
    $compiled = $compiledDirectory.'/custom.php';
    $resolver = new CallSiteResolver(
        projectPath: $resolvedRoot,
        packagePath: dirname(__DIR__, 2),
        compiledViewPath: $root.'/./compiled-views',
    );

    expect($resolver->capture(includeCompiledView: true))->toBe(['callsite' => null, 'stack' => []]);

    mkdir($compiledDirectory, 0777, true);
    file_put_contents($source, '<p>Custom view</p>');
    file_put_contents($compiled, <<<'PHP'
        <?php

        return $resolver->capture(includeCompiledView: true);
        ?>
        PHP.PHP_EOL.'<?php /**PATH '.$source.' ENDPATH**/ ?>');

    $location = (static fn (string $file, CallSiteResolver $resolver): array => include $file)($compiled, $resolver);

    expect($location['callsite'])
        ->file->toBe('compiled-views/custom.php')
        ->kind->toBe('compiled_view')
        ->template_file->toBe('resources/views/custom.blade.php');

    unlink($compiled);
    unlink($source);
    rmdir($compiledDirectory);
    rmdir($sourceDirectory);
    rmdir(dirname($sourceDirectory));
    rmdir($root);
});

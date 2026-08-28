<?php

namespace NewDebugBar\Support;

use Throwable;

/** Captures a short project-relative stack without arguments or vendor frames. */
final class CallSiteResolver
{
    /** Keep the configured path because Laravel may create this directory after boot. */
    private readonly ?string $configuredCompiledViewPath;

    /** Canonical directory used to identify compiled Blade stack frames. */
    private ?string $compiledViewPath;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $packagePath,
        private readonly bool $enabled = true,
        private readonly int $maxFrames = 5,
        private readonly int $scanLimit = 40,
        ?string $compiledViewPath = null,
    ) {
        $this->configuredCompiledViewPath = $compiledViewPath;
        $this->compiledViewPath = $this->normalizeDirectory($compiledViewPath);
    }

    /**
     * @return array{
     *     callsite: array{file: string, line: int, kind?: string, template_file?: string}|null,
     *     stack: list<array{file: string, line: int, function: string, kind?: string, template_file?: string}>
     * }
     */
    public function capture(bool $includeCompiledView = false): array
    {
        if (! $this->enabled) {
            return ['callsite' => null, 'stack' => []];
        }

        // A custom compiled-view directory may not exist until Laravel renders its first view.
        $this->compiledViewPath ??= $this->normalizeDirectory($this->configuredCompiledViewPath);

        $frames = [];
        $compiledAuthorizationFrame = null;
        $compiledViewFrame = null;

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->scanLimit) as $frame) {
            $file = isset($frame['file']) ? $this->normalizePath((string) $frame['file']) : null;

            if ($file === null) {
                continue;
            }

            $compiledAuthorizationFrame ??= $this->compiledAuthorizationLocation(
                $file,
                (int) ($frame['line'] ?? 0),
            );

            if ($includeCompiledView) {
                $compiledViewFrame ??= $this->compiledViewLocation(
                    $file,
                    (int) ($frame['line'] ?? 0),
                );
            }

            if (! $this->isApplicationFile($file)) {
                continue;
            }

            $frames[] = [
                'file' => $this->relativePath($file),
                'line' => (int) ($frame['line'] ?? 0),
                'function' => $this->functionName($frame),
            ];

            if (count($frames) >= $this->maxFrames) {
                break;
            }
        }

        if ($compiledAuthorizationFrame !== null) {
            array_unshift($frames, [
                ...$compiledAuthorizationFrame,
                'function' => 'Blade authorization directive',
            ]);
            $frames = array_slice($frames, 0, $this->maxFrames);
        } elseif ($compiledViewFrame !== null) {
            array_unshift($frames, [
                ...$compiledViewFrame,
                'function' => 'Compiled Blade view',
            ]);
            $frames = array_slice($frames, 0, $this->maxFrames);
        }

        $callsite = $frames[0] ?? null;

        if (is_array($callsite)) {
            unset($callsite['function']);
        }

        return [
            'callsite' => $callsite,
            'stack' => $frames,
        ];
    }

    /** Returns the first application frame from a thrown error. */
    public function fromThrowable(Throwable $exception): ?array
    {
        foreach ([
            ['file' => $exception->getFile(), 'line' => $exception->getLine()],
            ...$exception->getTrace(),
        ] as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            $location = $this->location((string) $frame['file'], (int) ($frame['line'] ?? 1));

            if ($location !== null) {
                return $location;
            }
        }

        return null;
    }

    /** @return array{file: string, line: int}|null */
    public function location(string $path, int $line = 1): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        $file = $this->normalizePath($path);

        if ($file === null || ! $this->isApplicationFile($file)) {
            return null;
        }

        return [
            'file' => $this->relativePath($file),
            'line' => max(1, $line),
        ];
    }

    /** @return array{file: string, line: int}|null */
    public function templateLocation(string $path, int $line = 1): ?array
    {
        $file = $this->normalizePath($path);

        if ($file === null) {
            return null;
        }

        $project = rtrim(str_replace('\\', '/', $this->projectPath), '/').'/';

        return [
            'file' => str_starts_with($file, $project) ? substr($file, strlen($project)) : $file,
            'line' => max(1, $line),
        ];
    }

    /** Compiled templates are matched by path, so a customized view.compiled still resolves. */
    private function isCompiledView(string $file): bool
    {
        return $this->compiledViewPath !== null
            && str_ends_with($file, '.php')
            && str_starts_with($file, $this->compiledViewPath.'/');
    }

    private function normalizeDirectory(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $normalized = $this->normalizePath($path);

        return $normalized === null ? null : rtrim($normalized, '/');
    }

    private function normalizePath(string $path): ?string
    {
        $realPath = realpath($path);

        return $realPath === false ? null : str_replace('\\', '/', $realPath);
    }

    private function isApplicationFile(string $file): bool
    {
        $project = rtrim(str_replace('\\', '/', $this->projectPath), '/').'/';
        $package = rtrim(str_replace('\\', '/', $this->packagePath), '/').'/';

        return str_starts_with($file, $project)
            && ! str_starts_with($file, $project.'vendor/')
            && ! str_starts_with($file, $project.'storage/')
            && ! str_starts_with($file, $package.'src/');
    }

    private function relativePath(string $file): string
    {
        $project = rtrim(str_replace('\\', '/', $this->projectPath), '/').'/';

        return str_starts_with($file, $project) ? substr($file, strlen($project)) : basename($file);
    }

    /** @return array{file: string, line: int}|null */
    private function compiledAuthorizationLocation(string $file, int $line): ?array
    {
        if (! $this->isCompiledView($file)) {
            return null;
        }

        if (! is_readable($file)) {
            return null;
        }

        $compiled = file_get_contents($file);

        if (! is_string($compiled) || ! preg_match('/<\?php \/\*\*PATH (.+?) ENDPATH\*\*\/ \?>\s*$/s', $compiled, $pathMatch)) {
            return null;
        }

        $source = $this->normalizePath($pathMatch[1]);

        if ($source === null || ! $this->isApplicationFile($source)) {
            return null;
        }

        if (! is_readable($source) || ! is_string($sourceContents = file_get_contents($source))) {
            return null;
        }

        $compiledLines = preg_split('/\R/', $compiled) ?: [];
        $sourceLines = preg_split('/\R/', $sourceContents) ?: [];
        $compiledDirectives = [];
        $sourceDirectives = [];

        foreach ($compiledLines as $index => $compiledLine) {
            if (
                str_contains($compiledLine, '\\Illuminate\\Contracts\\Auth\\Access\\Gate::class')
                && (str_contains($compiledLine, '->check(') || str_contains($compiledLine, '->any('))
            ) {
                $compiledDirectives[] = $index + 1;
            }
        }

        foreach ($sourceLines as $index => $sourceLine) {
            if (preg_match('/(?<!@)@(?:can|cannot|canany|elsecan|elsecannot)\s*\(/', $sourceLine) === 1) {
                $sourceDirectives[] = $index + 1;
            }
        }

        $directiveIndex = array_search($line, $compiledDirectives, true);

        if ($directiveIndex === false) {
            foreach ($compiledDirectives as $index => $compiledLine) {
                if (abs($compiledLine - $line) <= 2) {
                    $directiveIndex = $index;
                    break;
                }
            }
        }

        if ($directiveIndex !== false && isset($sourceDirectives[$directiveIndex])) {
            return [
                'file' => $this->relativePath($source),
                'line' => $sourceDirectives[$directiveIndex],
            ];
        }

        return null;
    }

    /** @return array{file: string, line: int, kind: string, template_file: string}|null */
    private function compiledViewLocation(string $file, int $line): ?array
    {
        if (! $this->isCompiledView($file)) {
            return null;
        }

        if (! is_readable($file)) {
            return null;
        }

        $compiled = file_get_contents($file);

        if (! is_string($compiled) || ! preg_match('/<\?php \/\*\*PATH (.+?) ENDPATH\*\*\/ \?>\s*$/s', $compiled, $pathMatch)) {
            return null;
        }

        $source = $this->normalizePath($pathMatch[1]);

        if ($source === null || ! $this->isApplicationFile($source)) {
            return null;
        }

        return [
            'file' => $this->relativePath($file),
            'line' => max(1, $line),
            'kind' => 'compiled_view',
            'template_file' => $this->relativePath($source),
        ];
    }

    /** @param array<string, mixed> $frame */
    private function functionName(array $frame): string
    {
        return (string) (($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''));
    }
}

<?php

namespace NewDebugBar\Support;

use Throwable;

/** Builds bounded project-relative exception evidence for the inspector. */
final class ExceptionNormalizer
{
    private const MAX_CAUSES = 5;

    public function __construct(
        private readonly string $projectPath,
        private readonly string $packagePath,
        private readonly int $maxApplicationFrames = 12,
        private readonly int $maxVendorFrames = 12,
        private readonly int $sourceContextLines = 9,
    ) {}

    /** @return array<string, mixed> */
    public function normalize(Throwable $exception): array
    {
        $causes = [];
        $seen = [spl_object_id($exception) => true];
        $previous = $exception->getPrevious();

        $chainTruncated = false;

        while ($previous instanceof Throwable) {
            $objectId = spl_object_id($previous);

            if (isset($seen[$objectId])) {
                break;
            }

            if (count($causes) >= self::MAX_CAUSES) {
                $chainTruncated = true;

                break;
            }

            $seen[$objectId] = true;
            $causes[] = $this->normalizeException($previous);
            $previous = $previous->getPrevious();
        }

        return [
            ...$this->normalizeException($exception),
            'causes' => $causes,
            'chain_truncated' => $chainTruncated,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeException(Throwable $exception): array
    {
        $origin = $this->frame($exception->getFile(), $exception->getLine(), 'throw');
        $application = [];
        $vendor = [];
        $seen = [];

        foreach ([
            ['file' => $exception->getFile(), 'line' => $exception->getLine(), 'function' => 'throw'],
            ...$exception->getTrace(),
        ] as $trace) {
            if (! isset($trace['file'])) {
                continue;
            }

            $frame = $this->frame(
                (string) $trace['file'],
                (int) ($trace['line'] ?? 0),
                (string) (($trace['class'] ?? '').($trace['type'] ?? '').$trace['function']),
            );
            $signature = $frame['file'].':'.$frame['line'].':'.$frame['function'];

            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $target = $frame['application'] ? 'application' : 'vendor';

            if ($target === 'application' && count($application) < $this->maxApplicationFrames) {
                $application[] = $this->publicFrame($frame);
            } elseif ($target === 'vendor' && count($vendor) < $this->maxVendorFrames) {
                $vendor[] = $this->publicFrame($frame);
            }
        }

        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $origin['file'],
            'line' => $origin['line'],
            'frames' => [
                'application' => $application,
                'vendor' => $vendor,
            ],
            'source' => $origin['application']
                ? $this->sourceContext($exception->getFile(), $exception->getLine(), $origin['file'])
                : null,
        ];
    }

    /** @return array{file: string, line: int, function: string, application: bool} */
    private function frame(string $file, int $line, string $function): array
    {
        $normalized = $this->normalizePath($file);

        return [
            'file' => $this->relativePath($normalized),
            'line' => max(0, $line),
            'function' => $function === '' ? 'unknown' : $function,
            'application' => $this->isApplicationFile($normalized),
        ];
    }

    /** @param array{file: string, line: int, function: string, application: bool} $frame @return array{file: string, line: int, function: string} */
    private function publicFrame(array $frame): array
    {
        unset($frame['application']);

        return $frame;
    }

    /** @return array{file: string, start_line: int, focus_line: int, lines: list<array{number: int, code: string, focus: bool}>}|null */
    private function sourceContext(string $file, int $focusLine, string $relativeFile): ?array
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $contents = file($file, FILE_IGNORE_NEW_LINES);

        if (! is_array($contents) || $contents === []) {
            return null;
        }

        $half = intdiv(max(1, $this->sourceContextLines), 2);
        $start = max(1, $focusLine - $half);
        $end = min(count($contents), $start + max(1, $this->sourceContextLines) - 1);
        $lines = [];

        for ($number = $start; $number <= $end; $number++) {
            $code = (string) ($contents[$number - 1] ?? '');
            $lines[] = [
                'number' => $number,
                'code' => mb_strlen($code) > 500 ? mb_substr($code, 0, 499).'…' : $code,
                'focus' => $number === $focusLine,
            ];
        }

        return [
            'file' => $relativeFile,
            'start_line' => $start,
            'focus_line' => $focusLine,
            'lines' => $lines,
        ];
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        return str_replace('\\', '/', $realPath === false ? $path : $realPath);
    }

    private function isApplicationFile(string $file): bool
    {
        $project = rtrim(str_replace('\\', '/', $this->projectPath), '/').'/';
        $package = rtrim(str_replace('\\', '/', $this->packagePath), '/').'/';
        $separatePackage = rtrim($project, '/') !== rtrim($package, '/');

        return str_starts_with($file, $project)
            && ! str_starts_with($file, $project.'vendor/')
            && (! $separatePackage || ! str_starts_with($file, $package));
    }

    private function relativePath(string $file): string
    {
        $project = rtrim(str_replace('\\', '/', $this->projectPath), '/').'/';
        $package = rtrim(str_replace('\\', '/', $this->packagePath), '/').'/';

        if (str_starts_with($file, $project)) {
            return substr($file, strlen($project));
        }

        if (str_starts_with($file, $package)) {
            return 'vendor/newdebugbar/newdebugbar/'.substr($file, strlen($package));
        }

        return basename($file);
    }
}

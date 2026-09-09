<?php

namespace NewDebugBar\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves the package's compiled local assets with immutable caching. */
final class AssetController
{
    private const CONTENT_TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'woff2' => 'font/woff2',
    ];

    public function __invoke(string $path): BinaryFileResponse
    {
        if (str_contains($path, '..')) {
            abort(404);
        }

        $root = realpath(__DIR__.'/../../../dist');
        $file = realpath(__DIR__.'/../../../dist/'.$path);

        if ($root === false || $file === false || ! is_file($file) || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (! isset(self::CONTENT_TYPES[$extension])) {
            abort(404);
        }

        return response()->file($file, [
            'Content-Type' => self::CONTENT_TYPES[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

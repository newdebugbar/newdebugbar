<?php

namespace NewDebugBar\Storage;

use SplFileInfo;

/** Keeps a precise write timestamp first so ordering never needs to decode a full profile. */
final class StoredJson
{
    /** @param array<string, mixed> $record */
    public static function encode(array &$record): string
    {
        $record = ['stored_at' => sprintf('%.6F', microtime(true))] + $record;

        return json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function timestamp(SplFileInfo $file): float
    {
        $stream = @fopen($file->getPathname(), 'rb');

        if ($stream === false) {
            return (float) $file->getMTime();
        }

        try {
            fgets($stream, 128);
            $header = fgets($stream, 128);
        } finally {
            fclose($stream);
        }

        $metadata = json_decode('{'.rtrim(trim((string) $header), ',').'}', true);

        // Profiles written before precise ordering was introduced remain readable.
        return is_array($metadata) && is_numeric($metadata['stored_at'] ?? null)
            ? (float) $metadata['stored_at']
            : (float) $file->getMTime();
    }
}

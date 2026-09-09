<?php

namespace NewDebugBar\Tests\Support;

use Illuminate\Filesystem\Filesystem;

/** Reproduces a burst whose files all have the same whole-second modification time. */
final class FixedTimestampFilesystem extends Filesystem
{
    private ?int $timestamp = null;

    public function put($path, $contents, $lock = false)
    {
        $result = parent::put($path, $contents, $lock);

        if ($result !== false) {
            touch($path, $this->timestamp ??= time());
            clearstatcache(true, $path);
        }

        return $result;
    }
}

<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

class SafeFilesystem extends Filesystem
{
    public function replace($path, $content, $mode = null): void
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $this->ensureDirectoryExists(dirname($path));

        file_put_contents($path, $content, LOCK_EX);

        if (! is_null($mode)) {
            @chmod($path, $mode);

            return;
        }

        @chmod($path, 0777 - umask());
    }
}

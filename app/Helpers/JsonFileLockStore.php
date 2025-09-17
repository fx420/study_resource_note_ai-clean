<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class JsonFileLockStore
{
    public function updateAtomic(string $path, callable $updater, int $retries = 3): bool
    {
        $dir = dirname($path);
        if (! File::exists($dir)) {
            try {
                File::makeDirectory($dir, 0755, true);
            } catch (\Throwable $e) {
                Log::error("JsonFileLockStore: failed making dir {$dir}: ".$e->getMessage());
                return false;
            }
        }

        $modeCreate = ! File::exists($path);
        $fh = @fopen($path, $modeCreate ? 'c+' : 'r+');
        if (! $fh) {
            Log::error("JsonFileLockStore: failed to open file {$path}");
            return false;
        }

        $attempt = 0;
        while ($attempt++ < max(1, $retries)) {
            if (@flock($fh, LOCK_EX)) {
                try {
                    rewind($fh);
                    $raw = stream_get_contents($fh);
                    $current = null;
                    if ($raw !== false && trim($raw) !== '') {
                        $decoded = json_decode($raw, true);
                        $current = is_array($decoded) ? $decoded : null;
                    }

                    $new = $updater($current);

                    if (! is_array($new) && ! is_object($new)) {
                        throw new \RuntimeException('JsonFileLockStore updater must return array|object');
                    }

                    $json = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    if ($json === false) {
                        throw new \RuntimeException('JsonFileLockStore json_encode failed: '.json_last_error_msg());
                    }

                    rewind($fh);
                    if (! @ftruncate($fh, 0)) {
                        throw new \RuntimeException("JsonFileLockStore failed to truncate file {$path}");
                    }
                    $bytes = fwrite($fh, $json);
                    fflush($fh);

                    @chmod($path, 0644);

                    flock($fh, LOCK_UN);
                    fclose($fh);

                    return ($bytes !== false && $bytes >= 0);
                } catch (\Throwable $e) {
                    @flock($fh, LOCK_UN);
                    fclose($fh);
                    Log::error("JsonFileLockStore: update failed for {$path}: " . $e->getMessage());
                    return false;
                }
            } else {
                usleep(50000);
            }
        }

        try { fclose($fh); } catch (\Throwable $_) {}
        Log::error("JsonFileLockStore: lock acquisition failed for {$path}");
        return false;
    }

    public function read(string $path)
    {
        if (! File::exists($path)) return null;
        try {
            $raw = File::get($path);
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::error("JsonFileLockStore: read failed for {$path}: ".$e->getMessage());
            return null;
        }
    }
}

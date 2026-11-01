<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

#[Signature('cache:sweep')]
#[Description('Delete expired cache files, which the file cache only removes if the same key is read again')]
class SweepCache extends Command
{
    // Every cache file starts with its expiry time as a ten-digit timestamp.
    private const EXPIRY_LENGTH = 10;

    public function handle(): int
    {
        $root = (string) config('cache.stores.file.path');

        if (! File::isDirectory($root)) {
            return self::SUCCESS;
        }

        $swept = collect(File::allFiles($root))->filter($this->isExpired(...))->each(
            fn (SplFileInfo $file) => File::delete($file->getPathname()),
        )->count();

        $this->removeEmptyFolders($root);

        $this->components->info("Removed {$swept} expired cache files.");

        return self::SUCCESS;
    }

    private function isExpired(SplFileInfo $file): bool
    {
        $expiry = file_get_contents($file->getPathname(), length: self::EXPIRY_LENGTH);

        return $expiry !== false && ctype_digit($expiry) && now()->timestamp >= (int) $expiry;
    }

    private function removeEmptyFolders(string $folder): void
    {
        foreach (File::directories($folder) as $child) {
            $this->removeEmptyFolders($child);

            if (File::isEmptyDirectory($child, ignoreDotFiles: false)) {
                File::deleteDirectory($child);
            }
        }
    }
}

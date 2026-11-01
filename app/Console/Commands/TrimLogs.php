<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

#[Signature('logs:trim')]
#[Description('Keep the log folder under its size limit, because rotating by day cannot stop one bad day filling the disk')]
class TrimLogs extends Command
{
    // During a database outage every failed request can log about 25 KB.
    private const LIMIT_BYTES = 200 * 1024 * 1024;

    public function handle(): int
    {
        $logs = collect(File::glob(storage_path('logs/*.log')))
            ->map(fn (string $path) => new SplFileInfo($path))
            ->sortByDesc(fn (SplFileInfo $file) => $file->getMTime())
            ->values();

        $kept = 0;

        foreach ($logs as $position => $file) {
            $kept += $file->getSize();

            if ($kept <= self::LIMIT_BYTES) {
                continue;
            }

            // Today's log is still being written, so it is cut short rather than deleted.
            if ($position === 0) {
                File::put($file->getPathname(), "Log emptied by logs:trim because it passed the size limit.\n");
                $kept = 0;
            } else {
                File::delete($file->getPathname());
            }
        }

        return self::SUCCESS;
    }
}

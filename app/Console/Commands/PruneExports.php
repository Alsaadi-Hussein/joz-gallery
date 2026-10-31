<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('exports:prune')]
#[Description('Delete old Excel exports, which hold customer names and phone numbers')]
class PruneExports extends Command
{
    private const KEEP_DAYS = 30;

    public function handle(): int
    {
        Export::query()
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->each(function (Export $export) {
                $export->deleteFileDirectory();
                $export->delete();
            });

        return self::SUCCESS;
    }
}

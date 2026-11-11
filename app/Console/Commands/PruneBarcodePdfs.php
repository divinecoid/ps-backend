<?php

namespace App\Console\Commands;

use App\Services\BarcodePdfService;
use Illuminate\Console\Command;

class PruneBarcodePdfs extends Command
{
    protected $signature = 'barcode:prune';
    protected $description = 'Delete pre-generated barcode PDFs older than the retention period (BARCODE_PDF_RETENTION_DAYS, default 30)';

    public function handle(BarcodePdfService $service): int
    {
        $removed = $service->prune();
        $this->info("Removed {$removed} expired barcode PDF(s).");

        return self::SUCCESS;
    }
}

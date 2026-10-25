<?php

namespace App\Jobs;

use App\Models\Transactions\SettlementImport;
use App\Services\SettlementReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSettlementImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public string $importId)
    {
    }

    public function handle(SettlementReconciliationService $service): void
    {
        $import = SettlementImport::find($this->importId);
        if ($import && $import->status === 'queued') {
            $service->process($import);
        }
    }
}

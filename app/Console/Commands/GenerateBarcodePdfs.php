<?php

namespace App\Console\Commands;

use App\Jobs\GenerateBarcodePdfJob;
use App\Models\MasterData\Product;
use App\Models\Transactions\Request;
use App\Services\BarcodePdfService;
use Illuminate\Console\Command;

class GenerateBarcodePdfs extends Command
{
    protected $signature = 'barcode:generate
        {type=batch : batch (stok lama), request (request CMT) atau all}
        {--id=* : Hanya ID batch/request ini (boleh diulang)}
        {--sync : Generate langsung di proses ini, tanpa queue}';

    protected $description = 'Pre-generate PDF barcode (lusinan & satuan) untuk batch stok lama / request CMT yang sudah ada';

    public function handle(BarcodePdfService $service): int
    {
        $type = $this->argument('type');
        if (!in_array($type, ['batch', 'request', 'all'], true)) {
            $this->error("Tipe tidak dikenal '{$type}'. Pilih: batch, request, all.");
            return self::FAILURE;
        }

        $ids = array_filter((array) $this->option('id'));
        $targets = [];

        if ($type !== 'request') {
            $batches = $ids ?: Product::whereNotNull('stock_batch_id')->distinct()->pluck('stock_batch_id')->all();
            foreach ($batches as $id) {
                $targets[] = [BarcodePdfService::TYPE_BATCH, (string) $id];
            }
        }
        if ($type !== 'batch') {
            $requests = $ids ?: Request::pluck('id')->all();
            foreach ($requests as $id) {
                $targets[] = [BarcodePdfService::TYPE_REQUEST, (string) $id];
            }
        }

        if (!$targets) {
            $this->warn('Tidak ada yang perlu di-generate.');
            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');
        $bar = $this->output->createProgressBar(count($targets));
        $failed = 0;

        foreach ($targets as [$targetType, $id]) {
            if ($sync) {
                try {
                    $service->generateAll($targetType, $id);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("{$targetType} {$id}: " . $e->getMessage());
                }
            } else {
                GenerateBarcodePdfJob::dispatch($targetType, $id);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(($sync ? 'Selesai generate ' : 'Di-queue ') . (count($targets) - $failed) . ' item'
            . ($sync ? '' : ' (diproses oleh queue worker)') . ($failed ? ", {$failed} gagal." : '.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

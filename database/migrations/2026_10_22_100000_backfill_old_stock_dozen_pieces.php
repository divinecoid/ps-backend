<?php

use App\Models\MasterData\Product;
use App\Services\LegacyStockService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Old-stock dozen batches entered before piece barcodes were registered
    // per dozen only hold the D rows; give each dozen its 12 P rows.
    public function up(): void
    {
        $service = app(LegacyStockService::class);

        Product::whereNotNull('stock_batch_id')
            ->whereNull('rack_id')
            ->where('barcode', 'like', '%|D|%')
            ->distinct()
            ->pluck('stock_batch_id')
            ->each(fn($batchId) => $service->backfillDozenPieces($batchId));
    }

    public function down(): void
    {
        // Data backfill; nothing to undo.
    }
};

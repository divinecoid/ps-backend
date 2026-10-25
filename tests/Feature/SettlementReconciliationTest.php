<?php

namespace Tests\Feature;

use App\Models\Transactions\BankTransaction;
use App\Models\Transactions\SettlementEntry;
use App\Models\Transactions\SettlementImport;
use App\Services\SettlementParser;
use App\Services\SettlementReconciliationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementReconciliationTest extends TestCase
{
    private string $storeId;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-25 10:00:00');
        Storage::fake('local');

        // Only the tables this feature touches; the full migration set is MySQL-specific.
        Schema::create('mdx_marketplaces', function ($t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->softDeletes();
        });
        Schema::create('mdx_online_stores', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('marketplace_id')->nullable();
            $t->string('store_name');
            $t->softDeletes();
        });
        (include database_path('migrations/2026_10_25_100000_create_settlement_reconciliation_tables.php'))->up();

        $mp = (string) Str::uuid();
        $this->storeId = (string) Str::uuid();
        \DB::table('mdx_marketplaces')->insert(['id' => $mp, 'name' => 'Shopee']);
        \DB::table('mdx_online_stores')->insert(['id' => $this->storeId, 'marketplace_id' => $mp, 'store_name' => 'Shopee A']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function runImport(string $type, string $csv): SettlementImport
    {
        $import = SettlementImport::create([
            'online_store_id' => $type === 'settlement' ? $this->storeId : null,
            'file_type' => $type,
            'file_name' => "$type.csv",
            'file_hash' => hash('sha256', $csv . Str::random()),
            'status' => 'queued',
        ]);
        $path = "settlement-imports/{$import->id}.csv";
        Storage::disk('local')->put($path, $csv);
        $import->update(['file_path' => $path]);
        app(SettlementReconciliationService::class)->process($import);

        return $import->fresh();
    }

    private function settlementCsv(): string
    {
        return <<<CSV
Laporan Penghasilan,,,,,,,,
Periode,2026-10-01 s/d 2026-10-31,,,,,,,
No. Pesanan,No. Referensi,Tanggal Dana Dilepaskan,Harga Asli Produk,Voucher dari Penjual,Biaya Komisi,Biaya Layanan,Biaya Program Event,Total Penghasilan
ORD1,PO-A,2026-10-10,1000000,-50000,-80000,-20000,-30000,820000
ORD2,PO-A,2026-10-10,1000000,-50000,-80000,-20000,-30000,750000
ORD3,PO-A,2026-10-10,500000,0,-40000,0,-100000,360000
ORD4,,,300000,0,-24000,0,0,
CSV;
    }

    public function test_statuses_and_totals_follow_the_rules(): void
    {
        $import = $this->runImport('settlement', $this->settlementCsv());

        $this->assertSame('done', $import->status);
        $this->assertSame(4, $import->imported_count);

        $status = SettlementEntry::pluck('status', 'order_sn');
        $this->assertSame('matched', $status['ORD1']);
        $this->assertSame('discrepancy', $status['ORD2']);
        $this->assertSame('unusual_fee', $status['ORD3']); // event fee 20% of gross
        $this->assertSame('pending', $status['ORD4']);

        $ord2 = SettlementEntry::where('order_sn', 'ORD2')->first();
        $this->assertEquals(-70000, $ord2->difference);
        $this->assertEquals(820000, $ord2->expected_net);

        $summary = app(SettlementReconciliationService::class)->summary();
        $this->assertEquals(2800000, $summary['kpi']['gross_order_value']);
        $this->assertEquals(-70000, $summary['kpi']['unresolved_difference']);
        $this->assertSame(1, $summary['status_counts']['discrepancy']);
        $this->assertSame(1, $summary['status_counts']['pending']);
    }

    public function test_reimporting_rows_does_not_duplicate(): void
    {
        $this->runImport('settlement', $this->settlementCsv());
        $second = $this->runImport('settlement', $this->settlementCsv());

        $this->assertSame(0, $second->imported_count);
        $this->assertSame(4, $second->duplicate_count);
        $this->assertSame(4, SettlementEntry::count());
    }

    public function test_bank_credit_is_linked_by_payout_reference(): void
    {
        $this->runImport('settlement', $this->settlementCsv());
        $this->runImport('bank', "Tanggal,Keterangan,Debit,Kredit\n2026-10-11,TRSF SHOPEE PO-A,0,1930000\n2026-10-11,BIAYA ADM,5000,0\n");

        $this->assertSame(1, BankTransaction::count()); // debit line skipped
        $payout = app(SettlementReconciliationService::class)->payouts()->first();
        $this->assertSame('bank_matched', $payout['status']);
        $this->assertEquals(1930000, $payout['bank_total']);
    }

    public function test_ambiguous_amount_matches_are_left_for_review(): void
    {
        $csv = "No. Pesanan,Tanggal Dana Dilepaskan,Harga Asli Produk,Total Penghasilan\nX1,2026-10-10,1000000,1000000\n";
        $this->runImport('settlement', $csv);
        $this->runImport('bank', "Tanggal,Keterangan,Kredit\n2026-10-10,TRF A,1000000\n2026-10-11,TRF B,1000000\n");

        $payout = app(SettlementReconciliationService::class)->payouts()->first();
        $this->assertSame('ambiguous', $payout['status']);
        $this->assertSame(0, BankTransaction::whereNotNull('matched_payout_key')->count());
    }

    public function test_missing_required_columns_fail_the_import(): void
    {
        $import = $this->runImport('settlement', "Foo,Bar\n1,2\n");
        $this->assertSame('failed', $import->status);
    }

    public function test_amount_parsing_handles_indonesian_and_english_formats(): void
    {
        $this->assertSame(1234567.5, SettlementParser::amount('Rp 1.234.567,50'));
        $this->assertSame(-1234.5, SettlementParser::amount('-1,234.50'));
        $this->assertSame(-1000.0, SettlementParser::amount('(1.000)'));
        $this->assertSame(250000.0, SettlementParser::amount('250.000'));
    }
}

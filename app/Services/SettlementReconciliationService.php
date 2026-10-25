<?php

namespace App\Services;

use App\Imports\RawSheetImport;
use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\BankTransaction;
use App\Models\Transactions\SettlementEntry;
use App\Models\Transactions\SettlementImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class SettlementReconciliationService
{
    private const CHUNK = 500;

    /** Parse the stored file of an import and persist entries / bank rows. */
    public function process(SettlementImport $import): void
    {
        $import->update(['status' => 'processing']);

        try {
            $path = Storage::disk('local')->path($import->file_path);
            $rows = $this->readRows($path);

            if ($import->file_type === 'bank') {
                $this->processBank($import, $rows);
                $this->reconcileBank();
            } else {
                $this->processSettlement($import, $rows);
                $this->reconcileBank();
            }
        } catch (\Throwable $e) {
            $import->update([
                'status' => 'failed',
                'errors' => [['row' => null, 'order_sn' => null, 'message' => $e->getMessage()]],
                'processed_at' => now(),
            ]);
            report($e);
        }
    }

    private function readRows(string $path): array
    {
        $delimiter = ',';
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv') {
            $first = (string) fgets(fopen($path, 'r'));
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            if (substr_count($first, "\t") > substr_count($first, $delimiter)) {
                $delimiter = "\t";
            }
        }

        $sheets = Excel::toArray(new RawSheetImport($delimiter), $path);

        // Use the sheet with the most rows (reports sometimes carry a tiny summary sheet).
        usort($sheets, fn($a, $b) => count($b) <=> count($a));

        return $sheets[0] ?? [];
    }

    private function processSettlement(SettlementImport $import, array $rows): void
    {
        $parsed = SettlementParser::parseSettlement($rows);

        if ($parsed['fatal']) {
            $import->update([
                'status' => 'failed',
                'errors' => [['row' => null, 'order_sn' => null, 'message' => $parsed['fatal']]],
                'processed_at' => now(),
            ]);
            return;
        }

        $store = OnlineStore::find($import->online_store_id);
        $imported = 0;
        $now = now();

        foreach (array_chunk($parsed['entries'], self::CHUNK) as $chunk) {
            $payload = [];
            foreach ($chunk as $e) {
                $fp = SettlementParser::fingerprint($import->online_store_id, $e);
                $payload[$fp] = [
                    'id' => (string) Str::uuid(),
                    'import_id' => $import->id,
                    'online_store_id' => $import->online_store_id,
                    'marketplace_id' => $store?->marketplace_id,
                    'source_row_number' => $e['source_row_number'],
                    'fingerprint' => $fp,
                    'order_sn' => $e['order_sn'],
                    'payout_ref' => $e['payout_ref'],
                    'order_date' => $e['order_date'],
                    'settlement_date' => $e['settlement_date'],
                    'gross_amount' => $e['gross_amount'],
                    'seller_discount' => $e['seller_discount'],
                    'commission_fee' => $e['commission_fee'],
                    'service_fee' => $e['service_fee'],
                    'event_fee' => $e['event_fee'],
                    'shipping_fee' => $e['shipping_fee'],
                    'refund_amount' => $e['refund_amount'],
                    'other_fee' => $e['other_fee'],
                    'adjustment_amount' => $e['adjustment_amount'],
                    'reported_net' => $e['reported_net'],
                    'expected_net' => $e['expected_net'],
                    'difference' => $e['difference'],
                    'status' => $e['status'],
                    'flags' => json_encode($e['flags']),
                    'fee_breakdown' => json_encode($e['fee_breakdown']),
                    'raw_payload' => json_encode($e['raw_payload']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $existing = SettlementEntry::where('online_store_id', $import->online_store_id)
                ->whereIn('fingerprint', array_keys($payload))
                ->pluck('fingerprint')->all();
            $new = array_diff_key($payload, array_flip($existing));

            if ($new) {
                SettlementEntry::insert(array_values($new));
                $imported += count($new);
            }
        }

        $import->update([
            'status' => 'done',
            'row_count' => $parsed['row_count'],
            'imported_count' => $imported,
            'duplicate_count' => count($parsed['entries']) - $imported,
            'error_count' => count($parsed['errors']),
            'errors' => array_slice($parsed['errors'], 0, 200),
            'unmapped_columns' => $parsed['unmapped'],
            'processed_at' => now(),
        ]);
    }

    private function processBank(SettlementImport $import, array $rows): void
    {
        $parsed = SettlementParser::parseBank($rows);

        if ($parsed['fatal']) {
            $import->update([
                'status' => 'failed',
                'errors' => [['row' => null, 'order_sn' => null, 'message' => $parsed['fatal']]],
                'processed_at' => now(),
            ]);
            return;
        }

        $imported = 0;
        $now = now();

        foreach (array_chunk($parsed['rows'], self::CHUNK) as $chunk) {
            $payload = [];
            foreach ($chunk as $r) {
                // Identical lines on the same day are legitimate; the import-level file hash
                // prevents re-importing the same statement, so the row number joins the key.
                $fp = hash('sha256', implode('|', [$import->file_hash, $r['source_row_number']]));
                $payload[$fp] = $r + [
                    'id' => (string) Str::uuid(),
                    'import_id' => $import->id,
                    'fingerprint' => $fp,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $existing = BankTransaction::whereIn('fingerprint', array_keys($payload))->pluck('fingerprint')->all();
            $new = array_diff_key($payload, array_flip($existing));
            if ($new) {
                BankTransaction::insert(array_values($new));
                $imported += count($new);
            }
        }

        $import->update([
            'status' => 'done',
            'row_count' => $parsed['row_count'],
            'imported_count' => $imported,
            'duplicate_count' => count($parsed['rows']) - $imported,
            'error_count' => count($parsed['errors']),
            'errors' => array_slice($parsed['errors'], 0, 200),
            'processed_at' => now(),
        ]);
    }

    /**
     * Payouts = settled entries grouped per store + payout reference (or settlement date
     * when the report carries no reference).
     *
     * @return \Illuminate\Support\Collection<int,array>
     */
    public function payouts(array $filters = [])
    {
        $rows = $this->entryQuery($filters)
            ->whereNotNull('reported_net')
            ->whereNotNull('settlement_date')
            ->selectRaw("online_store_id, payout_ref, settlement_date, COUNT(*) as order_count, SUM(reported_net) as total_net")
            ->groupBy('online_store_id', 'payout_ref', 'settlement_date')
            ->get();

        // Entries sharing a reference but different dates belong to one payout.
        $grouped = $rows->groupBy(fn($r) => $r->online_store_id . '|' . ($r->payout_ref ?: 'D' . Carbon::parse($r->settlement_date)->format('Y-m-d')));

        $stores = OnlineStore::with('marketplace')->whereIn('id', $rows->pluck('online_store_id')->unique())->get()->keyBy('id');
        $matchedByKey = BankTransaction::whereNotNull('matched_payout_key')
            ->get()->groupBy(fn($b) => $b->matched_online_store_id . '|' . $b->matched_payout_key);
        $unmatchedBank = BankTransaction::whereNull('matched_payout_key')->get();

        $tolerance = config('reconciliation.bank_tolerance');
        $window = config('reconciliation.bank_date_window_days');
        $today = Carbon::today();

        return $grouped->map(function ($g, $key) use ($stores, $matchedByKey, $unmatchedBank, $tolerance, $window, $today) {
            $first = $g->first();
            $total = round($g->sum('total_net'), 2);
            $date = Carbon::parse($g->max('settlement_date'));
            $payoutKey = $first->payout_ref ?: 'D' . Carbon::parse($first->settlement_date)->format('Y-m-d');
            $bankRows = $matchedByKey->get($first->online_store_id . '|' . $payoutKey, collect());
            $bankTotal = round($bankRows->sum('credit_amount'), 2);

            if ($bankRows->isNotEmpty()) {
                $status = abs($bankTotal - $total) <= $tolerance ? 'bank_matched' : 'bank_variance';
            } else {
                $candidates = $unmatchedBank->filter(
                    fn($b) => abs((float) $b->credit_amount - $total) <= $tolerance
                        && abs(Carbon::parse($b->transaction_date)->diffInDays($date, false)) <= $window
                )->count();
                $status = $candidates > 1 ? 'ambiguous'
                    : ($date->isAfter($today->copy()->subDays($window)) ? 'pending' : 'awaiting_bank');
            }

            $store = $stores->get($first->online_store_id);

            return [
                'payout_key' => $payoutKey,
                'payout_ref' => $first->payout_ref,
                'online_store_id' => $first->online_store_id,
                'store_name' => $store?->store_name,
                'marketplace_name' => $store?->marketplace?->name,
                'settlement_date' => $date->format('Y-m-d'),
                'order_count' => (int) $g->sum('order_count'),
                'total_net' => $total,
                'bank_total' => $bankTotal,
                'bank_difference' => $bankRows->isNotEmpty() ? round($bankTotal - $total, 2) : null,
                'status' => $status,
            ];
        })->sortByDesc('settlement_date')->values();
    }

    /**
     * Link bank credits to payouts. Reference in the bank text wins; otherwise a credit
     * is only linked when amount + date window leave exactly one candidate on both sides.
     * Anything ambiguous stays unlinked for human review.
     */
    public function reconcileBank(): int
    {
        $payouts = $this->payouts();
        $bank = BankTransaction::whereNull('matched_payout_key')->get();
        if ($payouts->isEmpty() || $bank->isEmpty()) {
            return 0;
        }

        $already = BankTransaction::whereNotNull('matched_payout_key')
            ->get()->groupBy(fn($b) => $b->matched_online_store_id . '|' . $b->matched_payout_key);

        $tolerance = config('reconciliation.bank_tolerance');
        $window = config('reconciliation.bank_date_window_days');
        $linked = 0;

        // 1) reference match
        foreach ($payouts as $p) {
            if (!$p['payout_ref'] || $already->has($p['online_store_id'] . '|' . $p['payout_key'])) {
                continue;
            }
            foreach ($bank as $b) {
                if ($b->matched_payout_key) {
                    continue;
                }
                $haystack = strtolower(($b->description ?? '') . ' ' . ($b->reference ?? ''));
                if (str_contains($haystack, strtolower($p['payout_ref']))) {
                    $b->update(['matched_online_store_id' => $p['online_store_id'], 'matched_payout_key' => $p['payout_key'], 'match_method' => 'reference']);
                    $linked++;
                }
            }
        }

        // 2) amount + date, only when unambiguous in both directions
        $free = $bank->filter(fn($b) => !$b->matched_payout_key);
        $open = $payouts->filter(fn($p) => !$already->has($p['online_store_id'] . '|' . $p['payout_key'])
            && !BankTransaction::where('matched_online_store_id', $p['online_store_id'])->where('matched_payout_key', $p['payout_key'])->exists());

        $near = fn($b, $p) => abs((float) $b->credit_amount - $p['total_net']) <= $tolerance
            && abs(Carbon::parse($b->transaction_date)->diffInDays(Carbon::parse($p['settlement_date']), false)) <= $window;

        foreach ($open as $p) {
            $cands = $free->filter(fn($b) => !$b->matched_payout_key && $near($b, $p));
            if ($cands->count() !== 1) {
                continue;
            }
            $b = $cands->first();
            if ($open->filter(fn($q) => $near($b, $q))->count() !== 1) {
                continue;
            }
            $b->update(['matched_online_store_id' => $p['online_store_id'], 'matched_payout_key' => $p['payout_key'], 'match_method' => 'amount_date']);
            $linked++;
        }

        return $linked;
    }

    /** Dashboard numbers. */
    public function summary(array $filters = []): array
    {
        $base = fn() => $this->entryQuery($filters);

        $t = $base()->selectRaw('
            COUNT(*) as entry_count,
            COALESCE(SUM(gross_amount),0) as gross, COALESCE(SUM(seller_discount),0) as seller_discount,
            COALESCE(SUM(commission_fee),0) as commission, COALESCE(SUM(service_fee),0) as service,
            COALESCE(SUM(event_fee),0) as event, COALESCE(SUM(shipping_fee),0) as shipping,
            COALESCE(SUM(refund_amount),0) as refund, COALESCE(SUM(other_fee),0) as other,
            COALESCE(SUM(adjustment_amount),0) as adjustment,
            COALESCE(SUM(reported_net),0) as net_settlement, COALESCE(SUM(expected_net),0) as expected_net
        ')->first();

        $deductions = (float) ($t->seller_discount + $t->commission + $t->service + $t->event + $t->shipping + $t->refund + $t->other);

        $byStatus = $base()->selectRaw('status, COUNT(*) as c, COALESCE(SUM(expected_net),0) as expected, COALESCE(SUM(difference),0) as diff,
            COALESCE(SUM(event_fee+commission_fee+service_fee+shipping_fee+other_fee),0) as fees')
            ->groupBy('status')->get()->keyBy('status');

        $count = fn($s) => (int) ($byStatus[$s]->c ?? 0);
        $settleable = $count('matched') + $count('discrepancy') + $count('unusual_fee');

        $byStore = $base()->selectRaw('online_store_id, COUNT(*) as entry_count,
            SUM(gross_amount) as gross, SUM(seller_discount+commission_fee+service_fee+event_fee+shipping_fee+refund_amount+other_fee) as deductions,
            SUM(event_fee) as event, COALESCE(SUM(reported_net),0) as net_settlement,
            SUM(CASE WHEN status = \'discrepancy\' THEN 1 ELSE 0 END) as discrepancy_count,
            SUM(CASE WHEN status = \'unusual_fee\' THEN 1 ELSE 0 END) as unusual_count')
            ->groupBy('online_store_id')->get();
        $stores = OnlineStore::with('marketplace')->whereIn('id', $byStore->pluck('online_store_id'))->get()->keyBy('id');

        $payouts = $this->payouts($filters);
        $payoutStatus = $payouts->countBy('status');

        return [
            'kpi' => [
                'gross_order_value' => round($t->gross, 2),
                'total_deductions' => round($deductions, 2),
                'deduction_rate_pct' => $t->gross > 0 ? round($deductions / $t->gross * 100, 2) : null,
                'net_settlement' => round($t->net_settlement, 2),
                'expected_net' => round($t->expected_net, 2),
                'unresolved_difference' => round((float) ($byStatus['discrepancy']->diff ?? 0), 2),
                'pending_amount' => round((float) ($byStatus['pending']->expected ?? 0), 2),
                'unusual_fee_exposure' => round((float) ($byStatus['unusual_fee']->fees ?? 0), 2),
                'match_rate_pct' => $settleable > 0 ? round($count('matched') / $settleable * 100, 2) : null,
                'bank_received' => round($payouts->whereIn('status', ['bank_matched', 'bank_variance'])->sum('bank_total'), 2),
                'payout_total' => round($payouts->sum('total_net'), 2),
                'entry_count' => (int) $t->entry_count,
            ],
            'fee_breakdown' => [
                ['key' => 'commission', 'label' => 'Komisi', 'amount' => round($t->commission, 2)],
                ['key' => 'service', 'label' => 'Biaya Layanan/Admin', 'amount' => round($t->service, 2)],
                ['key' => 'event', 'label' => 'Program/Event/Promo', 'amount' => round($t->event, 2)],
                ['key' => 'shipping', 'label' => 'Ongkir', 'amount' => round($t->shipping, 2)],
                ['key' => 'seller_discount', 'label' => 'Diskon/Voucher Penjual', 'amount' => round($t->seller_discount, 2)],
                ['key' => 'refund', 'label' => 'Refund/Retur', 'amount' => round($t->refund, 2)],
                ['key' => 'other', 'label' => 'Biaya Lain', 'amount' => round($t->other, 2)],
            ],
            'status_counts' => [
                'matched' => $count('matched'),
                'pending' => $count('pending'),
                'discrepancy' => $count('discrepancy'),
                'unusual_fee' => $count('unusual_fee'),
            ],
            'payout_status_counts' => $payoutStatus,
            'by_store' => $byStore->map(fn($r) => [
                'online_store_id' => $r->online_store_id,
                'store_name' => $stores[$r->online_store_id]->store_name ?? '-',
                'marketplace_name' => $stores[$r->online_store_id]->marketplace->name ?? '-',
                'entry_count' => (int) $r->entry_count,
                'gross' => round($r->gross, 2),
                'deductions' => round($r->deductions, 2),
                'deduction_rate_pct' => $r->gross > 0 ? round($r->deductions / $r->gross * 100, 2) : null,
                'event' => round($r->event, 2),
                'net_settlement' => round($r->net_settlement, 2),
                'discrepancy_count' => (int) $r->discrepancy_count,
                'unusual_count' => (int) $r->unusual_count,
            ])->sortByDesc('gross')->values(),
        ];
    }

    /** Entries filtered by store / marketplace / settlement period. */
    public function entryQuery(array $filters)
    {
        $dateExpr = 'COALESCE(settlement_date, order_date)';

        return SettlementEntry::query()
            ->when($filters['online_store_id'] ?? null, fn($q, $v) => $q->where('online_store_id', $v))
            ->when($filters['marketplace_id'] ?? null, fn($q, $v) => $q->where('marketplace_id', $v))
            ->when($filters['date_from'] ?? null, fn($q, $v) => $q->whereRaw("$dateExpr >= ?", [$v]))
            ->when($filters['date_to'] ?? null, fn($q, $v) => $q->whereRaw("$dateExpr <= ?", [$v]))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null, fn($q, $v) => $q->where('order_sn', 'like', '%' . $v . '%'));
    }
}

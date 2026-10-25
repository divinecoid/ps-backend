<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Pure (no DB) parsing + rule evaluation for marketplace settlement and bank files.
 *
 * Column names differ per marketplace and change over time, so columns are
 * recognised by alias (identifiers/dates/gross/net) or by keyword (fees). Fee
 * columns we cannot classify are never guessed: they are reported back as
 * "unmapped" so finance can review them, and any resulting mismatch shows up
 * as a discrepancy instead of being silently absorbed.
 */
class SettlementParser
{
    public const FEE_CATEGORIES = ['commission', 'service', 'event', 'shipping', 'other'];

    private const CORE_ALIASES = [
        'order_sn' => ['no_pesanan', 'nomor_pesanan', 'order_id', 'order_sn', 'order_no', 'order_adjustment_id', 'order_id_adjustment_id'],
        'payout_ref' => ['payout_id', 'payout_ref', 'no_referensi', 'nomor_referensi', 'id_pencairan', 'no_pencairan', 'settlement_id', 'statement_id', 'payment_id'],
        'order_date' => ['tanggal_pesanan_dibuat', 'waktu_pesanan_dibuat', 'tanggal_pesanan', 'order_created_time', 'order_created_date', 'order_date', 'created_time'],
        'settlement_date' => ['tanggal_dana_dilepaskan', 'tanggal_pelepasan_dana', 'tanggal_pencairan', 'tanggal_penyelesaian', 'settlement_date', 'order_settled_time', 'settled_time', 'release_date', 'payment_date'],
        'gross_amount' => ['harga_asli_produk', 'total_harga_produk', 'nilai_pesanan', 'gross_amount', 'gross_sales', 'total_revenue', 'subtotal_before_discount', 'order_amount', 'harga_produk'],
        'reported_net' => ['total_penghasilan', 'jumlah_dana_dilepaskan', 'total_pencairan', 'dana_diterima', 'net_settlement', 'net_amount', 'total_settlement_amount', 'settlement_amount', 'reported_net'],
    ];

    private const BANK_ALIASES = [
        'date' => ['tanggal', 'tgl', 'date', 'transaction_date', 'posting_date', 'tanggal_transaksi'],
        'description' => ['keterangan', 'deskripsi', 'description', 'berita', 'uraian', 'remark', 'remarks', 'transaction_description'],
        'reference' => ['referensi', 'no_referensi', 'reference', 'ref', 'ref_no'],
        'credit' => ['kredit', 'credit', 'cr', 'mutasi_kredit', 'credit_amount', 'uang_masuk', 'jumlah_kredit'],
        'amount' => ['jumlah', 'amount', 'nominal', 'mutasi'],
        'debit' => ['debit', 'db', 'mutasi_debit', 'debit_amount', 'uang_keluar'],
    ];

    public static function norm(string $header): string
    {
        $h = strtolower(trim($header));
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);

        return trim($h, '_');
    }

    /**
     * Locate the header row (first 25 rows) by searching for a recognisable cell.
     *
     * @return array{0:int,1:array<int,string>}|null  [rowIndex, normalised headers]
     */
    public static function findHeader(array $rows, array $mustContainAnyOf): ?array
    {
        foreach (array_slice($rows, 0, 25, true) as $i => $row) {
            $norm = array_map(fn($c) => is_scalar($c) ? self::norm((string) $c) : '', $row);
            if (array_intersect($norm, $mustContainAnyOf)) {
                return [$i, $norm];
            }
        }

        return null;
    }

    /**
     * Classify one normalised header into a fee category, or null if it is not a fee column.
     * Returns [category, mode]; mode "abs" = always a deduction, "signed" = keep sign (credit/debit).
     */
    public static function classifyColumn(string $h): ?array
    {
        if ($h === '' || str_starts_with($h, 'total_') || str_starts_with($h, 'subtotal')) {
            return null;
        }

        $has = fn(string $re) => (bool) preg_match($re, $h);

        if ($has('/penyesuaian|adjustment|kompensasi|compensation|subsidi|subsidy/')) {
            return ['adjustment', 'signed'];
        }
        if ($has('/refund|retur|return|pengembalian_dana/')) {
            return ['refund', 'abs'];
        }
        if ($has('/(penjual|seller).*(diskon|voucher|discount|coupon)|(diskon|voucher|discount|coupon).*(penjual|seller)/')) {
            return ['seller_discount', 'abs'];
        }
        // Shipping paid by the buyer is income for the seller, not a deduction.
        if ($has('/kirim|ongkir|shipping|logistik|logistic|delivery/')) {
            return $has('/pembeli|buyer|customer/') ? ['adjustment', 'signed'] : ['shipping', 'abs'];
        }
        if ($has('/event|campaign|kampanye|program|promo|flash|hemat|xtra|cashback|(^|_)ams(_|$)|(^|_)live(_|$)|affiliate/')) {
            return ['event', 'abs'];
        }
        if ($has('/komisi|commission/')) {
            return ['commission', 'abs'];
        }
        if ($has('/layanan|service|administrasi|(^|_)admin(_|$)|transaction_fee|biaya_proses|payment_fee|processing/')) {
            return ['service', 'abs'];
        }
        if ($has('/biaya|(^|_)fee(_|$)|fees|potongan|deduction|charge|penalti|penalty/')) {
            return ['other', 'abs'];
        }

        return null;
    }

    /**
     * Parse a raw sheet (array of rows) into settlement entries.
     *
     * @return array{entries:array,errors:array,unmapped:array,fatal:?string,row_count:int}
     */
    public static function parseSettlement(array $rows): array
    {
        $result = ['entries' => [], 'errors' => [], 'unmapped' => [], 'fatal' => null, 'row_count' => 0];

        $header = self::findHeader($rows, self::CORE_ALIASES['order_sn']);
        if (!$header) {
            $result['fatal'] = 'Kolom nomor pesanan tidak ditemukan (mis. "No. Pesanan" / "Order ID").';
            return $result;
        }

        [$headerIdx, $headers] = $header;

        $core = [];
        $feeCols = [];
        foreach ($headers as $col => $h) {
            if ($h === '') {
                continue;
            }
            $matched = false;
            foreach (self::CORE_ALIASES as $field => $aliases) {
                if (in_array($h, $aliases, true) && !isset($core[$field])) {
                    $core[$field] = $col;
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                continue;
            }
            $class = self::classifyColumn($h);
            if ($class) {
                $feeCols[$col] = ['category' => $class[0], 'mode' => $class[1], 'label' => trim((string) $rows[$headerIdx][$col])];
            } else {
                $result['unmapped'][] = trim((string) $rows[$headerIdx][$col]);
            }
        }

        foreach (['gross_amount' => 'nilai pesanan (mis. "Harga Asli Produk")', 'reported_net' => 'penghasilan bersih (mis. "Total Penghasilan")'] as $field => $label) {
            if (!isset($core[$field])) {
                $result['fatal'] = "Kolom {$label} tidak ditemukan.";
                return $result;
            }
        }

        $result['unmapped'] = array_slice(array_values(array_unique($result['unmapped'])), 0, 100);

        foreach (array_slice($rows, $headerIdx + 1, null, true) as $i => $row) {
            $orderSn = isset($core['order_sn']) ? trim((string) ($row[$core['order_sn']] ?? '')) : '';
            if ($orderSn === '') {
                continue; // blank / footer line
            }
            $result['row_count']++;
            $line = $i + 1;

            try {
                $result['entries'][] = self::buildEntry($row, $core, $feeCols, $headers, $orderSn, $line);
            } catch (\InvalidArgumentException $e) {
                $result['errors'][] = ['row' => $line, 'order_sn' => $orderSn, 'message' => $e->getMessage()];
            }
        }

        return $result;
    }

    private static function buildEntry(array $row, array $core, array $feeCols, array $headers, string $orderSn, int $line): array
    {
        $get = fn(string $f) => isset($core[$f]) ? ($row[$core[$f]] ?? null) : null;

        $gross = self::amount($get('gross_amount'), 'nilai pesanan');
        $netRaw = $get('reported_net');
        $reportedNet = ($netRaw === null || trim((string) $netRaw) === '') ? null : self::amount($netRaw, 'penghasilan bersih');

        $totals = ['seller_discount' => 0.0, 'commission' => 0.0, 'service' => 0.0, 'event' => 0.0,
            'shipping' => 0.0, 'other' => 0.0, 'refund' => 0.0, 'adjustment' => 0.0];
        $breakdown = [];

        foreach ($feeCols as $col => $def) {
            $raw = $row[$col] ?? null;
            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }
            $v = self::amount($raw, $def['label']);
            if ($v == 0.0) {
                continue;
            }
            $value = $def['mode'] === 'abs' ? abs($v) : $v;
            $totals[$def['category']] += $value;
            $breakdown[$def['label']] = ($breakdown[$def['label']] ?? 0) + $value;
        }

        $payout = trim((string) ($get('payout_ref') ?? ''));
        $settlementDate = self::date($get('settlement_date'));

        $entry = [
            'source_row_number' => $line,
            'order_sn' => $orderSn,
            'payout_ref' => $payout !== '' ? $payout : null,
            'order_date' => self::date($get('order_date')),
            'settlement_date' => $settlementDate,
            'gross_amount' => $gross,
            'seller_discount' => $totals['seller_discount'],
            'commission_fee' => $totals['commission'],
            'service_fee' => $totals['service'],
            'event_fee' => $totals['event'],
            'shipping_fee' => $totals['shipping'],
            'refund_amount' => $totals['refund'],
            'other_fee' => $totals['other'],
            'adjustment_amount' => $totals['adjustment'],
            'reported_net' => $reportedNet,
            'fee_breakdown' => $breakdown,
            'raw_payload' => self::rawPayload($row, $headers),
        ];

        return $entry + self::evaluate($entry);
    }

    /**
     * Expected net + status + flags for an entry (rules REC-05/06/10 and unusual fee).
     *
     * @return array{expected_net:float,difference:?float,status:string,flags:array}
     */
    public static function evaluate(array $e, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $expected = $e['gross_amount'] - $e['seller_discount'] - $e['commission_fee'] - $e['service_fee']
            - $e['event_fee'] - $e['shipping_fee'] - $e['refund_amount'] - $e['other_fee'] + $e['adjustment_amount'];
        $expected = round($expected, 2);

        $flags = [];
        $difference = $e['reported_net'] === null ? null : round($e['reported_net'] - $expected, 2);

        $isPending = $e['reported_net'] === null
            || empty($e['settlement_date'])
            || Carbon::parse($e['settlement_date'])->isAfter($today);

        // Unusual-fee checks work on the share of gross.
        if ($e['gross_amount'] > 0) {
            $limits = config('reconciliation.unusual_fee_pct');
            $fees = ['event' => $e['event_fee'], 'commission' => $e['commission_fee'], 'service' => $e['service_fee'],
                'shipping' => $e['shipping_fee'], 'other' => $e['other_fee']];
            foreach ($fees as $cat => $amt) {
                if ($amt / $e['gross_amount'] * 100 > ($limits[$cat] ?? 100)) {
                    $flags[] = "high_{$cat}_fee";
                }
            }
            $totalDeduction = array_sum($fees) + $e['seller_discount'] + $e['refund_amount'];
            if ($totalDeduction / $e['gross_amount'] * 100 > config('reconciliation.unusual_total_deduction_pct')) {
                $flags[] = 'high_total_deduction';
            }
        }
        if ($e['reported_net'] !== null && $e['reported_net'] < 0) {
            $flags[] = 'negative_net';
        }

        if ($isPending) {
            $status = 'pending';
        } elseif (abs($difference) > config('reconciliation.settlement_tolerance')) {
            $status = 'discrepancy';
        } elseif ($flags) {
            $status = 'unusual_fee';
        } else {
            $status = 'matched';
        }

        return ['expected_net' => $expected, 'difference' => $difference, 'status' => $status, 'flags' => $flags];
    }

    /**
     * @return array{rows:array,errors:array,fatal:?string,row_count:int}
     */
    public static function parseBank(array $rows): array
    {
        $result = ['rows' => [], 'errors' => [], 'fatal' => null, 'row_count' => 0];

        $header = self::findHeader($rows, array_merge(self::BANK_ALIASES['credit'], self::BANK_ALIASES['amount']));
        if (!$header) {
            $result['fatal'] = 'Kolom kredit/jumlah tidak ditemukan pada mutasi bank.';
            return $result;
        }
        [$headerIdx, $headers] = $header;

        $col = [];
        foreach (self::BANK_ALIASES as $field => $aliases) {
            foreach ($headers as $i => $h) {
                if (in_array($h, $aliases, true)) {
                    $col[$field] = $i;
                    break;
                }
            }
        }
        if (!isset($col['date'])) {
            $result['fatal'] = 'Kolom tanggal tidak ditemukan pada mutasi bank.';
            return $result;
        }

        foreach (array_slice($rows, $headerIdx + 1, null, true) as $i => $row) {
            $dateRaw = $row[$col['date']] ?? null;
            if ($dateRaw === null || trim((string) $dateRaw) === '') {
                continue;
            }
            $result['row_count']++;
            $line = $i + 1;

            try {
                $credit = null;
                if (isset($col['credit'])) {
                    $credit = self::amount($row[$col['credit']] ?? 0, 'kredit');
                } elseif (isset($col['amount'])) {
                    $isDebit = isset($col['debit']) && self::amount($row[$col['debit']] ?? 0, 'debit') > 0;
                    $credit = $isDebit ? 0.0 : self::amount($row[$col['amount']] ?? 0, 'jumlah');
                }
                if ($credit === null || $credit <= 0) {
                    continue; // debit or empty line, not a payout candidate
                }
                $date = self::date($dateRaw);
                if (!$date) {
                    throw new \InvalidArgumentException("Tanggal tidak valid: {$dateRaw}");
                }
                $result['rows'][] = [
                    'source_row_number' => $line,
                    'transaction_date' => $date,
                    'description' => isset($col['description']) ? mb_substr(trim((string) ($row[$col['description']] ?? '')), 0, 500) : null,
                    'reference' => isset($col['reference']) ? trim((string) ($row[$col['reference']] ?? '')) ?: null : null,
                    'credit_amount' => $credit,
                ];
            } catch (\InvalidArgumentException $e) {
                $result['errors'][] = ['row' => $line, 'order_sn' => null, 'message' => $e->getMessage()];
            }
        }

        return $result;
    }

    /** Parse "Rp 1.234.567,50", "-1,234.50", "(1.000)", numerics. */
    public static function amount(mixed $value, string $label = 'nominal'): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $s = trim((string) $value);
        $negative = str_starts_with($s, '(') && str_ends_with($s, ')');
        $s = preg_replace('/[^0-9.,\-]/', '', $s);
        if ($s === '' || $s === '-') {
            throw new \InvalidArgumentException("Nominal {$label} tidak valid: {$value}");
        }
        if (str_starts_with($s, '-')) {
            $negative = true;
            $s = ltrim($s, '-');
        }

        $dots = substr_count($s, '.');
        $commas = substr_count($s, ',');
        if ($dots && $commas) {
            $decimalSep = strrpos($s, '.') > strrpos($s, ',') ? '.' : ',';
        } elseif ($dots || $commas) {
            $sep = $dots ? '.' : ',';
            $count = max($dots, $commas);
            $after = substr($s, strrpos($s, $sep) + 1);
            $decimalSep = ($count === 1 && strlen($after) !== 3) ? $sep : null;
        } else {
            $decimalSep = null;
        }

        if ($decimalSep) {
            $pos = strrpos($s, $decimalSep);
            $int = preg_replace('/[.,]/', '', substr($s, 0, $pos));
            $s = $int . '.' . substr($s, $pos + 1);
        } else {
            $s = preg_replace('/[.,]/', '', $s);
        }

        if (!is_numeric($s)) {
            throw new \InvalidArgumentException("Nominal {$label} tidak valid: {$value}");
        }

        $n = round((float) $s, 2);

        return $negative ? -$n : $n;
    }

    /** Excel serials and common Indonesian / ISO strings to Y-m-d. */
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
        }

        $s = trim((string) $value);
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y H:i', 'd-m-Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'Y/m/d'] as $fmt) {
            try {
                $d = Carbon::createFromFormat('!' . $fmt, $s);
            } catch (\Throwable) {
                continue;
            }
            if ($d && $d->format($fmt) === $s) {
                return $d->format('Y-m-d');
            }
        }
        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function fingerprint(string $storeId, array $e): string
    {
        return hash('sha256', implode('|', [
            $storeId, $e['order_sn'], $e['payout_ref'] ?? '', $e['settlement_date'] ?? '',
            $e['reported_net'] ?? 'null', $e['gross_amount'], json_encode($e['fee_breakdown']),
        ]));
    }

    private static function rawPayload(array $row, array $headers): array
    {
        $out = [];
        foreach ($headers as $i => $h) {
            if ($h !== '' && isset($row[$i]) && $row[$i] !== '') {
                $out[$h] = is_scalar($row[$i]) ? $row[$i] : null;
            }
        }

        return $out;
    }
}

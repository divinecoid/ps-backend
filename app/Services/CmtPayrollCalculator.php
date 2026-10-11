<?php

namespace App\Services;

/**
 * Pure payroll math for CMT penggajian (no DB access), so it can be unit
 * tested on its own: piece -> lusin conversion and grouping of received
 * pieces into one payroll row per seri + model.
 */
class CmtPayrollCalculator
{
    /**
     * Maps leftover pieces (1-11, not making a full dozen) to their paid
     * lusin-equivalent fraction, per the CMT's "HITUNGAN PCS" convention
     * (e.g. 5 pcs is paid as 0.42, not 5/12 = 0.4167). Every entry is n/12
     * rounded to 2 decimals (8 => 0.67, 11 => 0.92; confirmed by the client).
     */
    public const PIECE_TO_LUSIN = [
        1 => 0.08,
        2 => 0.17,
        3 => 0.25,
        4 => 0.33,
        5 => 0.42,
        6 => 0.5,
        7 => 0.58,
        8 => 0.67,
        9 => 0.75,
        10 => 0.83,
        11 => 0.92,
    ];

    /**
     * Converts a piece quantity into its paid lusin (dozen) equivalent:
     * full dozens count as 1.0 lusin each, and any leftover pieces are
     * converted via the fixed CMT piece-to-lusin table above.
     */
    public static function pcsToLusin(int $pcs): float
    {
        $dozens = intdiv($pcs, 12);
        $remainder = $pcs % 12;

        return $dozens + (self::PIECE_TO_LUSIN[$remainder] ?? 0);
    }

    /**
     * Splits a piece quantity into [full dozens, leftover pieces] — e.g.
     * 28 => [2, 4], shown on screen as "2⁴".
     *
     * @return array{0:int,1:int}
     */
    public static function splitDozen(int $pcs): array
    {
        return [intdiv($pcs, 12), $pcs % 12];
    }

    /**
     * Groups received-log rows into payroll rows: ONE row per
     * (request/seri, model, fee) instead of one per scanned barcode, so e.g.
     * 12 + 12 + 4 pcs of the same model on the same seri becomes a single
     * 28-pcs row. Grouping happens BEFORE the lusin conversion — converting
     * each scan separately would pay every loose piece as 0.08 lusin
     * (30 single scans = 2.4 lusin) instead of the true 30 pcs = 2 lusin +
     * 6 pcs = 2.5 lusin.
     *
     * Each input row: [id, request_id, request_detail_id, model_id, qty,
     * rate (per lusin, or null when the model has no configured rate),
     * fallback_unit_fee (per piece, used only when rate is null)].
     *
     * @param  iterable<array<string,mixed>>  $rows
     * @return list<array<string,mixed>> keys: request_detail_id, model_id,
     *         qty, qty_lusin (null for per-piece fallback), unit_fee,
     *         amount, received_log_detail_ids
     */
    public static function group(iterable $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $perLusin = $row['rate'] !== null;
            $unitFee = $perLusin ? (float) $row['rate'] : (float) ($row['fallback_unit_fee'] ?? 0);
            $key = implode('|', [
                $row['request_id'] ?? '',
                $row['model_id'] ?? '',
                $perLusin ? 'lusin' : 'pcs',
                number_format($unitFee, 2, '.', ''),
            ]);

            $groups[$key] ??= [
                'request_detail_id' => $row['request_detail_id'] ?? null,
                'model_id' => $row['model_id'] ?? null,
                'per_lusin' => $perLusin,
                'unit_fee' => $unitFee,
                'qty' => 0,
                'received_log_detail_ids' => [],
            ];

            $groups[$key]['qty'] += (int) $row['qty'];
            $groups[$key]['received_log_detail_ids'][] = $row['id'];
        }

        return array_values(array_map(function (array $g) {
            $qtyLusin = $g['per_lusin'] ? self::pcsToLusin($g['qty']) : null;
            $amount = round($g['unit_fee'] * ($qtyLusin ?? $g['qty']), 2);

            return [
                'request_detail_id' => $g['request_detail_id'],
                'model_id' => $g['model_id'],
                'qty' => $g['qty'],
                'qty_lusin' => $qtyLusin,
                'unit_fee' => $g['unit_fee'],
                'amount' => $amount,
                'received_log_detail_ids' => $g['received_log_detail_ids'],
            ];
        }, $groups));
    }
}

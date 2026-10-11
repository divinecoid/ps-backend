<?php

namespace Tests\Unit;

use App\Services\CmtPayrollCalculator;
use PHPUnit\Framework\TestCase;

class CmtPayrollCalculatorTest extends TestCase
{
    private function row(string $id, int $qty, string $request = 'R1', string $model = 'M1', ?float $rate = 100000, float $fallback = 0): array
    {
        return [
            'id' => $id,
            'request_id' => $request,
            'request_detail_id' => "rd-$id",
            'model_id' => $model,
            'qty' => $qty,
            'rate' => $rate,
            'fallback_unit_fee' => $fallback,
        ];
    }

    public function test_pcs_to_lusin_uses_cmt_table_for_leftovers(): void
    {
        $this->assertSame(1.0, CmtPayrollCalculator::pcsToLusin(12));
        $this->assertSame(0.08, CmtPayrollCalculator::pcsToLusin(1));
        $this->assertSame(2.33, CmtPayrollCalculator::pcsToLusin(28));
        $this->assertSame(2.5, CmtPayrollCalculator::pcsToLusin(30));
    }

    public function test_split_dozen(): void
    {
        $this->assertSame([2, 4], CmtPayrollCalculator::splitDozen(28));
        $this->assertSame([0, 5], CmtPayrollCalculator::splitDozen(5));
        $this->assertSame([3, 0], CmtPayrollCalculator::splitDozen(36));
    }

    public function test_dozen_and_loose_scans_of_same_model_become_one_row(): void
    {
        // 12 + 12 + 4 pcs of "28 PANJANG LIS" -> a single 28-pcs row (2 dozen + 4).
        $rows = CmtPayrollCalculator::group([
            $this->row('a', 12), $this->row('b', 12), $this->row('c', 4),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(28, $rows[0]['qty']);
        $this->assertSame(2.33, $rows[0]['qty_lusin']);
        $this->assertSame(233000.0, $rows[0]['amount']);
        $this->assertSame(['a', 'b', 'c'], $rows[0]['received_log_detail_ids']);
    }

    public function test_loose_pieces_are_summed_before_lusin_conversion(): void
    {
        // 30 single-piece scans must pay 2.5 lusin (30 pcs), not 30 x 0.08 = 2.4.
        $scans = array_map(fn($i) => $this->row("p$i", 1), range(1, 30));
        $rows = CmtPayrollCalculator::group($scans);

        $this->assertCount(1, $rows);
        $this->assertSame(30, $rows[0]['qty']);
        $this->assertSame(2.5, $rows[0]['qty_lusin']);
        $this->assertSame(250000.0, $rows[0]['amount']);
    }

    public function test_different_seri_or_model_stay_separate_rows(): void
    {
        $rows = CmtPayrollCalculator::group([
            $this->row('a', 12, 'R1', 'M1'),
            $this->row('b', 12, 'R2', 'M1'),
            $this->row('c', 12, 'R1', 'M2'),
        ]);

        $this->assertCount(3, $rows);
    }

    public function test_model_without_rate_falls_back_to_per_piece_fee(): void
    {
        $rows = CmtPayrollCalculator::group([
            $this->row('a', 10, rate: null, fallback: 5000),
            $this->row('b', 5, rate: null, fallback: 5000),
        ]);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['qty_lusin']);
        $this->assertSame(15, $rows[0]['qty']);
        $this->assertSame(75000.0, $rows[0]['amount']);
    }
}

<?php

/**
 * Marketplace settlement reconciliation rules.
 * Every threshold can be overridden per environment.
 */
return [
    // Max |reported_net - expected_net| (Rp) still counted as matched (rounding).
    'settlement_tolerance' => (float) env('RECON_SETTLEMENT_TOLERANCE', 1),

    // Max |bank credit - payout total| (Rp) still counted as a bank match.
    'bank_tolerance' => (float) env('RECON_BANK_TOLERANCE', 0),

    // +/- days around the payout date when looking for a bank credit by amount.
    'bank_date_window_days' => (int) env('RECON_BANK_DATE_WINDOW', 3),

    // A fee category above this share of gross flags the entry as UNUSUAL_FEE.
    'unusual_fee_pct' => [
        'event' => (float) env('RECON_UNUSUAL_EVENT_PCT', 10),
        'commission' => (float) env('RECON_UNUSUAL_COMMISSION_PCT', 15),
        'service' => (float) env('RECON_UNUSUAL_SERVICE_PCT', 10),
        'shipping' => (float) env('RECON_UNUSUAL_SHIPPING_PCT', 25),
        'other' => (float) env('RECON_UNUSUAL_OTHER_PCT', 5),
    ],

    // Total deductions above this share of gross flags the entry as UNUSUAL_FEE.
    'unusual_total_deduction_pct' => (float) env('RECON_UNUSUAL_TOTAL_PCT', 35),
];

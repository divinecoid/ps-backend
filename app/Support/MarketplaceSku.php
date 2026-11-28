<?php

namespace App\Support;

/**
 * What one marketplace order item means in physical pieces.
 *
 * Format: [PAKET{N}-][LOGO{n}*]<SKU>[+<SKU_extra>], e.g.
 * PAKET3-LOGO48*PANJANGPOLOS+OBLONGHITAM = 3x PANJANGPOLOS + 1x OBLONGHITAM.
 * Colours are listed per piece ("Merah=Biru=Hitam").
 *
 * This mirrors parseSku() in the web order screen (form-order.tsx), which is
 * what the warehouse scans against. Change both together.
 */
class MarketplaceSku
{
    private const DEFAULT_COLOR = 'Hitam';

    /** @return array<int,array{sku:string,color:string}> one entry per piece */
    public static function pieces(string $sku, ?string $colors = null): array
    {
        if (!preg_match('/^(?:(PAKET(\d+))-)?(?:(.+?)\*)?([^+]+)(?:\+(.+))?$/', $sku, $match)) {
            return [];
        }

        $count = ($match[2] ?? '') !== '' ? (int) $match[2] : 1;
        $colorList = ($colors ?? '') !== '' ? preg_split('/[|=]/', $colors) : [];

        $pieces = [];
        for ($i = 0; $i < $count; $i++) {
            $pieces[] = ['sku' => $match[4], 'color' => $colorList[$i] ?? self::DEFAULT_COLOR];
        }
        if (($match[5] ?? '') !== '') {
            $pieces[] = ['sku' => $match[5], 'color' => self::DEFAULT_COLOR];
        }

        return $pieces;
    }
}

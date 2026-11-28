<?php

namespace Tests\Unit;

use App\Support\MarketplaceSku;
use PHPUnit\Framework\TestCase;

class MarketplaceSkuTest extends TestCase
{
    public function test_package_with_logo_and_extra_item(): void
    {
        $pieces = MarketplaceSku::pieces('PAKET3-LOGO48*PANJANGPOLOS+OBLONGHITAM', 'Merah=Biru|Hijau');

        $this->assertSame(
            ['PANJANGPOLOS:Merah', 'PANJANGPOLOS:Biru', 'PANJANGPOLOS:Hijau', 'OBLONGHITAM:Hitam'],
            array_map(fn($p) => "{$p['sku']}:{$p['color']}", $pieces)
        );
    }

    public function test_plain_sku(): void
    {
        $this->assertSame([['sku' => 'MDL01', 'color' => 'BLACK']], MarketplaceSku::pieces('MDL01', 'BLACK'));
        $this->assertSame([['sku' => 'MDL01', 'color' => 'Hitam']], MarketplaceSku::pieces('MDL01'));
        $this->assertSame([], MarketplaceSku::pieces(''));
    }
}

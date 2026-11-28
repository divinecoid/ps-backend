<?php

namespace Tests\Unit;

use App\Support\ShippingClassifier as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShippingClassifierTest extends TestCase
{
    public static function shopeeNames(): array
    {
        return [
            'instant' => [null, 'SPX Instant', S::INSTANT, 'Shopee Express (SPX)'],
            'instant prioritas' => ['Instant Prioritas', 'GoSend Instant Prioritas', S::INSTANT, 'GoSend'],
            'same day' => [null, 'GrabExpress Sameday', S::SAMEDAY, 'GrabExpress'],
            'checkout choice wins' => ['Reguler (Cashless)', 'J&T Express', S::REGULER, 'J&T Express'],
            'courier only is regular' => [null, 'SPX Express', S::REGULER, 'Shopee Express (SPX)'],
            'hemat' => ['Hemat', 'SPX Hemat', S::HEMAT, 'Shopee Express (SPX)'],
            'hemat kargo is cargo' => ['Hemat Kargo', 'J&T Cargo', S::KARGO, 'J&T Cargo'],
            'jne' => [null, 'JNE Reguler', S::REGULER, 'JNE'],
            'service word is not a courier' => ['Reguler', null, S::REGULER, null],
        ];
    }

    #[DataProvider('shopeeNames')]
    public function test_names(?string $chosen, ?string $carrier, string $type, ?string $courier): void
    {
        $this->assertSame($type, S::fromNames($chosen, $carrier));
        $this->assertSame($courier, S::courier($carrier, $chosen));
    }

    public function test_tiktok_priority_level_decides_the_type(): void
    {
        $this->assertSame(S::INSTANT, S::tiktok(100, 'Pengiriman reguler'));
        $this->assertSame(S::SAMEDAY, S::tiktok(300, null));
        $this->assertSame(S::REGULER, S::tiktok(600, 'Standard shipping'));
        $this->assertSame(S::KARGO, S::tiktok(800, null));
        $this->assertSame(S::SAMEDAY, S::tiktok(null, 'Instant Hemat'));
        $this->assertNull(S::tiktok(null, null));
    }

    public function test_shopee_instant_deadline_is_counted_from_payment(): void
    {
        $paid = 1_800_000_000;
        $later = $paid + 86400;

        $this->assertSame($paid + 3600, S::shopeeShipBy(S::INSTANT, 'SPX Instant', $later, $paid)->timestamp);
        $this->assertSame($paid + 2700, S::shopeeShipBy(S::INSTANT, 'Instant Prioritas', $later, $paid)->timestamp);
        $this->assertSame($later, S::shopeeShipBy(S::REGULER, 'SPX Express', $later, $paid)->timestamp);
    }
}

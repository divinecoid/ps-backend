<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Turns what Shopee and TikTok Shop say about a shipment into one service type
 * (instant, same day, regular, ...) and one courier name.
 */
class ShippingClassifier
{
    public const INSTANT = 'instant';
    public const SAMEDAY = 'sameday';
    public const NEXTDAY = 'nextday';
    public const EXPRESS = 'express';
    public const REGULER = 'reguler';
    public const HEMAT = 'hemat';
    public const KARGO = 'kargo';
    public const PICKUP = 'ambil_sendiri';

    public const LABELS = [
        self::INSTANT => 'Instant',
        self::SAMEDAY => 'Same Day',
        self::NEXTDAY => 'Next Day',
        self::EXPRESS => 'Express',
        self::REGULER => 'Reguler',
        self::HEMAT => 'Hemat',
        self::KARGO => 'Kargo',
        self::PICKUP => 'Ambil Sendiri',
    ];

    /**
     * TikTok Shop `fulfillment_priority_level` (South-East Asia):
     * https://partner.tiktokshop.com/docv2/page/get-order-detail-202507
     */
    private const TIKTOK_PRIORITY = [
        100 => self::INSTANT,
        200 => self::SAMEDAY,
        300 => self::SAMEDAY,
        400 => self::NEXTDAY,
        500 => self::EXPRESS,
        600 => self::REGULER,
        700 => self::HEMAT,
        800 => self::KARGO,
    ];

    /** Checked in order: the first match wins. */
    private const NAME_PATTERNS = [
        // Sold to buyers as "Instant Hemat", handled by sellers as same day.
        '/instant?\s*hemat/i' => self::SAMEDAY,
        '/same\s*-?\s*day/i' => self::SAMEDAY,
        '/\binstan(t)?\b/i' => self::INSTANT,
        '/next\s*-?\s*day|\byes\b|besok sampai/i' => self::NEXTDAY,
        '/cargo|kargo|trucking|\bjtr\b|gokil/i' => self::KARGO,
        '/hemat|econom|ekonomi|\boke\b|\beco\b/i' => self::HEMAT,
        '/ambil di tempat|self[\s_-]*collect|in[\s_-]*store|pick\s*-?\s*up point/i' => self::PICKUP,
        '/regul[ae]r|standard|standar|\breg\b/i' => self::REGULER,
    ];

    private const COURIERS = [
        '/j\s*&\s*t\s*(cargo|kargo)|jnt\s*(cargo|kargo)/i' => 'J&T Cargo',
        '/j\s*&\s*t|\bjnt\b/i' => 'J&T Express',
        '/\bspx\b|shopee\s*(express|xpress)/i' => 'Shopee Express (SPX)',
        '/\bjne\b|\bjtr\b/i' => 'JNE',
        '/si\s*cepat|\bgokil\b/i' => 'SiCepat',
        '/anter\s*aja/i' => 'AnterAja',
        '/go\s*-?\s*send|\bgojek\b/i' => 'GoSend',
        '/\bgrab/i' => 'GrabExpress',
        '/ninja/i' => 'Ninja Xpress',
        '/\bid\s*express\b|\bidx\b/i' => 'ID Express',
        '/\bpos\b/i' => 'Pos Indonesia',
        '/\btiki\b/i' => 'TIKI',
        '/lion\s*parcel/i' => 'Lion Parcel',
        '/paxel/i' => 'Paxel',
        '/lalamove/i' => 'Lalamove',
        '/blitz/i' => 'Blitz',
        '/blue\s*bird|kirim instant car/i' => 'Bluebird Kirim',
        '/wahana/i' => 'Wahana',
        '/\bsap\b/i' => 'SAP Express',
    ];

    /** Service type from the marketplace's service names, most specific name first. */
    public static function fromNames(?string ...$names): ?string
    {
        $names = array_filter(array_map(fn($n) => trim((string) $n), $names));
        foreach ($names as $name) {
            foreach (self::NAME_PATTERNS as $pattern => $type) {
                if (preg_match($pattern, $name)) {
                    return $type;
                }
            }
        }

        // A courier with no service word ("J&T Express", "SPX Express") is the regular service.
        return $names ? self::REGULER : null;
    }

    public static function tiktok(?int $priorityLevel, ?string $deliveryOption, ?string $provider = null): ?string
    {
        return self::TIKTOK_PRIORITY[$priorityLevel] ?? self::fromNames($deliveryOption, $provider);
    }

    public static function courier(?string ...$names): ?string
    {
        foreach ($names as $name) {
            foreach (self::COURIERS as $pattern => $courier) {
                if (preg_match($pattern, (string) $name)) {
                    return $courier;
                }
            }
        }
        // Unknown courier: keep its own name. A bare service word ("Reguler") is not a courier.
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '' && !preg_match('/^(regul[ae]r|standard|standar|hemat|kargo|instant?|same\s*day)\b/i', $name)) {
                return mb_substr($name, 0, 60);
            }
        }

        return null;
    }

    /**
     * Shopee Instant orders are cancelled automatically when not packed in
     * time: 1 hour after the order comes in, 45 minutes for Instant Prioritas
     * (https://seller.shopee.co.id/edu/article/19288).
     */
    public static function shopeeShipBy(?string $type, ?string $serviceName, ?int $shipByDate, ?int $paidAt): ?Carbon
    {
        $candidates = [];
        if ($shipByDate) {
            $candidates[] = $shipByDate;
        }
        if ($type === self::INSTANT && $paidAt) {
            $candidates[] = $paidAt + 60 * (stripos((string) $serviceName, 'priorit') !== false ? 45 : 60);
        }

        return $candidates ? Carbon::createFromTimestamp(min($candidates)) : null;
    }
}

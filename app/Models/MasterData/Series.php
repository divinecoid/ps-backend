<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mdx_series';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'current',
        'digits',
    ];

    /**
     * Get the next sequential value for a key and format as padded string.
     */
    public static function nextValue(string $key, int $digits = 5): string
    {
        $series = self::where('key', $key)->lockForUpdate()->first();

        if (!$series) {
            $series = self::create([
                'key' => $key,
                'current' => 0,
                'digits' => $digits,
            ]);
        }

        $series->increment('current');

        return str_pad((string) $series->current, $series->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Preview the next sequential value without incrementing.
     */
    public static function previewNextValue(string $key, int $digits = 5): string
    {
        $series = self::where('key', $key)->first();

        $next = $series ? $series->current + 1 : 1;
        $dig = $series ? $series->digits : $digits;

        return str_pad((string) $next, $dig, '0', STR_PAD_LEFT);
    }
}

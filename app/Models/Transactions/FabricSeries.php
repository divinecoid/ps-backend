<?php

namespace App\Models\Transactions;

use App\Models\MasterData\Color;
use App\Models\MasterData\Configuration;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FabricSeries extends Model
{
    /** @use HasFactory<\Database\Factories\Transactions\FabricSeriesFactory> */
    use HasFactory, HasUuids;

    protected $table = 'trx_fabric_series';

    protected $fillable = [
        'model_id',
        'series_code',
        'sequence',
        'roll_available',
    ];

    public function configuration()
    {
        return $this->belongsTo(Configuration::class, 'configuration_id');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }
}

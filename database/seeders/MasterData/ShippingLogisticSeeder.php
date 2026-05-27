<?php

namespace Database\Seeders\MasterData;

use Illuminate\Database\Seeder;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\ShippingLogistic;

class ShippingLogisticSeeder extends Seeder
{
    public function run(): void
    {
        $shopee = Marketplace::where('alias', 'shopee')->first();
        $shopeeSandbox = Marketplace::where('alias', 'shopee_sandbox')->first();

        $shopeeLogistics = [
            ['logistic_id' => '50001', 'logistic_name' => 'J&T Express',          'logistic_type' => 'Regular'],
            ['logistic_id' => '50002', 'logistic_name' => 'SiCepat REG',           'logistic_type' => 'Regular'],
            ['logistic_id' => '50003', 'logistic_name' => 'JNE REG',               'logistic_type' => 'Regular'],
            ['logistic_id' => '50004', 'logistic_name' => 'AnterAja',              'logistic_type' => 'Regular'],
            ['logistic_id' => '50005', 'logistic_name' => 'Ninja Xpress',          'logistic_type' => 'Regular'],
            ['logistic_id' => '50006', 'logistic_name' => 'Shopee Express Standard', 'logistic_type' => 'Regular'],
            ['logistic_id' => '50007', 'logistic_name' => 'Shopee Express Hemat',  'logistic_type' => 'Economy'],
            ['logistic_id' => '50008', 'logistic_name' => 'Shopee Express Sameday', 'logistic_type' => 'Sameday'],
            ['logistic_id' => '50009', 'logistic_name' => 'IDExpress',             'logistic_type' => 'Regular'],
            ['logistic_id' => '50010', 'logistic_name' => 'Lion Parcel',           'logistic_type' => 'Regular'],
        ];

        foreach ($shopeeLogistics as $logistic) {
            if ($shopee) {
                ShippingLogistic::updateOrCreate(
                    ['marketplace_id' => $shopee->id, 'logistic_id' => $logistic['logistic_id']],
                    ['logistic_name' => $logistic['logistic_name'], 'logistic_type' => $logistic['logistic_type'], 'is_active' => true]
                );
            }

            if ($shopeeSandbox) {
                ShippingLogistic::updateOrCreate(
                    ['marketplace_id' => $shopeeSandbox->id, 'logistic_id' => $logistic['logistic_id']],
                    ['logistic_name' => $logistic['logistic_name'], 'logistic_type' => $logistic['logistic_type'], 'is_active' => true]
                );
            }
        }
    }
}

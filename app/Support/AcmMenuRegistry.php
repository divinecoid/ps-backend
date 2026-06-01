<?php

namespace App\Support;

/**
 * ACM Menu Registry — single source of truth for all menu keys.
 */
class AcmMenuRegistry
{
    public static function all(): array
    {
        return [
            // Gudang (Inventory)
            'gudang_kain'   => ['label' => 'Gudang Kain'],
            'gudang_kecil'  => ['label' => 'Gudang Kecil'],
            'gudang_besar'  => ['label' => 'Gudang Besar'],

            // Transaksi Kain
            'pembelian_kain' => ['label' => 'Pembelian Kain'],

            // Transaksi CMT
            'permintaan'         => ['label' => 'Permintaan CMT'],
            'penerimaan'         => ['label' => 'Penerimaan CMT'],
            'riwayat_penerimaan' => ['label' => 'Riwayat Penerimaan'],

            // Mutasi Gudang
            'mutasi' => ['label' => 'Mutasi Gudang'],

            // Transaksi Toko Online
            'pesanan' => ['label' => 'Pesanan Toko Online'],

            // Master Data
            'master_gudang'      => ['label' => 'Master Gudang'],
            'master_rak'         => ['label' => 'Master Rak'],
            'master_cmt'         => ['label' => 'Master CMT'],
            'master_product'     => ['label' => 'Master Product'],
            'master_ukuran'      => ['label' => 'Master Ukuran'],
            'master_warna'       => ['label' => 'Master Warna'],
            'master_model'       => ['label' => 'Master Model'],
            'master_pabrik'      => ['label' => 'Master Pabrik'],
            'master_roll_size'   => ['label' => 'Master Ukuran Roll'],
            'master_toko'        => ['label' => 'Master Toko Online'],
            'master_marketplace' => ['label' => 'Master Marketplace'],
            'master_konfigurasi' => ['label' => 'Konfigurasi'],
            'audit_log'          => ['label' => 'Audit Log'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}

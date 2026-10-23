<?php

namespace App\Console\Commands;

use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;
use Illuminate\Console\Command;

class FillRackSizeFromName extends Command
{
    protected $signature = 'racks:fill-size {--apply : Save the changes (default is a dry run)}';

    protected $description = 'Fill mdx_racks.size_id of small-warehouse racks from the size at the end of the rack name (e.g. "RAK-339 SAKU ONLINE-L")';

    // Same size written two ways (rack names use XXL, barcodes may use 2XL).
    private const ALIASES = [
        'XXL' => ['2XL'], '2XL' => ['XXL'],
        'XXXL' => ['3XL'], '3XL' => ['XXXL'],
        'XXXXL' => ['4XL'], '4XL' => ['XXXXL'],
    ];

    public function handle(): int
    {
        $sizes = Size::all();
        $apply = $this->option('apply');
        $rows = [];
        $updates = 0;

        Rack::with(['warehouse', 'size'])
            ->whereNull('size_id')
            ->whereHas('warehouse', fn($q) => $q->where('type', 'KECIL'))
            ->orderBy('code')
            ->get()
            ->each(function (Rack $rack) use ($sizes, $apply, &$rows, &$updates) {
                $token = $this->sizeToken($rack->name);
                $size = $token ? $this->findSize($sizes, $token) : null;

                if ($size && $apply) {
                    $rack->update(['size_id' => $size->id]);
                }
                if ($size) {
                    $updates++;
                }
                $rows[] = [$rack->code, $rack->name, $token ?? '-', $size?->code ?? 'TIDAK COCOK'];
            });

        $this->table(['Kode', 'Nama', 'Token', 'Ukuran'], $rows);
        $this->info(($apply ? 'Diupdate: ' : 'Akan diupdate (dry run): ') . "$updates dari " . count($rows) . ' rak.');
        if (!$apply) {
            $this->line('Jalankan dengan --apply untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /** Last "-" or space separated part of the name, e.g. "L" from "RAK-339 SAKU ONLINE-L". */
    private function sizeToken(string $name): ?string
    {
        $parts = preg_split('/[-\s]+/', trim($name));
        $last = strtoupper(end($parts) ?: '');
        return $last !== '' ? $last : null;
    }

    private function findSize($sizes, string $token): ?Size
    {
        foreach ([$token, ...(self::ALIASES[$token] ?? [])] as $candidate) {
            $hit = $sizes->first(fn(Size $s) => strtoupper($s->code) === $candidate || strtoupper($s->name) === $candidate);
            if ($hit) {
                return $hit;
            }
        }
        return null;
    }
}

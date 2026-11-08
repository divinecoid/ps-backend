<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Cloth;
use App\Models\Transactions\FabricReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FabricReturnController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn(FabricReturn $data) => [
            'id' => $data->id,
            'type' => $data->type,
            'cloth_id' => $data->cloth_id,
            'cloth' => $data->cloth ? [
                'id' => $data->cloth->id,
                'sequence' => $data->cloth->sequence,
                'gram' => $data->cloth->gram,
                'factory' => $data->cloth->factory ? ['id' => $data->cloth->factory->id, 'name' => $data->cloth->factory->name] : null,
                'color' => $data->cloth->color ? ['id' => $data->cloth->color->id, 'name' => $data->cloth->color->name] : null,
            ] : null,
            'cmt_id' => $data->cmt_id,
            'cmt' => $data->cmt ? ['id' => $data->cmt->id, 'name' => $data->cmt->name] : null,
            'quantity' => $data->quantity,
            'reason' => $data->reason,
            'notes' => $data->notes,
            'user' => $data->user ? ['id' => $data->user->id, 'name' => $data->user->name] : null,
            'created_at' => $data->created_at?->utc()->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            FabricReturn::class,
            ['cloth.factory', 'cloth.color', 'cmt', 'user'],
            ['type', 'reason', 'cloth.sequence', 'cmt.name'],
            $this->structure(),
            function ($query) use ($request) {
                if ($request->filled('type')) {
                    $query->where('type', $request->input('type'));
                }
            },
            ['created_at', 'desc']
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            FabricReturn::class,
            $id,
            ['cloth.factory', 'cloth.color', 'cmt', 'user'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'type' => 'required|in:SUPPLIER,CMT',
                'cloth_id' => 'required|uuid|exists:mdx_clothes,id',
                'cmt_id' => 'required_if:type,CMT|nullable|uuid|exists:mdx_cmts,id',
                'quantity' => 'required|integer|min:1',
                'reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ],
            function ($data) {
                try {
                    return DB::transaction(function () use ($data) {
                        $cloth = Cloth::lockForUpdate()->find($data['cloth_id']);
                        if (!$cloth) {
                            return $this->errorResponse(404, 'Kain tidak ditemukan.');
                        }

                        $qty = (int) $data['quantity'];
                        $remaining = (int) ($cloth->remaining_quantity ?? $cloth->quantity);

                        if ($data['type'] === 'SUPPLIER') {
                            // Retur ke pabrik: roll keluar dari Gudang Kain.
                            if ($qty > $remaining) {
                                return $this->errorResponse(422, "Jumlah retur melebihi stok tersedia ({$remaining} roll).");
                            }
                            $cloth->remaining_quantity = $remaining - $qty;
                            $cloth->quantity = max(0, (int) $cloth->quantity - $qty);
                        } else {
                            // Retur dari CMT: roll kembali ke Gudang Kain.
                            $cloth->remaining_quantity = $remaining + $qty;
                            $cloth->quantity = (int) $cloth->quantity + $qty;
                        }
                        $cloth->save();

                        $return = FabricReturn::create([
                            'type' => $data['type'],
                            'cloth_id' => $cloth->id,
                            'cmt_id' => $data['type'] === 'CMT' ? $data['cmt_id'] : null,
                            'quantity' => $qty,
                            'reason' => $data['reason'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'user_id' => Auth::id(),
                        ]);

                        $fresh = FabricReturn::with(['cloth.factory', 'cloth.color', 'cmt', 'user'])->find($return->id);
                        return $this->successResponse(($this->structure())($fresh));
                    });
                } catch (\Throwable $e) {
                    return $this->errorResponse(422, $e->getMessage());
                }
            }
        );
    }
}

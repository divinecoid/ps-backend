<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'serial_number' => $data->serial_number,
            'product_id' => $data->product_id,
            'factory_id' => $data->factory_id,
            'quantity' => $data->quantity,
            'cmt_id' => $data->cmt_id,
            'rack_id' => $data->rack_id,
            'barcode_group' => $data->barcode_group,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Inventory::class,
            [],
            ['serial_number', 'product_id', 'factory_id', 'quantity', 'cmt_id', 'rack_id', 'barcode_group'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Inventory::class,
            $id,
            ['scanned_item', 'request'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Inventory::class,
            [
                'serial_number' => 'required|string|unique:mdx_inventories,serial_number|max:255',
                'product_id' => 'required|exists:mdx_products,id',
                'factory_id' => 'required|exists:mdx_factories,id',
                'quantity' => 'required|integer|min:0',
                'cmt_id' => 'required|exists:mdx_cmts,id',
                'rack_id' => 'required|exists:mdx_racks,id',
                'barcode_group' => 'nullable|string|max:255',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Inventory::class,
            $id,
            [
                'serial_number' => 'required|string|unique:mdx_inventories,serial_number|max:255',
                'product_id' => 'required|exists:mdx_products,id',
                'factory_id' => 'required|exists:mdx_factories,id',
                'quantity' => 'required|integer|min:1',
                'cmt_id' => 'required|exists:mdx_cmts,id',
                'rack_id' => 'required|exists:mdx_racks,id',
                'barcode_group' => 'nullable|string|max:255',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Inventory::class,
            $id
        );
    }
}

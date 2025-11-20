<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Inventory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'cmt' => (object) [
                'name' => $data->cmt->name
            ],
            'rack' => (object) [
                'name' => $data->rack->name
            ],
            'product' => (object) [
                'sku' => $data->product->sku
            ],
            'factory' => (object) [
                'name' => $data->factory->name
            ],
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
                'product_id' => [
                    'required',
                    Rule::exists('mdx_products', 'id')->whereNull('deleted_at'),
                ],
                'factory_id' => [
                    'required',
                    Rule::exists('mdx_factories', 'id')->whereNull('deleted_at'),
                ],
                'quantity' => 'required|integer|min:1',
                'cmt_id' =>
                    [
                        'required',
                        Rule::exists('mdx_cmts', 'id')->whereNull('deleted_at'),
                    ],
                'rack_id' => [
                    'required',
                    Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
                ],
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
                'serial_number' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_inventories', 'serial_number')->ignore($id)
                ],
                'product_id' => [
                    'required',
                    Rule::exists('mdx_products', 'id')->whereNull('deleted_at'),
                ],
                'factory_id' => [
                    'required',
                    Rule::exists('mdx_factories', 'id')->whereNull('deleted_at'),
                ],
                'quantity' => 'required|integer|min:1',
                'cmt_id' =>
                    [
                        'required',
                        Rule::exists('mdx_cmts', 'id')->whereNull('deleted_at'),
                    ],
                'rack_id' => [
                    'required',
                    Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
                ],
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

    public function restore($id)
    {
        return $this->baseRestore(Inventory::class, $id);
    }
}

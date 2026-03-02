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
            'model_id' => $data->model_id,
            'model' => (object) [
                'name' => $data->model->name
            ],
            'color_id' => $data->color_id,
            'color' => (object) [
                'name' => $data->color->name
            ],
            'size_id' => $data->size_id,
            'size' => (object) [
                'name' => $data->size->name
            ],
            'detail' => $data->detail->map(fn($d) => [
                'series' => $d->series,
                'quantity' => $d->quantity,
            ]),
            'total' => (int) ($data->detail_sum_quantity ?? 0)
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Inventory::class,
            ['model', 'color', 'size'],
            ['model_id', 'model.name', 'color_id', 'color.name', 'size_id', 'size.name'],
            $this->structure(),
            function ($query) {
                $query->withSum('detail', 'quantity');
            }
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Inventory::class,
            ['model', 'color', 'size'],
            ['model_id', 'model.name', 'color_id', 'color.name', 'size_id', 'size.name'],
            $this->structure(),
            function ($query) {
                $query->withSum('detail', 'quantity');
            }
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Inventory::class,
            $id,
            ['model', 'color', 'size'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        // return $this->baseStore(
        //     $request,
        //     Inventory::class,
        //     [
        //         'serial_number' => 'required|string|unique:mdx_inventories,serial_number|max:255',
        //         'product_id' => [
        //             'required',
        //             Rule::exists('mdx_products', 'id')->whereNull('deleted_at'),
        //         ],
        //         'factory_id' => [
        //             'required',
        //             Rule::exists('mdx_factories', 'id')->whereNull('deleted_at'),
        //         ],
        //         'quantity' => 'required|integer|min:1',
        //         'cmt_id' =>
        //             [
        //                 'required',
        //                 Rule::exists('mdx_cmts', 'id')->whereNull('deleted_at'),
        //             ],
        //         'rack_id' => [
        //             'required',
        //             Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
        //         ],
        //         'barcode_group' => 'nullable|string|max:255',
        //     ],
        //     null
        // );
    }

    public function update(Request $request, $id)
    {
        // return $this->baseUpdate(
        //     $request,
        //     Inventory::class,
        //     $id,
        //     [
        //         'serial_number' => [
        //             'required',
        //             'string',
        //             'max:255',
        //             Rule::unique('mdx_inventories', 'serial_number')->ignore($id)
        //         ],
        //         'product_id' => [
        //             'required',
        //             Rule::exists('mdx_products', 'id')->whereNull('deleted_at'),
        //         ],
        //         'factory_id' => [
        //             'required',
        //             Rule::exists('mdx_factories', 'id')->whereNull('deleted_at'),
        //         ],
        //         'quantity' => 'required|integer|min:1',
        //         'cmt_id' =>
        //             [
        //                 'required',
        //                 Rule::exists('mdx_cmts', 'id')->whereNull('deleted_at'),
        //             ],
        //         'rack_id' => [
        //             'required',
        //             Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
        //         ],
        //         'barcode_group' => 'nullable|string|max:255',
        //     ],
        //     null
        // );
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

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Inventory::class,
            $request->all()
        );
    }
}

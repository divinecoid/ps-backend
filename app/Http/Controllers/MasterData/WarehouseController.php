<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'priority' => $data->priority
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Warehouse::class,
            [],
            ['code', 'name', 'priority'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Warehouse::class,
            $id,
            ['rack'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Warehouse::class,
            [
                'code' => 'required|string|unique:mdx_warehouses,name|max:255',
                'name' => 'required|string|max:255',
                'priority' => 'required|integer',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Warehouse::class,
            $id,
            [
                'code' => 'required|string|unique:mdx_warehouses,name|max:255',
                'name' => 'required|string|max:255',
                'priority' => 'required|integer',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Warehouse::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Warehouse::class, $id);
    }
}

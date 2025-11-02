<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Rack;
use Illuminate\Http\Request;

class RackController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'warehouse_id' => $data->warehouse_id
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Rack::class,
            [],
            ['code', 'name', 'warehouse_id'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Rack::class,
            $id,
            ['scanned_item', 'inventory'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Rack::class,
            [
                'code' => 'required|string|unique:mdx_racks,name|max:255',
                'name' => 'required|string|max:255',
                'warehouse_id' => 'required|exists:mdx_warehouses,id',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Rack::class,
            $id,
            [
                'code' => 'required|string|unique:mdx_racks,name|max:255',
                'name' => 'required|string|max:255',
                'warehouse_id' => 'required|exists:mdx_warehouses,id',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Rack::class,
            $id
        );
    }
}

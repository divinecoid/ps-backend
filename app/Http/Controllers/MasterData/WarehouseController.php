<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\WarehouseTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Imports\WarehouseImport;
use App\Models\MasterData\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'priority' => $data->priority,
            'type' => $data->type
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
    public function master(Request $request)
    {
        return $this->baseMaster(
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
                'code' => 'required|string|unique:mdx_warehouses,code|max:255',
                'name' => 'required|string|unique:mdx_warehouses,name|max:255',
                'priority' => 'required|integer',
                'type' => 'nullable|string|in:BIG,SMALL',
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
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_warehouses', 'code')->ignore($id)
                ],
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_warehouses', 'name')->ignore($id)
                ],
                'priority' => 'required|integer',
                'type' => 'nullable|string|in:BIG,SMALL',
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

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Warehouse::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Warehouse::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Warehouse::class,
            $request->all()
        );
    }

    public function downloadTemplate()
    {
        return Excel::download(new WarehouseTemplateExport(), 'template-gudang.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new WarehouseImport();
        Excel::import($import, $request->file('file'));

        if ($import->failures()->isNotEmpty()) {
            $first = $import->failures()->first();
            return $this->errorResponse(
                422,
                "Baris {$first->row()}: " . implode(', ', $first->errors())
            );
        }

        return $this->successResponse(null);
    }
}

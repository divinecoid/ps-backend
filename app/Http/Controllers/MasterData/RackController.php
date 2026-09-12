<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\RackTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Imports\RackImport;
use App\Models\MasterData\Rack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class RackController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'warehouse_id' => $data->warehouse_id,
            'warehouse' => (object) [
                'name' => $data->warehouse?->name
            ],
            'model_id' => $data->model_id,
            'model' => $data->model ? (object) [
                'name' => $data->model->name
            ] : null,
            'color_id' => $data->color_id,
            'color' => $data->color ? (object) [
                'name' => $data->color->name
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Rack::class,
            ['warehouse', 'model', 'color'],
            ['id', 'code', 'name', 'warehouse.name', 'model.name', 'color.name'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Rack::class,
            ['warehouse', 'model', 'color'],
            ['code', 'name', 'warehouse.name', 'model.name', 'color.name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Rack::class,
            $id,
            ['warehouse', 'model', 'color'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Rack::class,
            [
                'code' => 'required|string|unique:mdx_racks,code|max:255',
                'name' => 'required|string|max:255',
                'warehouse_id' => [
                    'required',
                    Rule::exists('mdx_warehouses', 'id')->whereNull('deleted_at'),
                ],
                'model_id' => [
                    'nullable',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                ],
                'color_id' => [
                    'nullable',
                    Rule::exists('mdx_colors', 'id')->whereNull('deleted_at'),
                ],
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
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_racks', 'code')->ignore($id)
                ],
                'name' => 'required|string|max:255',
                'warehouse_id' => [
                    'required',
                    Rule::exists('mdx_warehouses', 'id')->whereNull('deleted_at'),
                ],
                'model_id' => [
                    'nullable',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                ],
                'color_id' => [
                    'nullable',
                    Rule::exists('mdx_colors', 'id')->whereNull('deleted_at'),
                ],
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

    public function restore($id)
    {
        return $this->baseRestore(Rack::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Rack::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Rack::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Rack::class,
            $request->all()
        );
    }

    public function downloadTemplate()
    {
        return Excel::download(new RackTemplateExport(), 'template-rak.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new RackImport();
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

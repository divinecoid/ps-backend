<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\FactoryTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Imports\FactoryImport;
use App\Models\MasterData\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class FactoryController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Factory::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }
    
    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Factory::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Factory::class,
            $id,
            [],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Factory::class,
            [
                'code' => 'required|string|unique:mdx_factories,code|max:255',
                'name' => 'required|string|max:255',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Factory::class,
            $id,
            [
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_factories', 'code')->ignore($id)
                ],
                'name' => 'required|string|max:255',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Factory::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Factory::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Factory::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Factory::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Factory::class,
            $request->all()
        );
    }

    public function downloadTemplate()
    {
        return Excel::download(new FactoryTemplateExport(), 'template-pabrik.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new FactoryImport();
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

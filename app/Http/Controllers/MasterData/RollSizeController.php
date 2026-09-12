<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\RollSizeTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Imports\RollSizeImport;
use App\Models\MasterData\RollSize;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class RollSizeController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'size' => $data->size,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            RollSize::class,
            [],
            ['id', 'size'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            RollSize::class,
            [],
            ['size'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            RollSize::class,
            $id,
            [],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            RollSize::class,
            [
                'size' => 'required|string|unique:mdx_roll_sizes,size|max:255',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            RollSize::class,
            $id,
            [
                'size' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_roll_sizes', 'size')->ignore($id)
                ],
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            RollSize::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(RollSize::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            RollSize::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            RollSize::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            RollSize::class,
            $request->all()
        );
    }

    public function downloadTemplate()
    {
        return Excel::download(new RollSizeTemplateExport(), 'template-ukuran-roll.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new RollSizeImport();
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

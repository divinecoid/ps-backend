<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\CMTTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Imports\CMTImport;
use App\Models\MasterData\CMT;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class CMTController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'contact_person' => $data->contact_person,
            'phone' => $data->phone,
            'address' => $data->address
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            CMT::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            CMT::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            CMT::class,
            $id,
            [],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            CMT::class,
            [
                'code' => 'required|string|unique:mdx_cmts,code|max:255',
                'name' => 'required|string|max:255',
                'contact_person' => 'required|string|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255'
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            CMT::class,
            $id,
            [
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_cmts', 'code')->ignore($id)
                ],
                'name' => 'required|string|max:255',
                'contact_person' => 'required|string|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255'
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            CMT::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(CMT::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            CMT::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            CMT::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            CMT::class,
            $request->all()
        );
    }

    public function downloadTemplate()
    {
        return Excel::download(new CMTTemplateExport(), 'template-cmt.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new CMTImport();
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

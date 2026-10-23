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
            'display_name' => $data->name
                . ($data->model ? ' - ' . $data->model->name : '')
                . ($data->color ? ' - ' . $data->color->name : '')
                . ($data->size ? ' - ' . $data->size->name : ''),
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
            'size_id' => $data->size_id,
            'size' => $data->size ? (object) [
                'name' => $data->size->name
            ] : null,
        ];
    }

    private function filterByModel(Request $request)
    {
        return function ($query) use ($request) {
            if ($request->filled('code')) {
                $query->where('code', 'like', '%' . trim($request->input('code')) . '%');
            }
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . trim($request->input('name')) . '%');
            }
            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->input('warehouse_id'));
            }
            if ($request->filled('model_id')) {
                $query->where('model_id', $request->input('model_id'));
            }
            if ($request->filled('color_id')) {
                $query->where('color_id', $request->input('color_id'));
            }
            if ($request->filled('size_id')) {
                $query->where('size_id', $request->input('size_id'));
            }
            if ($request->filled('status')) {
                if ($request->input('status') === 'active') {
                    $query->whereNull('deleted_at');
                } elseif ($request->input('status') === 'inactive') {
                    $query->whereNotNull('deleted_at');
                }
            }
        };
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Rack::class,
            ['warehouse', 'model', 'color', 'size'],
            ['id', 'code', 'name', 'warehouse.name', 'model.name', 'color.name', 'size.name'],
            $this->structure(),
            $this->filterByModel($request),
            ['id' => 'asc']
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Rack::class,
            ['warehouse', 'model', 'color', 'size'],
            ['code', 'name', 'warehouse.name', 'model.name', 'color.name', 'size.name'],
            $this->structure(),
            $this->filterByModel($request)
        );
    }

    /**
     * Rack picker for the mobile app (Mutasi / Terima Barang): every ACTIVE rack
     * with just the fields needed to match a scanned QR and show where it is.
     * Not paginated (a scan has to find any rack) and open to any logged-in
     * user, unlike the admin-only master-data endpoints.
     */
    public function options()
    {
        $racks = Rack::with('warehouse:id,name')
            ->orderBy('code')
            ->get()
            ->map(fn(Rack $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'warehouse_id' => $r->warehouse_id,
                'warehouse' => (object) ['name' => $r->warehouse?->name],
                'size_id' => $r->size_id,
            ]);

        return $this->successResponse($racks);
    }

    public function show($id)
    {
        return $this->baseShow(
            Rack::class,
            $id,
            ['warehouse', 'model', 'color', 'size'],
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
                'name' => 'required|string|unique:mdx_racks,name|max:255',
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
                'size_id' => [
                    'nullable',
                    Rule::exists('mdx_sizes', 'id')->whereNull('deleted_at'),
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
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_racks', 'name')->ignore($id)
                ],
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
                'size_id' => [
                    'nullable',
                    Rule::exists('mdx_sizes', 'id')->whereNull('deleted_at'),
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

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->errorResponse(
                422,
                'Gagal impor: ada kode atau nama rak yang sudah dipakai oleh rak lain.'
            );
        }

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

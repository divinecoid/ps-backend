<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CmtModelRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CmtModelRateController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'model_id' => $data->model_id,
            'model' => $data->model ? (object) [
                'name' => $data->model->name
            ] : null,
            'kategori' => $data->kategori,
            'rate' => $data->rate,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            CmtModelRate::class,
            ['model'],
            ['model.name', 'kategori'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            CmtModelRate::class,
            ['model'],
            ['model.name', 'kategori'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            CmtModelRate::class,
            $id,
            ['model'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            CmtModelRate::class,
            [
                'model_id' => [
                    'required',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                    Rule::unique('mdx_cmt_model_rates', 'model_id')->where(fn($q) => $q->where('kategori', $request->input('kategori'))),
                ],
                'kategori' => 'required|in:DALAM_KOTA,LUAR_KOTA',
                'rate' => 'required|numeric|min:0',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            CmtModelRate::class,
            $id,
            [
                'model_id' => [
                    'required',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                    Rule::unique('mdx_cmt_model_rates', 'model_id')->where(fn($q) => $q->where('kategori', $request->input('kategori')))->ignore($id),
                ],
                'kategori' => 'required|in:DALAM_KOTA,LUAR_KOTA',
                'rate' => 'required|numeric|min:0',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            CmtModelRate::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(CmtModelRate::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            CmtModelRate::class,
            $request->all()
        );
    }
}

<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CmtRateGroup;
use App\Models\MasterData\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CmtRateGroupController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'name' => $data->name,
            'model_ids' => $data->models()->pluck('mdx_models.id'),
        ];
    }

    private function syncModels(CmtRateGroup $group, Request $request)
    {
        if (!$request->has('model_ids')) {
            return;
        }
        $ids = array_filter((array) $request->input('model_ids'));
        ProductModel::where('cmt_rate_group_id', $group->id)
            ->whereNotIn('id', $ids)
            ->update(['cmt_rate_group_id' => null]);
        if (!empty($ids)) {
            ProductModel::whereIn('id', $ids)
                ->update(['cmt_rate_group_id' => $group->id]);
        }
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            CmtRateGroup::class,
            [],
            ['name'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            CmtRateGroup::class,
            [],
            ['name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            CmtRateGroup::class,
            $id,
            [],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            CmtRateGroup::class,
            [
                'name' => 'required|string|unique:mdx_cmt_rate_groups,name|max:255',
                'model_ids' => 'array',
                'model_ids.*' => 'string|exists:mdx_models,id',
            ],
            fn($data, $request) => $this->syncModels($data, $request)
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            CmtRateGroup::class,
            $id,
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_cmt_rate_groups', 'name')->ignore($id)
                ],
                'model_ids' => 'array',
                'model_ids.*' => 'string|exists:mdx_models,id',
            ],
            fn($data, $request) => $this->syncModels($data, $request)
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            CmtRateGroup::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(CmtRateGroup::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            CmtRateGroup::class,
            $request->all()
        );
    }
}

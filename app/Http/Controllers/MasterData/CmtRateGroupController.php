<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CmtRateGroup;
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
        ];
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
            ],
            null
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
            ],
            null
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

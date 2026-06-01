<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Marketplace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'alias' => $data->alias,
            'base_api_url' => $data->base_api_url,
            'description' => $data->description,
            'is_need_checker' => $data->is_needed_checker
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Marketplace::class,
            [],
            ['code', 'name', 'alias', 'base_api_url', 'is_need_checker'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Marketplace::class,
            [],
            ['code', 'name', 'alias', 'base_api_url', 'is_need_checker'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Marketplace::class,
            $id,
            ['online_store'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Marketplace::class,
            [
                'code' => 'required|string|unique:mdx_marketplaces,code|max:255',
                'name' => 'required|string|max:255',
                'alias' => 'required|string|max:255',
                'base_api_url' => 'required|string|max:255',
                'description' => 'string|max:500',
                'is_need_checker' => 'required|boolean',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Marketplace::class,
            $id,
            [
                'code' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_marketplaces', 'code')->ignore($id)
                ],
                'name' => 'sometimes|required|string|max:255',
                'alias' => 'sometimes|required|string|max:255',
                'base_api_url' => 'sometimes|required|string|max:255',
                'description' => 'string|max:500',
                'is_need_checker' => 'sometimes|required|boolean',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Marketplace::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Marketplace::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Marketplace::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Marketplace::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Marketplace::class,
            $request->all()
        );
    }
}

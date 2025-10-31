<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Marketplace;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'base_api_url' => $data->base_api_url,
            'description' => $data->description,
            'is_needed_checker' => $data->is_needed_checker
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Marketplace::class,
            [],
            ['code', 'name', 'base_api_url', 'is_needed_checker'],
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
                'code' => 'required|string|unique:mdx_marketplaces,name|max:255',
                'name' => 'required|string|max:255',
                'base_api_url' => 'required|string|max:255',
                'description' => 'string|max:500',
                'is_needed_checker' => 'required|boolean',
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
                'code' => "sometimes|required|string|unique:mdx_marketplaces,name,{$id}|max:255",
                'name' => 'sometimes|required|string|max:255',
                'base_api_url' => 'sometimes|required|string|max:255',
                'description' => 'string|max:500',
                'is_needed_checker' => 'sometimes|required|boolean',
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
}
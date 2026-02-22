<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\OnlineStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnlineStoreController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'marketplace_id' => $data->marketplace_id,
            'marketplace' => (object)[
                'name' => $data->marketplace->name
            ],
            'store_code' => $data->store_code,
            'store_name' => $data->store_name,
            'api_key' => $data->api_key,
            'client_id' => $data->client_id,
            'client_secret' => $data->client_secret,
            'store_url' => $data->store_url,
            'is_active' => $data->is_active,
            'redirect_uri' => $data->redirect_uri,
            'access_token' => $data->access_token,
            'refresh_token' => $data->refresh_token,
            'access_token_expires_at' => $data->access_token_expires_at,
            'refresh_token_expires_at' => $data->refresh_token_expires_at
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            OnlineStore::class,
            [],
            ['marketplace_id', 'store_code', 'store_name', 'api_key', 'client_id', 'client_secret', 'store_url', 'is_active'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            OnlineStore::class,
            [],
            ['marketplace_id', 'store_code', 'store_name', 'api_key', 'client_id', 'client_secret', 'store_url', 'is_active'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            OnlineStore::class,
            $id,
            ['order'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            OnlineStore::class,
            [
                'marketplace_id' => [
                    'required',
                    Rule::exists('mdx_marketplaces', 'id')->whereNull('deleted_at'),
                ],
                'store_code' => 'required|string|unique:mdx_online_stores,store_code|max:255',
                'store_name' => 'required|string|max:255',
                'api_key' => 'string|max:500',
                'client_id' => 'string|max:255',
                'client_secret' => 'string|max:500',
                'store_url' => 'string|max:500',
                'is_active' => 'required|boolean',
                'redirect_uri' => 'string|max:500',
                'access_token' => 'string|nullable',
                'refresh_token' => 'string|nullable',
                'expires_at' => 'date|nullable',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            OnlineStore::class,
            $id,
            [
                'marketplace_id' => [
                    'required',
                    Rule::exists('mdx_marketplaces', 'id')->whereNull('deleted_at'),
                ],
                'store_code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_online_stores', 'store_code')->ignore($id)->whereNull('deleted_at'),
                ],
                'store_name' => 'required|string|max:255',
                'api_key' => 'string|max:500',
                'client_id' => 'string|max:255',
                'client_secret' => 'string|max:500',
                'store_url' => 'string|max:500',
                'is_active' => 'required|boolean',
                'redirect_uri' => 'required|string|max:500',
                'access_token' => 'string|nullable',
                'refresh_token' => 'string|nullable',
                'expires_at' => 'date|nullable',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            OnlineStore::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(OnlineStore::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            OnlineStore::class,
            $request->all()
        );
    }
}

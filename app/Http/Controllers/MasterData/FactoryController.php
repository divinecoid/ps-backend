<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Factory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
}

<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'name' => $data->name,
            'description' => $data->description,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Role::class,
            [],
            ['name'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Role::class,
            [],
            ['name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Role::class,
            $id,
            [],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Role::class,
            [
                'name' => 'required|string|unique:mdx_roles,name|max:255',
                'description' => 'nullable|string|max:500',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Role::class,
            $id,
            [
                'name' => "sometimes|required|string|max:255,unique:mdx_roles,name,{$id}",
                'description' => "sometimes|nullable|string|max:500",
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Role::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Role::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Role::class,
            $request->all()
        );
    }
}

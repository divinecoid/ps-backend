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
        return fn($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
        ];
    }

    public function index(Request $request)
    {
        $result = $this->baseIndex(
            $request,
            Role::class,
            [],
            ['name'],
            $this->structure()
        );
        return $result;
    }

    public function show($id)
    {
        $user = $this->baseShow(
            Role::class,
            $id,
            [],
            $this->structure()
        );
        return $user;
    }

    public function store(Request $request)
    {
        $result = $this->baseStore(
            $request,
            Role::class,
            [
                'name' => 'required|string|unique:mdx_roles,name|max:255',
                'description' => 'nullable|string|max:500',
            ],
            null
        );
        return $result;
    }

    public function update(Request $request, $id)
    {
        $result = $this->baseUpdate(
            $request,
            Role::class,
            $id,
            [
                'name' => "sometimes|required|string|unique:mdx_roles,name|max:255,unique:mdx_roles,name,{$id}",
                'description' => "sometimes|nullable|string|max:500",
            ],
            null
        );
        return $result;
    }

    public function destroy($id)
    {
        $result = $this->baseDelete(
            Role::class,
            $id
        );
        return $result;
    }
}
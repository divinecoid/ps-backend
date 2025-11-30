<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'name' => $data->name,
            'username' => $data->username,
            'email' => $data->email,
            'roles' => $data->roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name
            ]),
            'role_id' => $data->roles->pluck('id')
        ];
    }

    public function index(Request $request)
    {
        $result = $this->baseIndex(
            $request,
            User::class,
            ['roles'],
            ['name', 'username', 'email'],
            $this->structure()
        );
        return $result;
    }

    public function master(Request $request)
    {
        $result = $this->baseMaster(
            $request,
            User::class,
            ['roles'],
            ['name', 'username', 'email'],
            $this->structure()
        );
        return $result;
    }

    public function show($id)
    {
        return $this->baseShow(
            User::class,
            $id,
            ['roles'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            User::class,
            [
                'username' => 'required|string|unique:users,username',
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:8',
                'role_id' => [
                    'required',
                    Rule::exists('mdx_roles', 'id')->whereNull('deleted_at'),
                ]
            ],
            function (User $user, Request $req) {
                $user->password = Hash::make($req->password);
                $user->save();
                $user->roles()->attach($req->role_id);
            }
        );
    }


    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            User::class,
            $id,
            [
                'name' => 'sometimes|required|string|max:255',
                'username' => "sometimes|required|string|unique:users,username,{$id}",
                'email' => "sometimes|required|email|unique:users,email,{$id}",
                'password' => 'sometimes|required|string|min:8',
                'role_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('mdx_roles', 'id')->whereNull('deleted_at'),
                ]
            ],
            function (User $user, Request $req) {
                if ($req->filled('password')) {
                    $user->password = Hash::make($req->password);
                }
                $user->save();
                if ($req->has('role_id')) {
                    $user->roles()->sync($req->role_id);
                }
            }
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            User::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(User::class, $id);
    }
}

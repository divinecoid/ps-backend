<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name
            ])
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

    public function show($id)
    {
        $user = $this->baseShow(
            User::class,
            $id,
            ['roles'],
            $this->structure()
        );
        return $user;
    }

    public function store(Request $request)
    {
        $result = $this->baseStore(
            $request,
            User::class,
            [
                'username' => 'required|string|unique:users,username',
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:8',
                'role_id' => 'required|exists:mdx_roles,id'
            ],
            function (User $user, Request $req) {
                $user->password = Hash::make($req->password);
                $user->save();
                $user->roles()->attach($req->role_id);
            }
        );
        return $result;
    }


    public function update(Request $request, $id)
    {
        $result = $this->baseUpdate(
            $request,
            User::class,
            $id,
            [
                'name' => 'sometimes|required|string|max:255',
                'username' => "sometimes|required|string|unique:users,username,{$id}",
                'email' => "sometimes|required|email|unique:users,email,{$id}",
                'password' => 'sometimes|required|string|min:8',
            ],
            function (User $user, Request $req) {
                $user->password = Hash::make($req->password);
            }
        );
        return $result;
    }

    public function destroy($id)
    {
        $result = $this->baseDelete(
            User::class,
            $id
        );
        return $result;
    }
}
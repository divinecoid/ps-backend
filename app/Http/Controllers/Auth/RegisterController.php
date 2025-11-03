<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    use CrudTrait;
    public function __invoke(Request $request)
    {
        return $this->baseStore(
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
    }
}
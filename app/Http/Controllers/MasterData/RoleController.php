<?php

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiFilterTrait;


class RoleController extends Controller
{
    use ApiFilterTrait;

    public function index(Request $request)
    {
        $perPage = (int)($request->input('per_page', $this->getPerPageDefault()));
        $query = Role::query();
        $query = $this->applyFilter($query,$request,['name','description']);
        $data = $query->paginate($perPage);
        
    }

}
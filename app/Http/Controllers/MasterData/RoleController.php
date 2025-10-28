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
        $items = collect($data->items());
        return response()->json($this->paginateResponse($data, $items));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $role = Role::create($request->only(['name', 'description']));
        return $this->successResponse($role);
    }

    public function show($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return $this->errorResponse(404);
        }
    }

}
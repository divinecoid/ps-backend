<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

trait CrudTrait
{
    use ApiFilterTrait;

    public function baseIndex(Request $request, $model, array $relations = [], array $filters = [], ?callable $map = null)
    {
        $perPage = (int) ($request->input('per_page', $this->getPerPageDefault()));
        $query = $model::query()->with($relations);

        if (method_exists($this, 'applyFilter') && !empty($filters)) {
            $query = $this->applyFilter($query, $request, $filters);
        }

        $data = $query->paginate($perPage);

        $items = collect($data->items())->map($map ?? fn($item) => $item);
        return response()->json($this->paginateResponse($data, $items));
    }

    public function baseMaster(Request $request, $model, array $relations = [], array $filters = [], ?callable $map = null)
    {
        $perPage = (int) ($request->input('per_page', $this->getPerPageDefault()));
        $query = $model::withTrashed()->with($relations);

        if (method_exists($this, 'applyFilter') && !empty($filters)) {
            $query = $this->applyFilter($query, $request, $filters);
        }

        $data = $query->paginate($perPage);

        $items = collect($data->items())->map(function ($item) use ($map) {
            $base = $map ? $map($item) : $item;
            return [
                ...$base,
                'is_deleted' => $item->deleted_at !== null,
            ];
        });
        return response()->json($this->paginateResponse($data, $items));
    }

    public function baseShow($model, $id, array $relations = [], ?callable $map = null)
    {
        $item = $model::with($relations)->find($id);
        if (!$item) {
            return $this->errorResponse(404, "Not found");
        }
        return $this->successResponse($map ? $map($item) : $item);
    }

    public function baseStore(Request $request, $model, array $rules, ?callable $afterCreate = null)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->errorResponse(422, $validator->errors()->first());
        }
        return DB::transaction(function () use ($request, $model, $afterCreate) {
            $data = $model::create($request->only(array_keys($request->all())));

            if ($afterCreate) {
                $afterCreate($data, $request);
            }
            return $this->successResponse($data);
        });
    }

    public function baseUpdate(Request $request, $model, $id, array $rules, ?callable $afterUpdate = null)
    {
        $record = $model::find($id);
        if (!$record) {
            return $this->errorResponse(404, 'Not found');
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->errorResponse(422, $validator->errors()->first());
        }

        return DB::transaction(function () use ($request, $record, $afterUpdate) {
            $record->update($request->only(array_keys($request->all())));

            if ($afterUpdate) {
                $afterUpdate($record, $request);
            }
            return $this->successResponse($record);
        });
    }

    public function baseDelete($model, $id)
    {
        $item = $model::find($id);
        if (!$item) {
            return $this->errorResponse(404, 'Not found');
        }
        $item->delete();
        return $this->successResponse($item);
    }

    public function baseRestore($model, $id)
    {
        $item = $model::onlyTrashed()->find($id);
        if (!$item) {
            return $this->errorResponse(404, 'Not found or already active');
        }
        $item->restore();
        return $this->successResponse($item);
    }

    public function baseValidate(Request $request, array $rules, ?callable $afterValidate = null)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->errorResponse(422, $validator->errors()->first());
        }
        $data = $request->only(array_keys($request->all()));

        if ($afterValidate) {
            return $afterValidate($data, $request);
        }
    }

    public function baseRelationIndex(Request $request, $relationQuery, array $filters = [], ?callable $map = null)
    {
        $perPage = (int) ($request->input('per_page', $this->getPerPageDefault()));
        $query = $relationQuery;

        if (method_exists($this, 'applyFilter') && !empty($filters)) {
            $query = $this->applyFilter($query, $request, $filters);
        }

        $data = $query->paginate($perPage);

        $items = collect($data->items())->map($map ?? fn($item) => $item);

        return response()->json($this->paginateResponse($data, $items));
    }

}

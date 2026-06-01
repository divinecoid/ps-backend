<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

trait CrudTrait
{
    use ApiFilterTrait;

    public function baseIndex(
        Request $request,
        $model,
        array $relations = [],
        array $filters = [],
        ?callable $map = null,
        ?callable $queryCallback = null,
        ?array $defaultSort = null
    ) {
        $perPage = (int) ($request->input('per_page', $this->getPerPageDefault()));
        $query = $model::query()->with($relations);

        if (is_callable($queryCallback)) {
            $queryCallback($query);
        }

        if (method_exists($this, 'applyFilter') && !empty($filters)) {
            $query = $this->applyFilter($query, $request, $filters, $defaultSort);
        }

        $data = $query->paginate($perPage);
        $items = collect($data->items())->map($map ?? fn($item) => $item);

        return response()->json($this->paginateResponse($data, $items));
    }


    public function baseMaster(
        Request $request,
        $model,
        array $relations = [],
        array $filters = [],
        ?callable $map = null,
        ?callable $queryCallback = null,
        ?array $defaultSort = null
    ) {
        $perPage = (int) ($request->input('per_page', $this->getPerPageDefault()));
        $query = $model::withTrashed()->with($relations);

        if (is_callable($queryCallback)) {
            $queryCallback($query);
        }

        if (method_exists($this, 'applyFilter') && !empty($filters)) {
            $query = $this->applyFilter($query, $request, $filters, $defaultSort);
        }

        $data = $query->paginate($perPage);

        $items = collect($data->items())->map(function ($item) use ($map) {
            $base = $map ? $map($item) : $item;
            return [
                ...$base,
                'deleted_at' => $item->deleted_at !== null,
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

            // Audit Log
            $module = \App\Support\AuditLogger::mapModelToModule($model);
            \App\Support\AuditLogger::log($module, 'CREATE', "Membuat data baru dengan ID: {$data->id}");

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

            // Audit Log
            $module = \App\Support\AuditLogger::mapModelToModule(get_class($record));
            \App\Support\AuditLogger::log($module, 'UPDATE', "Mengubah data dengan ID: {$record->id}");

            return $this->successResponse($record);
        });
    }

    public function baseDelete($model, $id)
    {
        if (is_array($id)) {
            $items = [];
            $deletedIds = [];
            foreach ($id as $i) {
                $item = $model::find($i);
                if (!$item)
                    continue;
                $items[] = $item;
                $item->delete();
                $deletedIds[] = $i;
            }
            if (count($items) > 0) {
                $module = \App\Support\AuditLogger::mapModelToModule($model);
                \App\Support\AuditLogger::log($module, 'DELETE', "Menghapus data dengan ID: " . implode(', ', $deletedIds));
            }
            if (count($items) > 0 && count($id) == count($items)) {
                return $this->successResponse($items, 'Success delete all data');
            } else if (count($items) > 0 && count($id) != count($items)) {
                return $this->successResponse($items, 'Success delete some data');
            } else if (count($items) == 0 && count($id) != count($items)) {
                return $this->errorResponse(400, 'Failed to delete data');
            } else {
                return $this->errorResponse(404, 'Not found');
            }
        } else {
            $item = $model::find($id);
            if (!$item) {
                return $this->errorResponse(404, 'Not found');
            }
            $item->delete();

            // Audit Log
            $module = \App\Support\AuditLogger::mapModelToModule($model);
            \App\Support\AuditLogger::log($module, 'DELETE', "Menghapus data dengan ID: {$id}");

            return $this->successResponse($item);
        }
    }

    public function baseForceDelete($model, $id)
    {
        if (is_array($id)) {
            $items = [];
            $deletedIds = [];
            foreach ($id as $i) {
                $item = $model::withTrashed()->find($i);
                if (!$item)
                    continue;
                $items[] = $item;
                $item->forceDelete();
                $deletedIds[] = $i;
            }
            if (count($items) > 0) {
                $module = \App\Support\AuditLogger::mapModelToModule($model);
                \App\Support\AuditLogger::log($module, 'FORCE_DELETE', "Menghapus permanen data dengan ID: " . implode(', ', $deletedIds));
            }
            if (count($items) > 0 && count($id) == count($items)) {
                return $this->successResponse($items, 'Success force delete all data');
            } else if (count($items) > 0 && count($id) != count($items)) {
                return $this->successResponse($items, 'Success force delete some data');
            } else if (count($items) == 0 && count($id) != count($items)) {
                return $this->errorResponse(400, 'Failed to force delete data');
            } else {
                return $this->errorResponse(404, 'Not found');
            }
        } else {
            $item = $model::withTrashed()->find($id);
            if (!$item) {
                return $this->errorResponse(404, 'Not found');
            }
            $item->forceDelete();

            // Audit Log
            $module = \App\Support\AuditLogger::mapModelToModule($model);
            \App\Support\AuditLogger::log($module, 'FORCE_DELETE', "Menghapus permanen data dengan ID: {$id}");

            return $this->successResponse($item);
        }
    }

    public function baseRestore($model, $id)
    {
        $item = $model::onlyTrashed()->find($id);
        if (!$item) {
            return $this->errorResponse(404, 'Not found or already active');
        }
        $item->restore();

        // Audit Log
        $module = \App\Support\AuditLogger::mapModelToModule($model);
        \App\Support\AuditLogger::log($module, 'RESTORE', "Mengembalikan data terhapus dengan ID: {$id}");

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
            return $afterValidate($data);
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

<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait ApiFilterTrait
{
    public function applyFilter($query, $request, $searchFields = [], ?array $defaultSort = null)
    {

        foreach ($searchFields as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search, $searchFields) {
                foreach ($searchFields as $field) {
                    if (str_contains($field, '.')) {
                        $parts = explode('.', $field);
                        $column = array_pop($parts);
                        $relation = implode('.', $parts);
                        $q->orWhereHas($relation, function ($qr) use ($column, $search) {
                            $qr->where($column, 'LIKE', "%{$search}%");
                        });
                    } else {
                        $q->orWhere($field, 'LIKE', "%{$search}%");
                    }
                }
            });
        }
        if ($sort = $request->input('sort')) {
            $sortArray = explode(';', $sort);
            foreach ($sortArray as $sortItem) {
                $sortParts = explode(',', $sortItem);
                if (count($sortParts) == 2) {
                    $column = trim($sortParts[0]);
                    $order = strtolower(trim($sortParts[1])) === 'desc' ? 'desc' : 'asc';
                    if (str_contains($column, '.')) {
                        $segments = explode('.', $column);
                        $relationName = $segments[0];
                        $relationColumn = $segments[1];
                        $model = $query->getModel();
                        $relation = $model->{$relationName}();
                        if ($relation instanceof BelongsTo) {
                            $parentTable = $model->getTable();
                            $relatedTable = $relation->getRelated()->getTable();

                            $query->leftJoin(
                                $relatedTable,
                                $relation->getQualifiedForeignKeyName(),
                                '=',
                                $relation->getQualifiedOwnerKeyName()
                            );

                            $query->orderBy("$relatedTable.$relationColumn", $order)
                                ->select("$parentTable.*");

                        }

                    } else {
                        $query->orderBy($column, $order);
                    }
                }
            }
        } elseif ($request->filled('sort_by')) {
            $sortBy = $request->input('sort_by');
            $order = $request->input('order', 'asc');
            $query->orderBy($sortBy, $order);
        } elseif ($defaultSort) {
            foreach ($defaultSort as $column => $direction) {
                if (is_int($column)) {
                    $col = $defaultSort[0] ?? null;
                    $dir = $defaultSort[1] ?? 'asc';
                    if ($col) {
                        $query->orderBy($col, $dir);
                    }
                    break;
                } else {
                    $query->orderBy($column, $direction);
                }
            }
        }
        return $query;
    }

    public function paginateResponse($data, $items)
    {
        return [
            'success' => true,
            'message' => "Success retrieved data",
            'data' => $items,
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'last_page' => $data->lastPage(),
                'total' => $data->total(),
            ],
        ];
    }

    public function getPerPageDefault()
    {
        return getenv("PER_PAGE_DEFAULT") ?: 100;
    }

    public function successResponse($data, $message = "Success")
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public function errorResponse($code = 400, $message = "Error", $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public function getMaxTokens()
    {
        return getenv("PER_USER_TOKENS") ?: 5;
    }
}
<?php

namespace App\Http\Traits;

trait ApiFilterTrait
{
    public function applyFilter($query, $request, $searchFields = [])
    {
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search, $searchFields) {
                foreach($searchFields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }
        if ($sort = $request->input('sort')) {
            $sortArray = explode(';', $sort);
            foreach($sortArray as $sortItem) {
                $sortParts = explode(',', $sortItem);
                if (count($sortParts) == 2) {
                    $column = trim($sortParts[0]);
                    $order = trim($sortParts[1]);
                    $query->orderBy($column, $order);
                }
            }
        } else {
            $sortBy = $request->input('sort_by', 'id');
            $order = $request->input('order', 'asc');
            $query->orderBy($sortBy, $order);
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


    public function successResponse($data, $message="Success")
    {
        return response()->json([
            'success'=>true,
            'message'=>$message,
            'data' => $data
        ]);
    }

    public function errorResponse($code=400, $message="Error")
    {
        return response()->json([
            'success'=>false,
            'message'=>$message,
            'data'=>null
        ],$code);
    }

    public function getMaxTokens()
    {
        return getenv("PER_USER_TOKENS") ?: 5;
    }
}
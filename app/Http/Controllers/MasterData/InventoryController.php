<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Inventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use CrudTrait;

    /**
     * One row per model + color + size + rack: inventory rows that share all
     * four (e.g. two receipts that raced to create the same row) are shown
     * as one, with their series merged and quantities summed. The row keeps
     * the oldest id so the detail link still works.
     */
    private function mergedStructure($group)
    {
        $first = $group->first();
        $detail = $group->flatMap(fn($i) => $i->detail)
            ->groupBy('series')
            ->map(fn($rows, $series) => ['series' => $series, 'quantity' => (int) $rows->sum('quantity')])
            ->values();

        return [
            'id' => $first->id,
            'model_id' => $first->model_id,
            'model' => (object) ['name' => $first->model->name],
            'color_id' => $first->color_id,
            'color' => (object) ['name' => $first->color->name],
            'size_id' => $first->size_id,
            'size' => (object) ['name' => $first->size->name],
            'rack_id' => $first->rack_id,
            'rack' => $first->rack ? (object) [
                'code' => $first->rack->code,
                'name' => $first->rack->name
            ] : null,
            'detail' => $detail,
            'total' => (int) $group->sum(fn($i) => (int) ($i->detail_sum_quantity ?? 0)),
        ];
    }

    private function mergedList(Request $request, bool $withTrashed)
    {
        $filters = ['model_id', 'model.name', 'color_id', 'color.name', 'size_id', 'size.name', 'rack_id', 'rack.code', 'rack.name'];
        $perPage = (int) $request->input('per_page', $this->getPerPageDefault());
        $page = max(1, (int) $request->input('page', 1));

        $query = ($withTrashed ? Inventory::withTrashed() : Inventory::query())
            ->with(['model', 'color', 'size', 'rack', 'detail'])
            ->withSum('detail', 'quantity')
            ->orderBy('created_at');
        // Sorting happens after merging (a row's total only exists then).
        $this->applyFilter($query, $request->duplicate(array_diff_key($request->query(), ['sort' => 1, 'sort_by' => 1])), $filters);

        $rows = $query->get()
            ->groupBy(fn($i) => implode('|', [$i->model_id, $i->color_id, $i->size_id, $i->rack_id ?? '']))
            ->map(function ($group) use ($withTrashed) {
                $row = $this->mergedStructure($group);
                if ($withTrashed) {
                    $row['deleted_at'] = $group->every(fn($i) => $i->deleted_at !== null);
                }
                return $row;
            })
            ->values();

        if ($sort = $request->input('sort')) {
            foreach (array_reverse(explode(';', $sort)) as $item) {
                [$column, $order] = array_pad(explode(',', $item), 2, 'asc');
                $descending = strtolower(trim($order)) === 'desc';
                $rows = $rows->sortBy(fn($r) => match (trim($column)) {
                    'model.name' => $r['model']->name,
                    'color.name' => $r['color']->name,
                    'size.name' => $r['size']->name,
                    'rack.code', 'rack.name', 'rack_id' => $r['rack']->code ?? '',
                    'total' => $r['total'],
                    default => 0,
                }, SORT_NATURAL | SORT_FLAG_CASE, $descending)->values();
            }
        }

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page
        );

        return response()->json($this->paginateResponse($paginator, $paginator->items()));
    }

    public function index(Request $request)
    {
        return $this->mergedList($request, false);
    }

    public function master(Request $request)
    {
        return $this->mergedList($request, true);
    }

    public function show($id)
    {
        $inventory = Inventory::find($id);
        if (!$inventory) {
            return $this->errorResponse(404, "Not found");
        }

        $group = Inventory::with(['model', 'color', 'size', 'rack', 'detail'])
            ->withSum('detail', 'quantity')
            ->where([
                'model_id' => $inventory->model_id,
                'color_id' => $inventory->color_id,
                'size_id' => $inventory->size_id,
            ])
            ->when(
                $inventory->rack_id === null,
                fn($q) => $q->whereNull('rack_id'),
                fn($q) => $q->where('rack_id', $inventory->rack_id)
            )
            ->orderBy('created_at')
            ->get();

        return $this->successResponse($this->mergedStructure($group));
    }

    public function store(Request $request)
    {
        // return $this->baseStore(
        //     $request,
        //     Inventory::class,
        //     [
        //         'serial_number' => 'required|string|unique:mdx_inventories,serial_number|max:255',
        //         'product_id' => [
        //             'required',
        //             Rule::exists('mdx_products', 'id')->whereNull('deleted_at'),
        //         ],
        //         'factory_id' => [
        //             'required',
        //             Rule::exists('mdx_factories', 'id')->whereNull('deleted_at'),
        //         ],
        //         'quantity' => 'required|integer|min:1',
        //         'cmt_id' =>
        //             [
        //                 'required',
        //                 Rule::exists('mdx_cmts', 'id')->whereNull('deleted_at'),
        //             ],
        //         'rack_id' => [
        //             'required',
        //             Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
        //         ],
        //         'barcode_group' => 'nullable|string|max:255',
        //     ],
        //     null
        // );
    }

    public function update(Request $request, $id)
    {
        // return $this->baseUpdate(
        //     $request,
        //     Inventory::class,
        //     $id,
        //     [
        //         'serial_number' => [
        //             'required',
        //             'string',
        //             'max:255',
        //             Rule::unique('mdx_inventories', 'serial_number')->ignore($id)
        //         ],
        //         'product_id' => [
        //             'required',
        //             Rule::exists('mdx_products', 'id')->whereNull('deleted_at'),
        //         ],
        //         'factory_id' => [
        //             'required',
        //             Rule::exists('mdx_factories', 'id')->whereNull('deleted_at'),
        //         ],
        //         'quantity' => 'required|integer|min:1',
        //         'cmt_id' =>
        //             [
        //                 'required',
        //                 Rule::exists('mdx_cmts', 'id')->whereNull('deleted_at'),
        //             ],
        //         'rack_id' => [
        //             'required',
        //             Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
        //         ],
        //         'barcode_group' => 'nullable|string|max:255',
        //     ],
        //     null
        // );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Inventory::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Inventory::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Inventory::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Inventory::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Inventory::class,
            $request->all()
        );
    }
}

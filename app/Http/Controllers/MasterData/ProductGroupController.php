<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductGroup;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'model_id' => $data->model_id,
            'model' => (object) [
                'name' => $data->model->name
            ],
            'color' => $data->color ? (object) [
                'name' => $data->color->name
            ] : null,
            'size' => $data->size ? (object) [
                'name' => $data->size->name
            ] : null,
            'series' => $data->series,
            'qty' => (int) $data->qty,
        ];
    }

    private function productStructure()
    {
        return fn($data) => [
            'id' => $data->id,
            'rack_id' => $data->rack_id,
            'model_id' => $data->model_id,
            'model' => (object) [
                'name' => $data->model->name
            ],
            'rack' => (object) [
                'name' => $data->rack->name
            ],
            'color' => $data->color ? (object) [
                'name' => $data->color->name
            ] : null,
            'size' => $data->size ? (object) [
                'name' => $data->size->name
            ] : null,
            'barcode' => $data->barcode,
            'series' => $data->series
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            ProductGroup::class,
            ['model', 'color', 'size'],
            ['series', 'model.name'],
            $this->structure(),
            function ($query) {
                $query->orderByDesc('created_at');
            }
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            ProductGroup::class,
            ['model', 'color', 'size'],
            ['series', 'model.name'],
            $this->structure(),
            function ($query) {
                $query->orderByDesc('created_at');
            }
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            ProductGroup::class,
            $id,
            ['model', 'color', 'size'],
            $this->structure()
        );
    }

    // Paginated list of the individual mdx_products rows belonging to this group.
    // mdx_products has no FK back to mdx_product_groups (left untouched by design),
    // so rows are matched by the group's model/color/size/series.
    public function products(Request $request, $id)
    {
        $group = ProductGroup::findOrFail($id);

        return $this->baseIndex(
            $request,
            Product::class,
            ['rack', 'model', 'color', 'size'],
            ['barcode'],
            $this->productStructure(),
            function ($query) use ($group) {
                $query->where('model_id', $group->model_id)->where('series', $group->series);
                $group->color_id ? $query->where('color_id', $group->color_id) : $query->whereNull('color_id');
                $group->size_id ? $query->where('size_id', $group->size_id) : $query->whereNull('size_id');
                $query->orderByDesc('created_at');
            }
        );
    }
}

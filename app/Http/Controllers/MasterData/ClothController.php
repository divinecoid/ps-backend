<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Cloth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClothController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'factory_id' => $data->factory_id,
            'factory' => $data->factory,
            'gram' => $data->gram,
            'color_id' => $data->color_id,
            'color' => $data->color,
            'roll_size_id' => $data->roll_size_id,
            'roll_size' => $data->roll_size,
            'quantity' => $data->quantity,
            'sequence' => $data->sequence
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Cloth::class,
            ['factory', 'color', 'roll_size'],
            ['id', 'factory', 'gram', 'color', 'quantity', 'sequence'],
            $this->structure(),
        );
    }
    public function uncut(Request $request)
    {
        return $this->baseIndex(
            $request,
            Cloth::class,
            ['factory', 'color', 'roll_size'],
            ['id', 'factory', 'gram', 'color', 'quantity', 'sequence'],
            $this->structure(),
            queryCallback: function ($query) {
                $query->where('quantity', '>', 0);
            }
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Cloth::class,
            ['factory', 'color', 'roll_size'],
            ['id', 'factory', 'gram', 'color', 'quantity', 'sequence'],
            $this->structure(),
            queryCallback: function ($query) {
                $query->where('quantity', '>', 0);
            }
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Cloth::class,
            $id,
            ['factory', 'color', 'roll_size'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Cloth::class,
            [
                'factory_id' => 'required|string|unique:mdx_factories,name|max:255',
                'gram' => 'required|string|max:255',
                'roll_size_id' => 'required|string|unique:mdx_roll_sizes,size|max:255',
                'color_id' => 'required|string|unique:mdx_colors,name|max:255',
                'quantity' => 'required|integer|max:255',
                'sequence' => 'required|string|max:255',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Cloth::class,
            $id,
            [
                'sequence' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_clothes', 'sequence')->ignore($id)
                ],
                'factory_id' => 'required|string|unique:mdx_factories,name|max:255',
                'gram' => 'required|string|max:255',
                'roll_size_id' => 'required|string|unique:mdx_roll_sizes,size|max:255',
                'color_id' => 'required|string|unique:mdx_colors,name|max:255',
                'quantity' => 'required|integer|max:255',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Cloth::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Cloth::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Cloth::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Cloth::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Cloth::class,
            $request->all()
        );
    }
}

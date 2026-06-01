<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Color;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColorController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Color::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Color::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Color::class,
            $id,
            [],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Color::class,
            [
                'code' => 'required|string|unique:mdx_colors,code|max:255',
                'name' => 'required|string|max:255',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Color::class,
            $id,
            [
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_colors', 'code')->ignore($id)
                ],
                'name' => 'required|string|max:255',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Color::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Color::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Color::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Color::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Color::class,
            $request->all()
        );
    }
}

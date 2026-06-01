<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Sequence;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SequenceController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'color_id' => $data->color_id,
            'color' => $data->color,
            'format' => $data->format,
            'current' => $data->current,
            'limit' => $data->limit,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Sequence::class,
            ['color'],
            ['color.name', 'format', 'current', 'limit'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Sequence::class,
            ['color'],
            ['color.name', 'format'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Sequence::class,
            $id,
            ['color'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Sequence::class,
            [
                'color_id' => 'required|uuid|exists:mdx_colors,id',
                'format' => 'required|string|max:255',
                'current' => 'required|integer|min:0',
                'limit' => 'required|integer|min:1',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Sequence::class,
            $id,
            [
                'color_id' => 'required|uuid|exists:mdx_colors,id',
                'format' => 'required|string|max:255',
                'current' => 'required|integer|min:0',
                'limit' => 'required|integer|min:1',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Sequence::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Sequence::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Sequence::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Sequence::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Sequence::class,
            $request->all()
        );
    }
}

<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Configuration;
use App\Models\MasterData\ConfigurationHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'config_key' => $data->config_key,
            'config_value' => $data->config_value,
            'data_type' => $data->data_type,
            'description' => $data->description,
            'created_by' => $data->createdBy ? [
                'id' => $data->createdBy->id,
                'name' => $data->createdBy->name,
            ] : null,
            'updated_by' => $data->updatedBy ? [
                'id' => $data->updatedBy->id,
                'name' => $data->updatedBy->name,
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Configuration::class,
            ['createdBy', 'updatedBy'],
            ['config_key', 'data_type', 'description'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Configuration::class,
            ['createdBy', 'updatedBy'],
            ['config_key', 'data_type', 'description'],
            $this->structure()
        );
    }

    public function show($id)
    {
        $item = Configuration::with(['createdBy', 'updatedBy', 'histories.changedBy'])->find($id);
        if (!$item) {
            return $this->errorResponse(404, 'Not found');
        }

        $data = ($this->structure())($item);
        $data['histories'] = $item->histories->map(fn($h) => [
            'id' => $h->id,
            'old_value' => $h->old_value,
            'new_value' => $h->new_value,
            'changed_by' => $h->changedBy ? [
                'id' => $h->changedBy->id,
                'name' => $h->changedBy->name,
            ] : null,
            'changed_at' => $h->changed_at,
        ]);

        return $this->successResponse($data);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'config_key' => 'required|string|max:100|unique:mdx_configurations,config_key',
            'config_value' => 'nullable|string',
            'data_type' => 'required|string|in:boolean,integer,decimal,string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, $validator->errors()->first());
        }

        return DB::transaction(function () use ($request) {
            $data = Configuration::create([
                ...$request->only(['config_key', 'config_value', 'data_type', 'description']),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return $this->successResponse($data);
        });
    }

    public function update(Request $request, $id)
    {
        $record = Configuration::find($id);
        if (!$record) {
            return $this->errorResponse(404, 'Not found');
        }

        $validator = Validator::make($request->all(), [
            'config_key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mdx_configurations', 'config_key')->ignore($id),
            ],
            'config_value' => 'nullable|string',
            'data_type' => 'required|string|in:boolean,integer,decimal,string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, $validator->errors()->first());
        }

        return DB::transaction(function () use ($request, $record) {
            $oldValue = $record->config_value;

            $record->update([
                ...$request->only(['config_key', 'config_value', 'data_type', 'description']),
                'updated_by' => auth()->id(),
            ]);

            // Record history only if the value actually changed
            if ($oldValue !== $request->input('config_value')) {
                ConfigurationHistory::create([
                    'configuration_id' => $record->id,
                    'old_value' => $oldValue,
                    'new_value' => $request->input('config_value'),
                    'changed_by' => auth()->id(),
                    'changed_at' => now(),
                ]);
            }

            return $this->successResponse($record);
        });
    }

    public function destroy($id)
    {
        return $this->baseDelete(Configuration::class, $id);
    }

    public function restore($id)
    {
        return $this->baseRestore(Configuration::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(Configuration::class, $request->all());
    }

    public function histories(Request $request, $id)
    {
        $config = Configuration::find($id);
        if (!$config) {
            return $this->errorResponse(404, 'Not found');
        }

        return $this->baseRelationIndex(
            $request,
            $config->histories()->with('changedBy'),
            [],
            fn($h) => [
                'id' => $h->id,
                'old_value' => $h->old_value,
                'new_value' => $h->new_value,
                'changed_by' => $h->changedBy ? [
                    'id' => $h->changedBy->id,
                    'name' => $h->changedBy->name,
                ] : null,
                'changed_at' => $h->changed_at,
            ]
        );
    }
}

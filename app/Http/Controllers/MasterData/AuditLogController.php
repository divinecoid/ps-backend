<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id'         => $data->id,
            'user'       => $data->user ? [
                'id'       => $data->user->id,
                'name'     => $data->user->name,
                'username' => $data->user->username,
            ] : null,
            'module'     => $data->module,
            'action'     => $data->action,
            'details'    => $data->details,
            'ip_address' => $data->ip_address,
            'user_agent' => $data->user_agent,
            'created_at' => $data->created_at ? $data->created_at->toIso8601String() : null,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            AuditLog::class,
            ['user'],
            ['module', 'action', 'details', 'ip_address', 'user.name', 'user.username'],
            $this->structure(),
            null,
            ['created_at' => 'desc']
        );
    }
}

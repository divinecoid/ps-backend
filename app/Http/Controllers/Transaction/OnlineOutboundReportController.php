<?php

namespace App\Http\Controllers\Transaction;

use App\Exports\OnlineOutboundExport;
use App\Http\Controllers\Controller;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use App\Services\OnlineOutboundReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** "Laporan Barang Keluar Online". */
class OnlineOutboundReportController extends Controller
{
    public function index(Request $request, OnlineOutboundReport $report)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $report->build($this->filters($request)),
        ]);
    }

    public function export(Request $request, OnlineOutboundReport $report)
    {
        $filters = $this->filters($request);
        $name = 'barang-keluar-online_' . ($filters['date_from'] ?? 'awal') . '_' . ($filters['date_to'] ?? 'akhir') . '.xlsx';

        return Excel::download(new OnlineOutboundExport($report->build($filters)), $name);
    }

    /** Dropdown choices; only names, so warehouse staff don't need master-data access. */
    public function options()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'marketplaces' => Marketplace::orderBy('name')->get(['id', 'name']),
                'stores' => OnlineStore::orderBy('store_name')->get(['id', 'store_name', 'marketplace_id']),
            ],
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'marketplace_id' => 'nullable|string',
            'online_store_id' => 'nullable|string',
            'search' => 'nullable|string|max:100',
        ]);
    }
}

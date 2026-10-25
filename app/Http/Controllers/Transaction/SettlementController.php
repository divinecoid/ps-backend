<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Jobs\ProcessSettlementImportJob;
use App\Models\Transactions\BankTransaction;
use App\Models\Transactions\SettlementEntry;
use App\Models\Transactions\SettlementImport;
use App\Services\SettlementReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class SettlementController extends Controller
{
    use CrudTrait;

    public function __construct(private SettlementReconciliationService $service)
    {
    }

    private function filters(Request $request): array
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'online_store_id' => 'nullable|uuid',
            'marketplace_id' => 'nullable|uuid',
            'status' => 'nullable|in:matched,pending,discrepancy,unusual_fee',
            'search' => 'nullable|string|max:100',
        ]);

        return $request->only(['date_from', 'date_to', 'online_store_id', 'marketplace_id', 'status', 'search']);
    }

    public function summary(Request $request)
    {
        return $this->successResponse($this->service->summary($this->filters($request)));
    }

    public function entries(Request $request)
    {
        $filters = $this->filters($request);
        $perPage = min((int) $request->input('per_page', 25), 100);

        $page = $this->service->entryQuery($filters)
            ->with('online_store:id,store_name,marketplace_id', 'online_store.marketplace:id,name')
            ->orderByRaw("CASE status WHEN 'discrepancy' THEN 0 WHEN 'unusual_fee' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
            ->orderByDesc('settlement_date')
            ->select('id', 'online_store_id', 'order_sn', 'payout_ref', 'order_date', 'settlement_date', 'gross_amount', 'seller_discount',
                'commission_fee', 'service_fee', 'event_fee', 'shipping_fee', 'refund_amount', 'other_fee', 'adjustment_amount',
                'reported_net', 'expected_net', 'difference', 'status', 'flags')
            ->paginate($perPage);

        return response()->json($this->paginateResponse($page, $page->items()));
    }

    public function showEntry(string $id)
    {
        return $this->successResponse(SettlementEntry::with('online_store:id,store_name', 'import:id,file_name,created_at')->findOrFail($id));
    }

    public function payouts(Request $request)
    {
        return $this->successResponse($this->service->payouts($this->filters($request)));
    }

    public function imports()
    {
        return $this->successResponse(
            SettlementImport::with('online_store:id,store_name')->orderByDesc('created_at')->limit(50)->get()
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:xlsx,xls,csv,txt',
            'file_type' => 'required|in:settlement,bank',
            'online_store_id' => 'required_if:file_type,settlement|nullable|uuid|exists:mdx_online_stores,id',
        ]);

        $file = $request->file('file');
        $type = $request->input('file_type');
        $storeId = $type === 'settlement' ? $request->input('online_store_id') : null;
        $hash = hash_file('sha256', $file->getRealPath());

        $duplicate = SettlementImport::where('file_hash', $hash)->where('file_type', $type)
            ->where('online_store_id', $storeId)->whereIn('status', ['queued', 'processing', 'done'])->first();
        if ($duplicate) {
            return $this->errorResponse(409, 'File yang sama sudah pernah diimpor (' . $duplicate->file_name . ').');
        }

        $store = $storeId ? \App\Models\MasterData\OnlineStore::find($storeId) : null;
        $import = SettlementImport::create([
            'online_store_id' => $storeId,
            'marketplace_id' => $store?->marketplace_id,
            'file_type' => $type,
            'file_name' => $file->getClientOriginalName(),
            'file_hash' => $hash,
            'status' => 'queued',
            'user_id' => JWTAuth::parseToken()->authenticate()?->id,
        ]);

        $ext = strtolower($file->getClientOriginalExtension()) ?: 'csv';
        $path = Storage::disk('local')->putFileAs('settlement-imports', $file, $import->id . '.' . $ext);
        $import->update(['file_path' => $path]);

        ProcessSettlementImportJob::dispatch($import->id);

        return $this->successResponse($import->fresh(), 'File diterima dan sedang diproses.');
    }

    public function reconcileBank()
    {
        return $this->successResponse(['linked' => $this->service->reconcileBank()], 'Pencocokan bank dijalankan ulang.');
    }

    /** Remove a mistaken import together with the rows it created. */
    public function destroyImport(string $id)
    {
        $import = SettlementImport::findOrFail($id);

        DB::transaction(function () use ($import) {
            SettlementEntry::where('import_id', $import->id)->delete();
            BankTransaction::where('import_id', $import->id)->delete();
            $import->delete();
        });
        if ($import->file_path) {
            Storage::disk('local')->delete($import->file_path);
        }

        return $this->successResponse(null, 'Import dihapus.');
    }

    /** CSV of every entry needing review (discrepancy / unusual fee) in the filter. */
    public function export(Request $request)
    {
        $filters = $this->filters($request);

        return new StreamedResponse(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Toko', 'No Pesanan', 'Payout', 'Tgl Settlement', 'Nilai Pesanan', 'Diskon Penjual', 'Komisi', 'Layanan',
                'Event/Program', 'Ongkir', 'Refund', 'Biaya Lain', 'Penyesuaian', 'Perkiraan Bersih', 'Bersih Dilaporkan', 'Selisih', 'Status', 'Catatan']);

            $q = $this->service->entryQuery($filters)->with('online_store:id,store_name');
            if (empty($filters['status'])) {
                $q->whereIn('status', ['discrepancy', 'unusual_fee']);
            }
            $q->orderBy('settlement_date')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $e) {
                    fputcsv($out, [$e->online_store?->store_name, $e->order_sn, $e->payout_ref, $e->settlement_date?->format('Y-m-d'),
                        $e->gross_amount, $e->seller_discount, $e->commission_fee, $e->service_fee, $e->event_fee, $e->shipping_fee,
                        $e->refund_amount, $e->other_fee, $e->adjustment_amount, $e->expected_net, $e->reported_net, $e->difference,
                        $e->status, implode(',', $e->flags ?? [])]);
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekonsiliasi-settlement-' . now()->format('Ymd-His') . '.csv"',
        ]);
    }
}

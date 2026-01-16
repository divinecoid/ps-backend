<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\MasterData\CMT;
use App\Models\MasterData\Color;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use App\Models\MasterData\Warehouse;
use App\Models\Transactions\Receivedlog;
use App\Models\Transactions\ReceivedlogDetail;
use App\Models\Transactions\Request;
use App\Models\Transactions\RequestDetail;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class InboundController extends Controller
{
    /**
     * Process inbound receiving from scanned barcodes
     * 
     * Barcode format: CMT_CODE|REQUEST_DATE|MODEL|COLOR|SIZE|TYPE|SEQUENCE
     * Example: "CMT01|2026-01-08 16:25:35|LC|RED|L|DOZEN|1"
     */
    public function store(HttpRequest $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'barcodes' => 'required|array|min:1',
            'barcodes.*' => 'required|string',
            'warehouse_id' => 'required|uuid|exists:mdx_warehouses,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $barcodes = $request->input('barcodes');
        $warehouseId = $request->input('warehouse_id');
        $notes = $request->input('notes');
        $userId = Auth::id();

        // Parse and validate barcodes
        $parsedItems = [];
        $errors = [];

        foreach ($barcodes as $index => $barcode) {
            try {
                $parsed = $this->parseBarcode($barcode);
                $parsed['original_barcode'] = $barcode;
                $parsedItems[] = $parsed;
            } catch (\Exception $e) {
                $errors[] = [
                    'barcode' => $barcode,
                    'index' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors) && empty($parsedItems)) {
            return response()->json([
                'success' => false,
                'message' => 'All barcodes failed to parse',
                'errors' => $errors
            ], 400);
        }

        // Group items by request (CMT + Date)
        $groupedByRequest = collect($parsedItems)->groupBy(function ($item) {
            return $item['cmt_code'] . '|' . $item['request_date'];
        });

        DB::beginTransaction();
        try {
            $createdReceivedlogs = [];
            $processedCount = 0;

            foreach ($groupedByRequest as $groupKey => $items) {
                $firstItem = $items->first();

                // Find the request
                $trxRequest = $this->findRequest($firstItem['cmt_code'], $firstItem['request_date']);

                if (!$trxRequest) {
                    foreach ($items as $item) {
                        $errors[] = [
                            'barcode' => $item['original_barcode'],
                            'error' => "Request not found for CMT: {$firstItem['cmt_code']}, Date: {$firstItem['request_date']}"
                        ];
                    }
                    continue;
                }

                // Create receivedlog header
                $receivedlog = Receivedlog::create([
                    'request_id' => $trxRequest->id,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $userId,
                    'received_date' => now(),
                    'notes' => $notes
                ]);

                $receivedlogItems = [];

                // Process each item in this request group
                foreach ($items as $item) {
                    try {
                        // Find request detail that matches this item
                        $requestDetail = $this->findRequestDetail(
                            $trxRequest->id,
                            $item['model_code'],
                            $item['color_code'],
                            $item['size_code']
                        );

                        if (!$requestDetail) {
                            $errors[] = [
                                'barcode' => $item['original_barcode'],
                                'error' => "Request detail not found for Model: {$item['model_code']}, Color: {$item['color_code']}, Size: {$item['size_code']}"
                            ];
                            continue;
                        }

                        // Calculate quantity (DOZEN = 12 pieces, PIECE = 1 piece)
                        $qty = $item['type'] === 'DOZEN' ? 12 : 1;

                        // Create receivedlog detail
                        $detail = ReceivedlogDetail::create([
                            'receivedlog_id' => $receivedlog->id,
                            'request_detail_id' => $requestDetail->id,
                            'model_id' => $requestDetail->model_id,
                            'color_id' => $requestDetail->color_id,
                            'size_id' => $requestDetail->size_id,
                            'qty' => $qty,
                            'barcode' => $item['original_barcode']
                        ]);

                        // Update received quantity in request detail
                        $requestDetail->increment('rec_qty', $qty);

                        $receivedlogItems[] = [
                            'id' => $detail->id,
                            'model' => $item['model_code'],
                            'color' => $item['color_code'],
                            'size' => $item['size_code'],
                            'type' => $item['type'],
                            'qty' => $qty,
                            'sequence' => $item['sequence']
                        ];

                        $processedCount++;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'barcode' => $item['original_barcode'],
                            'error' => 'Failed to process: ' . $e->getMessage()
                        ];
                    }
                }

                $createdReceivedlogs[] = [
                    'id' => $receivedlog->id,
                    'request_id' => $trxRequest->id,
                    'cmt' => $firstItem['cmt_code'],
                    'warehouse' => $receivedlog->warehouse->name ?? 'Unknown',
                    'received_date' => $receivedlog->received_date->toISOString(),
                    'items_count' => count($receivedlogItems),
                    'items' => $receivedlogItems
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully processed {$processedCount} items from " . count($createdReceivedlogs) . " request(s)",
                'data' => [
                    'summary' => [
                        'total_scanned' => count($barcodes),
                        'total_processed' => $processedCount,
                        'total_failed' => count($errors),
                        'total_receivedlogs' => count($createdReceivedlogs)
                    ],
                    'receivedlogs' => $createdReceivedlogs
                ],
                'errors' => $errors
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process inbound receiving',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse barcode string into components
     * Format: CMT_CODE|REQUEST_DATE|MODEL|COLOR|SIZE|TYPE|SEQUENCE
     */
    private function parseBarcode(string $barcode): array
    {
        $parts = explode('|', $barcode);

        if (count($parts) !== 7) {
            throw new \Exception("Invalid barcode format. Expected 7 parts separated by '|', got " . count($parts));
        }

        return [
            'cmt_code' => trim($parts[0]),
            'request_date' => trim($parts[1]),
            'model_code' => trim($parts[2]),
            'color_code' => trim($parts[3]),
            'size_code' => trim($parts[4]),
            'type' => strtoupper(trim($parts[5])),
            'sequence' => trim($parts[6])
        ];
    }

    /**
     * Find request by CMT code and date
     */
    private function findRequest(string $cmtCode, string $requestDateStr): ?Request
    {
        // Find CMT by code
        $cmt = CMT::where('code', $cmtCode)->first();

        if (!$cmt) {
            return null;
        }

        // Parse the request date and find requests created on that date
        try {
            $requestDate = Carbon::parse($requestDateStr);

            // Find request for this CMT on the same day
            return Request::where('cmt_id', $cmt->id)
                ->whereDate('created_at', $requestDate->toDateString())
                ->where('status', 'OPEN')
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find request detail by request and product attributes
     */
    private function findRequestDetail(string $requestId, string $modelCode, string $colorCode, string $sizeCode): ?RequestDetail
    {
        // Find model, color, and size by their codes
        $model = ProductModel::where('sku', $modelCode)->first();
        $color = Color::where('code', $colorCode)->first();
        $size = Size::where('code', $sizeCode)->first();

        if (!$model || !$color || !$size) {
            return null;
        }

        // Find the request detail that matches all criteria
        return RequestDetail::where('request_id', $requestId)
            ->where('model_id', $model->id)
            ->where('color_id', $color->id)
            ->where('size_id', $size->id)
            ->first();
    }
}

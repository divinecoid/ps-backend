<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\TiktokShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class TiktokShopController extends Controller
{
    public function __construct(private readonly TiktokShopService $tiktokShopService)
    {
    }

    public function getShopCipher()
    {
        try {
            return response()->json($this->tiktokShopService->getShops());
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProduct(string $productId)
    {
        if (trim($productId) === '') {
            return response()->json([
                'error' => 'Missing required productId in URL',
            ], 400);
        }

        try {
            return response()->json($this->tiktokShopService->getProduct($productId));
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrderList(Request $request)
    {
        $pageSize = $request->integer('page_size');

        try {
            return response()->json($this->tiktokShopService->getOrderList($pageSize > 0 ? $pageSize : null));
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrder(string $orderId)
    {
        if (trim($orderId) === '') {
            return response()->json([
                'error' => 'Missing required orderId in URL',
            ], 400);
        }

        try {
            return response()->json($this->tiktokShopService->getOrder($orderId));
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPackageDetail(string $packageId)
    {
        if (trim($packageId) === '') {
            return response()->json([
                'error' => 'Missing required packageId in URL',
            ], 400);
        }

        try {
            return response()->json(
                $this->tiktokShopService->getPackageDetail($packageId),
            );
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPackageHandoverTimeSlots(string $packageId)
    {
        if (trim($packageId) === '') {
            return response()->json([
                'error' => 'Missing required packageId in URL',
            ], 400);
        }

        try {
            return response()->json(
                $this->tiktokShopService->getPackageHandoverTimeSlots($packageId),
            );
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function shipPackage(string $packageId, Request $request)
    {
        if (trim($packageId) === '') {
            return response()->json([
                'error' => 'Missing required packageId in URL',
            ], 400);
        }

        try {
            return response()->json(
                $this->tiktokShopService->shipPackage(
                    $packageId,
                    $request->all(),
                ),
            );
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPackageShippingDocuments(string $packageId)
    {
        if (trim($packageId) === '') {
            return response()->json([
                'error' => 'Missing required packageId in URL',
            ], 400);
        }

        try {
            $response = $this->tiktokShopService->getPackageShippingDocument($packageId);

            // TikTok returns JSON for both errors and some document types (e.g. download_url).
            // PDF bytes are returned when document_format=PDF and the order is shipped.
            $contentType = $response->header('content-type') ?? '';
            $isJson = str_contains($contentType, 'application/json') || str_contains($contentType, 'text/json');

            // If TikTok returned a non-2xx status, always parse as JSON error
            if (!$response->successful()) {
                $json = $response->json() ?? [];
                $code = (int) ($json['code'] ?? $response->status());
                $rawMessage = $json['message'] ?? $response->reason() ?? 'TikTok API error';
                $friendlyMessage = $this->tiktokShopService->getErrorMessage($code, $rawMessage);
                return response()->json([
                    'error'      => $friendlyMessage,
                    'code'       => $code,
                    'request_id' => $json['request_id'] ?? null,
                ], 400);
            }

            if ($isJson) {
                $json = $response->json() ?? [];
                
                // Check for doc_url - it may be at top level or inside 'data'
                $docUrl = $json['doc_url'] ?? $json['download_url'] ?? $json['data']['doc_url'] ?? $json['data']['download_url'] ?? null;
                \Log::info('TikTok shipping doc response', ['json' => $json, 'doc_url' => $docUrl]);
                if ($docUrl) {
                    try {
                        \Log::info('Fetching PDF from TikTok URL', ['url' => $docUrl]);
                        $pdfResponse = Http::timeout(30)->withOptions(['verify' => false])->get($docUrl);
                        \Log::info('TikTok PDF fetch result', ['success' => $pdfResponse->successful(), 'size' => strlen($pdfResponse->body())]);
                        if ($pdfResponse->successful()) {
                            return response($pdfResponse->body(), 200, [
                                'Content-Type'        => 'application/pdf',
                                'Content-Disposition' => 'attachment; filename="shipping_label_' . $packageId . '.pdf"',
                            ]);
                        } else {
                            return response()->json([
                                'error' => 'Failed to fetch PDF from TikTok doc_url',
                                'status' => $pdfResponse->status(),
                            ], 502);
                        }
                    } catch (\Exception $e) {
                        \Log::error('TikTok PDF fetch exception', ['error' => $e->getMessage()]);
                        return response()->json([
                            'error' => 'Failed to fetch PDF from TikTok: ' . $e->getMessage(),
                        ], 502);
                    }
                }
                
                // Successful JSON response — check if it's an error
                $code = (int) ($json['code'] ?? 0);
                if ($code !== 0) {
                    $rawMessage = $json['message'] ?? 'TikTok API returned an error';
                    $friendlyMessage = $this->tiktokShopService->getErrorMessage($code, $rawMessage);
                    return response()->json([
                        'error'      => $friendlyMessage,
                        'code'       => $code,
                        'request_id' => $json['request_id'] ?? null,
                    ], 400);
                }
                
                if (isset($json['data'])) {
                    return response()->json($json['data']);
                }
                return response()->json($json);
            }

            // Binary PDF — stream it back with correct headers
            return response($response->body(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="shipping_label_' . $packageId . '.pdf"',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

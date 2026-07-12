<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\TiktokShopService;
use Illuminate\Http\Request;
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

            return response($response->body(), $response->status(), $response->headers());
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

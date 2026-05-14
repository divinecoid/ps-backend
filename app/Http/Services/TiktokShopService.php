<?php

namespace App\Http\Services;

use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use App\Enums\OrderStatus;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

class TiktokShopService
{
    private OnlineStore $store;

	public function __construct()
	{
		$this->store = OnlineStore::where('api_key', config('marketplace.tiktok_shop.app_key'))->firstOrFail();
	}

    public function getShops(): array
    {
        $path = '/authorization/202309/shops';
        $params = [
            'app_key'   => $this->getAppKey(),
            'timestamp' => time(),
        ];

        return $this->request('GET', $path, $params);
    }

    public function getPrimaryShop(): array
    {
        $shopsResponse = $this->getShops();
        $shop = $shopsResponse['data']['shops'][0] ?? null;

        if (!is_array($shop) || empty($shop['cipher'])) {
            throw new RuntimeException('No shop cipher found in TikTok shops response.');
        }

        return $shop;
    }

    public function getProduct(string $productId): array
    {
        $shop = $this->getPrimaryShop();
        $path = '/product/202309/products/' . $productId;
        $params = [
            'access_token' => $this->getAccessToken(),
            'app_key'      => $this->getAppKey(),
            'shop_cipher'  => $shop['cipher'],
            'shop_id'      => $shop['id'] ?? '',
            'timestamp'    => time(),
            'version'      => '202309',
        ];

        return $this->request('GET', $path, $params);
    }

    public function getOrderList(?int $pageSize = null): array
    {
        $shop = $this->getPrimaryShop();
        $path = '/order/202309/orders/search';
        $params = [
            'access_token' => $this->getAccessToken(),
            'app_key'      => $this->getAppKey(),
            'page_size'    => $pageSize ?? $this->getDefaultOrderPageSize(),
            'shop_cipher'  => $shop['cipher'],
            'timestamp'    => time(),
            'version'      => '202309',
        ];

        $response = $this->request('POST', $path, $params, new stdClass());
        $this->syncOrdersToDatabaseFromApiResponse($response);

        return $response;
    }

    public function getOrder(string $orderId): array
    {
        $shop = $this->getPrimaryShop();
        $path = '/order/202507/orders';
        $params = [
            'access_token' => $this->getAccessToken(),
            'app_key'      => $this->getAppKey(),
            'ids'          => $orderId,
            'shop_cipher'  => $shop['cipher'],
            'shop_id'      => $shop['id'] ?? '',
            'timestamp'    => time(),
            'version'      => '202507',
        ];

        $response = $this->request('GET', $path, $params);
        $this->syncOrdersToDatabaseFromApiResponse($response);

        return $response;
    }

    /**
     * Upserts TikTok orders into trx_orders.
     *
     * Mapping requested by user:
     * - order_sn = id
     * - awb_code = tracking number (first non-empty in line_items)
     * - item_count = total items (count(line_items))
     * - unique_item_count = how many types of items (distinct sku_id/product_id/seller_sku)
     * - total_shipping = original_shipping_fee - platform_discount
     * - total_price = sub_total
     * - total_amount = total_amount
     * - customer_name, customer_phone saved as-is
     * Other fields: left empty where possible; required DB fields get safe defaults.
     */
    public function syncOrdersToDatabaseFromApiResponse(array $apiResponse): void
    {
        $data = is_array($apiResponse['data'] ?? null) ? $apiResponse['data'] : [];
        $orders = $data['orders'] ?? $data['order_list'] ?? $data['orders_list'] ?? null;
        if (!is_array($orders) || $orders === []) {
            return;
        }

        DB::transaction(function () use ($orders) {
            foreach ($orders as $order) {
                if (!is_array($order)) {
                    continue;
                }

                $orderSn = (string) ($order['id'] ?? '');
                if (trim($orderSn) === '') {
                    continue;
                }

                $lineItems = is_array($order['line_items'] ?? null) ? $order['line_items'] : [];

                $awbCode = $this->extractTrackingNumber($lineItems);

                $itemCount = count($lineItems);
                $uniqueItemCount = $this->countUniqueItemTypes($lineItems);

                $payment = is_array($order['payment'] ?? null) ? $order['payment'] : [];
                $originalShippingFee = $this->toInt($payment['original_shipping_fee'] ?? 0);
                $platformDiscount = $this->toInt($payment['platform_discount'] ?? 0);
                $totalShipping = max(0, $originalShippingFee - $platformDiscount);

                $totalPrice = $this->toInt($payment['sub_total'] ?? ($payment['original_total_product_price'] ?? 0));
                $totalAmount = $this->toInt($payment['total_amount'] ?? 0);

                $recipient = is_array($order['recipient_address'] ?? null) ? $order['recipient_address'] : [];
                $customerName = (string) ($recipient['name'] ?? '');
                $customerPhone = (string) ($recipient['phone_number'] ?? '');

                // trx_orders requires non-null customer_name; keep "as-is" when present, fallback only when empty.
                if (trim($customerName) === '') {
                    $customerName = (string) ($order['buyer_email'] ?? 'Unknown');
                }

                // trx_orders requires non-null status; map TikTok status to internal enum.
                $marketplaceStatus = (string) ($order['status'] ?? '');
                $internalStatus = $this->mapTiktokStatusToOrderStatus($marketplaceStatus);

                $orderModel = Order::firstOrNew(['order_sn' => $orderSn]);

                $orderModel->online_store_id = $this->store->id;
                $orderModel->marketplace_id = $this->store->marketplace_id;
                $orderModel->item_count = $itemCount;
                $orderModel->unique_item_count = $uniqueItemCount;
                $orderModel->status = $internalStatus;
                $orderModel->total_price = (string) $totalPrice;
                $orderModel->total_shipping = (string) $totalShipping;
                $orderModel->total_amount = (string) $totalAmount;

                // Only set these if we actually received a non-empty value.
                if (trim($customerName) !== '') {
                    $orderModel->customer_name = $customerName;
                }
                if (trim($customerPhone) !== '') {
                    $orderModel->customer_phone = $customerPhone;
                }
                if (trim($awbCode) !== '') {
                    $orderModel->awb_code = $awbCode;
                }

                // Per request, leave address empty for newly created TikTok orders.
                if (!$orderModel->exists) {
                    $orderModel->customer_address = null;
                }

                // Keep TikTok raw status for traceability.
                if (trim($marketplaceStatus) !== '') {
                    $orderModel->readytoship_marketplace = $marketplaceStatus;
                }

                $orderModel->save();

                $this->syncOrderItemsToDatabase($orderModel, $lineItems);
            }
        });
    }

    /**
     * Upserts TikTok order line items into trx_order_items.
     *
     * Mapping (TikTok -> DB):
     * - trx_order_items.order_id            = trx_orders.id (internal UUID)
     * - trx_order_items.order_item_id       = line_items[].id (TikTok line item ID)
     * - trx_order_items.sku                 = line_items[].seller_sku
     * - trx_order_items.item_name           = line_items[].product_name (+ sku_name when useful)
     * - trx_order_items.price               = line_items[].original_price
     * - trx_order_items.discounted_price    = line_items[].sale_price
     * - trx_order_items.quantity_purchased  = 1 (per request; each line item is treated as unique)
     *
     * Notes:
     * - product_id is left untouched (often requires internal mapping to mdx_products).
     * - is_checked is set to false on create, never overwritten.
     */
    private function syncOrderItemsToDatabase(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }

            $externalLineItemId = (string) ($lineItem['id'] ?? '');
            if (trim($externalLineItemId) === '') {
                continue;
            }

            $sku = (string) ($lineItem['seller_sku'] ?? '');
            $productName = (string) ($lineItem['product_name'] ?? '');
            $skuName = (string) ($lineItem['sku_name'] ?? '');

            $itemName = trim($productName);
            $skuNameTrimmed = trim($skuName);
            if ($skuNameTrimmed !== '' && strcasecmp($skuNameTrimmed, 'default') !== 0) {
                $itemName = trim($itemName . ' - ' . $skuNameTrimmed);
            }

            // Optional: try to infer color/size from sku_name when it uses Shopee-like "Color,Size".
            [$color, $size] = $this->extractColorAndSizeFromSkuName($skuNameTrimmed);

            $originalPrice = $this->toInt($lineItem['original_price'] ?? 0);
            $salePrice = $this->toInt($lineItem['sale_price'] ?? ($lineItem['original_price'] ?? 0));

            $orderItem = OrderItem::firstOrNew([
                'order_id' => $order->id,
                'order_item_id' => $externalLineItemId,
            ]);

            // Only set is_checked on create; don't overwrite if already scanned/checked.
            if (!$orderItem->exists) {
                $orderItem->is_checked = false;
            }

            // Never overwrite product_id here (requires internal catalog mapping).

            $orderItem->quantity_purchased = 1;

            if (trim($sku) !== '') {
                $orderItem->sku = $sku;
            }
            if (trim($itemName) !== '') {
                $orderItem->item_name = $itemName;
            }

            // Only fill color/size when we actually inferred something and the DB is still empty.
            if ($color !== null && trim((string) $orderItem->color) === '') {
                $orderItem->color = $color;
            }
            if ($size !== null && trim((string) $orderItem->size) === '') {
                $orderItem->size = $size;
            }

            $orderItem->price = (string) $originalPrice;
            $orderItem->discounted_price = (string) $salePrice;

            $orderItem->save();
        }
    }

    private function extractColorAndSizeFromSkuName(string $skuName): array
    {
        $skuName = trim($skuName);
        if ($skuName === '') {
            return [null, null];
        }

        $parts = array_map('trim', explode(',', $skuName));
        if (count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '') {
            return [$parts[0], $parts[1]];
        }

        return [null, null];
    }

    private function extractTrackingNumber(array $lineItems): string
    {
        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }
            $trackingNumber = (string) ($lineItem['tracking_number'] ?? '');
            if (trim($trackingNumber) !== '') {
                return $trackingNumber;
            }
        }

        return '';
    }

    private function countUniqueItemTypes(array $lineItems): int
    {
        $keys = [];
        foreach ($lineItems as $lineItem) {
            if (!is_array($lineItem)) {
                continue;
            }

            $key = (string) (
                $lineItem['sku_id']
                    ?? $lineItem['product_id']
                    ?? $lineItem['seller_sku']
                    ?? $lineItem['id']
                    ?? ''
            );

            if (trim($key) !== '') {
                $keys[] = $key;
            }
        }

        return count(array_values(array_unique($keys)));
    }

    private function mapTiktokStatusToOrderStatus(string $tiktokStatus): OrderStatus
    {
        $normalized = strtoupper(trim($tiktokStatus));

        return match ($normalized) {
            'AWAITING_SHIPMENT' => OrderStatus::READY_TO_SHIP,
            'AWAITING_COLLECTION', 'READY_TO_SHIP' => OrderStatus::READY_TO_PICKUP,
            'IN_TRANSIT', 'SHIPPED' => OrderStatus::SHIPPED,
            'DELIVERED' => OrderStatus::DELIVERED,
            'CANCELLED', 'CANCELED' => OrderStatus::CANCELLED,
            'RETURNED' => OrderStatus::RETURNED,
            default => OrderStatus::PENDING,
        };
    }

    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 0;
            }
            if (is_numeric($trimmed)) {
                return (int) round((float) $trimmed);
            }
        }

        return 0;
    }

    /**
     * Returns a valid access token, refreshing or re-authorizing as needed.
     * Token precedence:
     *   1. Valid non-expired token already in DB → return it
     *   2. Expired token but refresh token still valid → refresh it
     *   3. No tokens at all → exchange auth code (one-time bootstrap)
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        // 1. Stored token is still valid
        if (
            !$forceRefresh
            && $this->store->access_token
            && $this->store->access_token_expires_at
            && Carbon::now()->lt(Carbon::parse($this->store->access_token_expires_at))
        ) {
            return $this->store->access_token;
        }

        // 2. Refresh token exists — try to use it
        if ($this->store->refresh_token) {
            return $this->exchangeRefreshToken();
        }

        // 3. Last resort: exchange the auth code (single-use — only works once)
        return $this->exchangeAuthCode();
    }

    // -------------------------------------------------------------------------
    // Token exchange helpers
    // -------------------------------------------------------------------------

	private function exchangeAuthCode(): string
	{
		$params = [
			'app_key'    => $this->getAppKey(),
			'app_secret' => $this->getAppSecret(),
			'auth_code'  => $this->getAuthCode(),
			'grant_type' => 'authorized_code',
		];

		// GET, not POST — TikTok's token/get endpoint doesn't accept POST
		$response = $this->http()->get($this->getAuthUrl(), $params);

		if (!$response->successful()) {
			throw new RuntimeException('Failed to exchange TikTok auth code: ' . $this->extractError($response));
		}

		$json = $response->json();

		if (($json['code'] ?? -1) !== 0 || empty($json['data']['access_token'])) {
			throw new RuntimeException('Failed to exchange TikTok auth code: ' . $this->extractError($response));
		}

		$this->persistTokens($json['data']);

		return $json['data']['access_token'];
	}
    private function exchangeRefreshToken(): string
    {
        $params = [
            'app_key'       => $this->getAppKey(),
            'app_secret'    => $this->getAppSecret(),
            'refresh_token' => $this->store->refresh_token,
            'grant_type'    => 'refresh_token',
        ];

        $response = $this->http()->get('https://auth.tiktok-shops.com/api/v2/token/refresh', $params);

        $json = $response->json();

        if (!$response->successful() || ($json['code'] ?? -1) !== 0) {
            throw new RuntimeException('Failed to refresh TikTok access token: ' . $this->extractError($response));
        }

        $this->persistTokens($json['data']);

        return $json['data']['access_token'];
    }

    /**
     * Saves access + refresh tokens and their expiry timestamps to the DB.
     */
    private function persistTokens(array $data): void
    {
        $updates = [
            'access_token'            => $data['access_token'],
            'access_token_expires_at' => Carbon::createFromTimestamp($data['access_token_expire_in']),
        ];

        // Only overwrite refresh token if a new one was returned
        if (!empty($data['refresh_token'])) {
            $updates['refresh_token'] = $data['refresh_token'];
        }

        if (!empty($data['refresh_token_expire_in'])) {
            $updates['refresh_token_expires_at'] = Carbon::createFromTimestamp($data['refresh_token_expire_in']);
        }

        $this->store->update($updates);

        // Keep the in-memory store in sync so the current request doesn't re-fetch
        $this->store->refresh();
    }

    // -------------------------------------------------------------------------
    // Core HTTP request + signing
    // -------------------------------------------------------------------------

    private function request(string $method, string $path, array $params, mixed $payload = null): array
    {
        $sign = $this->generateSign($path, $params, $payload);
        $url  = rtrim((string) config('marketplace.tiktok_shop.base_url'), '/') . $path;

        $finalParams = [
            ...$params,
            'sign' => $sign,
        ];

        $headers = [
            'x-tts-access-token' => $this->getAccessToken(),
            'Content-Type'       => 'application/json',
        ];

        $client = $this->http()->withHeaders($headers);

        $response = strtoupper($method) === 'POST'
            ? $client->post($url . '?' . http_build_query($finalParams), $payload ?? new stdClass())
            : $client->get($url, $finalParams);

        if (!$response->successful()) {
            throw new RuntimeException('TikTok API request failed: ' . $this->extractError($response));
        }

        return $response->json() ?? [];
    }

    private function generateSign(string $path, array $params, mixed $body = null): string
    {
        $paramsForSign = $params;
        unset($paramsForSign['sign'], $paramsForSign['access_token']);

        ksort($paramsForSign);

        $paramString = '';
        foreach ($paramsForSign as $key => $value) {
            $paramString .= $key . $value;
        }

        if ($body === null || $body === '') {
            $payload = '';
        } elseif (is_string($body)) {
            $payload = $body;
        } else {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new RuntimeException('Failed to JSON-encode TikTok request body for signing.');
            }
        }

        $secret       = $this->getAppSecret();
        $stringToSign = $secret . $path . $paramString . $payload . $secret;

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function extractError(Response $response): string
    {
        $json = $response->json();
        if (is_array($json)) {
            return json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $response->body();
        }

        return $response->body();
    }

    private function getAppKey(): string
    {
        return (string) config('marketplace.tiktok_shop.app_key');
    }

    private function getAppSecret(): string
    {
        return (string) config('marketplace.tiktok_shop.app_secret');
    }

	private function getAuthCode(): string
	{
		// Prefer the DB value, fall back to env
		return $this->store->auth_code 
			?: (string) config('marketplace.tiktok_shop.auth_code');
	}

    private function getAuthUrl(): string
    {
        return (string) config('marketplace.tiktok_shop.auth_url');
    }

    private function getDefaultOrderPageSize(): int
    {
        return (int) config('marketplace.tiktok_shop.order_page_size', 20);
    }

    private function http(): PendingRequest
    {
        $caBundle = config('marketplace.tiktok_shop.ca_bundle');
        if (is_string($caBundle) && trim($caBundle) !== '') {
            return Http::withOptions(['verify' => $caBundle]);
        }

        return Http::withOptions(['verify' => $this->getTlsVerifyValue()]);
    }

    private function getTlsVerifyValue(): bool
    {
        $value = config('marketplace.tiktok_shop.tls_verify', true);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return !in_array($normalized, ['0', 'false', 'off', 'no'], true);
        }

        return true;
    }
}
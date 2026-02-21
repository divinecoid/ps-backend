Shopee API Requests (JSON Copy)
================================

Base URL
- <BASE_URL> contoh: http://localhost:8000

Headers
```json
{
  "Content-Type": "application/json"
}
```

1) GET /api/shopee/shipping-parameter
- Path: /api/shopee/shipping-parameter
- Header: (tidak perlu Authorization)
- Query Params:
```json
{
  "order_sn": "251227DGWNKEAV"
}
```
- Contoh URL:
<BASE_URL>/api/shopee/shipping-parameter?order_sn=251227DGWNKEAV

2) POST /api/shopee/ship-order
- Path: /api/shopee/ship-order
- Header:
```json
{
  "Authorization": "Bearer <TOKEN>",
  "Content-Type": "application/json"
}
```
- JSON Body:
```json
{
  "order_sn": "251227DGWNKEAV",
  "address_id": "123456",
  "pickup_time_id": "PICKUP_TIME_ID"
}
```

3) POST /api/shopee/download-shipping-document
- Path: /api/shopee/download-shipping-document
- Header:
```json
{
  "Authorization": "Bearer <TOKEN>",
  "Content-Type": "application/json"
}
```
- JSON Body:
```json
{
  "order_sn": "251227DGWNKEAV",
  "shipping_document_type": "NORMAL_AIR_WAYBILL"
}
```

Referensi Kode
- Routes: routes/api.php [L188-194](file:///c:\Kerja\Code\PS-BE\routes\api.php#L188-L194)
- Controller: app/Http/Controllers/Api/ShopeeController.php
  - getShippingParameter [L135-173](file:///c:\Kerja\Code\PS-BE\app\Http\Controllers\Api\ShopeeController.php#L135-L173)
  - shipOrder [L181-297](file:///c:\Kerja\Code\PS-BE\app\Http\Controllers\Api\ShopeeController.php#L181-L297)
  - downloadShippingDocument [L305-353](file:///c:\Kerja\Code\PS-BE\app\Http\Controllers\Api\ShopeeController.php#L305-L353)

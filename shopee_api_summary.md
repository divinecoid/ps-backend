# Shopee API Summary

Berikut adalah daftar API Shopee yang sudah tersedia di backend, lengkap dengan URL, method, dan contoh request body-nya.

---

## 1. Auth API (Dapatkan Token)

Digunakan untuk mendapatkan Bearer Token sebelum mengakses API Shopee lainnya.

- **URL**: `http://localhost:8000/api/auth/login`
- **Method**: `POST`
- **Body (JSON)**:
  ```json
  {
    "username": "superadmin",
    "password": "password"
  }
  ```
- **Response**: Berisi `token` yang harus ditaruh di Header `Authorization: Bearer <TOKEN>`.

---

## 2. Shopee Logistics API

*Note: Semua API di bawah ini wajib menggunakan Header `Authorization: Bearer <TOKEN>`.*

### A. Get Shipping Parameter
Mendapatkan info pickup/dropoff dari Shopee.

- **URL**: `http://localhost:8000/api/shopee/shipping-parameter`
- **Method**: `GET`
- **Query Params**:
  - `order_sn`: (Required) Contoh: `260214PT2MJ3S3`

---

### B. Ship Order (Arrange Shipment)
Mengatur pengiriman (Request Pickup).

- **URL**: `http://localhost:8000/api/shopee/ship-order`
- **Method**: `POST`
- **Body (JSON)**:
  ```json
  {
    "order_sn": "260214PT2MJ3S3",
    "address_id": 123456,
    "pickup_time_id": "1774105000"
  }
  ```

---

### C. Create Shipping Document (Trigger Print)
Memicu Shopee untuk men-generate dokumen pengiriman (Wajib sebelum download).

- **URL**: `http://localhost:8000/api/shopee/create-shipping-document`
- **Method**: `POST`
- **Body (JSON)**:
  ```json
  {
    "order_sn": "260214PT2MJ3S3"
  }
  ```

---

### D. Download Shipping Document (Get Resi)
Mendownload label pengiriman dalam format PDF.

- **URL**: `http://localhost:8000/api/shopee/download-shipping-document`
- **Method**: `POST`
- **Body (JSON)**:
  ```json
  {
    "order_sn": "260214PT2MJ3S3",
    "shipping_document_type": "NORMAL_AIR_WAYBILL"
  }
  ```

---

## 3. Shopee Authentication Flow

### A. Redirect to Shopee (Login Store)
Redirect user ke halaman login Shopee untuk otorisasi toko.

- **URL**: `http://localhost:8000/api/shopee/redirect/{id}`
- **Method**: `GET`
- **Params**:
  - `id`: UUID dari Online Store.

---

### B. Handle Callback (Otomatis)
Endpoint yang dipanggil Shopee setelah user login (Callback URL).

- **URL**: `http://localhost:8000/api/shopee/callback`
- **Method**: `GET`
- **Note**: Digunakan oleh Shopee untuk mengirim `code` dan `shop_id`.

---

## 4. Shopee Synchronization

### Fetch Orders
Menarik data pesanan terbaru dari Shopee ke database lokal.

- **URL**: `http://localhost:8000/api/shopee/fetch-orders`
- **Method**: `GET`
- **Query Params**:
  - `days`: (Optional) Jumlah hari ke belakang (default 1).

---

## Kenapa "Not Found"?
Jika kamu mendapatkan error 404 (Not Found), pastikan:
1.  **URL Lengkap**: Harus ada `/api/` di depan rute (contoh: `/api/shopee/ship-order`).
2.  **Port Server**: Pastikan server Laravel jalan di port yang sama (default 8000).
3.  **Method HTTP**: Perhatikan mana yang `GET` dan mana yang `POST`.

**Referensi Kode:**
- [routes/api.php](file:///c:\Kerja\Code\PS-BE\routes\api.php)
- [ShopeeController.php](file:///c:\Kerja\Code\PS-BE\app\Http\Controllers\Api\ShopeeController.php)

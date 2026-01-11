# Shopee Integration Setup & Testing Guide

Panduan ini menjelaskan langkah-langkah untuk menjalankan aplikasi dari awal, melakukan setup database, dan menjalankan test flow Shopee.

## 1. Persiapan Environment

Pastikan file `.env` sudah dikonfigurasi dengan benar, terutama untuk koneksi database.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_anda
DB_USERNAME=root
DB_PASSWORD=
```

## 2. Setup Database (Migrate & Seed)

Jalankan perintah berikut untuk membuat tabel dan mengisi data awal (termasuk data dummy Shopee).

**Menggunakan Migrate Fresh (Reset Database):**
Perintah ini akan menghapus semua tabel dan membuatnya ulang dari awal.

```bash
php artisan migrate:fresh --seed
```

**Atau secara terpisah:**

1.  Jalankan migrasi:
    ```bash
    php artisan migrate
    ```

2.  Jalankan seeder:
    ```bash
    php artisan db:seed
    ```
    *Note: `DatabaseSeeder` sudah mencakup `ShopeeSeeder` yang mengisi data toko dan order dummy.*

## 3. Menjalankan Shopee Real Flow Test

Test ini mensimulasikan flow order Shopee dari masuk database, status `ready_to_ship`, `retry_ship`, hingga `ready_to_pickup` dan download dokumen pengiriman.

Jalankan perintah berikut:

```bash
php artisan test --filter=ShopeeRealFlowTest
```

### Penjelasan Flow Test:
1.  **Seed Data**: Test akan otomatis membuat user dan toko dummy jika belum ada.
2.  **Create Orders**: Membuat order dummy dengan berbagai status (`ready_to_ship`, `retry_ship`, dll).
3.  **Process Orders**:
    *   Untuk status `ready_to_ship` dan `retry_ship`: Mencoba mengambil parameter pengiriman dan melakukan "ship order" (simulasi).
    *   Untuk status `ready_to_pickup` dan `shipped`: Mencoba mendownload dokumen pengiriman (AWB).
4.  **Validasi**: Memastikan API merespons dengan kode 200 OK dan content-type yang sesuai (misal PDF untuk dokumen).

## 4. Menjalankan Fetch Orders Command

Untuk menarik data order real dari Shopee (atau Sandbox), gunakan perintah berikut:

```bash
php artisan shopee:fetch-orders --days=1
```

*   **--days=1**: Menarik order dari 1 hari terakhir (default). Anda bisa mengubah angkanya sesuai kebutuhan.
    *   Contoh 2 hari terakhir: `php artisan shopee:fetch-orders --days=2`
*   Command ini akan mencari semua toko Shopee yang aktif, mengambil daftar order, dan detail ordernya, lalu menyimpannya ke database.

## 5. Troubleshooting Umum

*   **Error "Data truncated for column 'status'":**
    Pastikan migrasi terbaru (`2026_01_08_000001_add_retry_ship_to_trx_orders_status.php`) sudah berjalan. Enum `retry_ship` baru saja ditambahkan.

*   **Error "Order status must be ready_to_ship":**
    Pastikan kode di `ShopeeController.php` sudah diupdate untuk mengizinkan `OrderStatus::RETRY_SHIP`.

*   **Sandbox Limitation:**
    Jika mendapat respons "No pickup/dropoff options found" saat test, itu wajar di environment Sandbox jika konfigurasi logistik dummy belum lengkap, namun flow aplikasi sudah dianggap valid jika tidak error 500.

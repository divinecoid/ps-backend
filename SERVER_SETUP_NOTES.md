# Panduan Setup Cron Job & Queue Worker di Hosting

Dokumen ini berisi langkah-langkah untuk menjalankan semua schedule (jadwal otomatis) di project Laravel ini pada environment hosting (seperti cPanel).

Project ini menggunakan fitur **Laravel Scheduler** dan **Queue Jobs**. Scheduler bertugas mengatur *kapan* sebuah tugas dijalankan, sedangkan Queue Jobs bertugas mengeksekusi tugas tersebut di background agar tidak mengganggu proses lain.

## 1. Setup Cron Job (Wajib)

Laravel Scheduler membutuhkan satu Cron Job tunggal yang berjalan **setiap menit**. Cron Job inilah yang akan memicu Laravel untuk memeriksa apakah ada jadwal (`routes/console.php`) yang harus dijalankan pada menit tersebut.

**Tambahkan di menu Cron Jobs (cPanel/Hosting):**

- **Menit (Minute):** `*` (Setiap Menit / Every minute)
- **Jam (Hour):** `*` (Setiap Jam / Every hour)
- **Hari (Day):** `*` (Setiap Hari / Every day)
- **Bulan (Month):** `*` (Setiap Bulan / Every month)
- **Hari dalam Seminggu (Weekday):** `*` (Setiap Hari / Every weekday)

**Command yang harus dijalankan (Pilih salah satu):**

*Opsi 1 (Umum di cPanel):*
```bash
cd /path/ke/folder/project/ps-backend && php artisan schedule:run >> /dev/null 2>&1
```

*Opsi 2 (Jika Opsi 1 gagal karena path PHP):*
```bash
/usr/local/bin/php /path/ke/folder/project/ps-backend/artisan schedule:run >> /dev/null 2>&1
```
*(Catatan: Sesuaikan `/path/ke/folder/project/ps-backend` dengan path absolut project Anda di server, dan `/usr/local/bin/php` dengan path eksekusi PHP di server Anda. Anda bisa mengecek path PHP dengan perintah `which php` di terminal server)*.

---

## 2. Setup Queue Worker (Wajib)

Karena kita menggunakan Queue (`$schedule->job(...)` di `routes/console.php`), tugas-tugas tidak dijalankan secara sinkron oleh Scheduler, melainkan dilempar ke antrean (Queue). Agar antrean ini diproses, **Queue Worker harus selalu berjalan di server.**

**Jika menggunakan cPanel / Hosting tanpa akses Supervisor:**

Karena di cPanel standard biasanya tidak bisa menginstall Supervisor (pengelola proses background), cara paling umum adalah menjalankan worker melalui Cron Job yang memastikan worker tersebut terus berjalan (atau direstart jika mati).

**Tambahkan di menu Cron Jobs (cPanel/Hosting):**

- **Frekuensi:** Setiap Menit (`* * * * *`)
- **Command:**
```bash
cd /path/ke/folder/project/ps-backend && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```
*Catatan: `--stop-when-empty` penting di cPanel agar proses worker tidak menumpuk dan menghabiskan memori. Cron akan menjalankannya lagi di menit berikutnya jika mati.*

**Jika server Anda adalah VPS / Dedicated Server (Punya akses root):**

Sangat disarankan menggunakan **Supervisor**.
1.  Install Supervisor (`sudo apt install supervisor` di Ubuntu).
2.  Buat file config (misal `/etc/supervisor/conf.d/ps-backend-worker.conf`):
    ```ini
    [program:ps-backend-worker]
    process_name=%(program_name)s_%(process_num)02d
    command=php /path/ke/folder/project/ps-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
    autostart=true
    autorestart=true
    user=www-data
    numprocs=1
    redirect_stderr=true
    stdout_logfile=/path/ke/folder/project/ps-backend/storage/logs/worker.log
    ```
3.  Jalankan: `sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start ps-backend-worker:*`

---

## 3. Daftar Job yang Sudah Berjalan Saat Ini

Jika langkah 1 dan 2 sudah dilakukan, tugas-tugas berikut akan berjalan otomatis:

1.  **Fetch Shopee Orders**
    - **Jadwal:** Setiap jam (`0 * * * *`)
    - **Fungsi:** Mengambil order Shopee 1 hari ke belakang.
    - **Job:** `App\Jobs\FetchShopeeOrdersJob`

2.  **Refresh Shopee Token**
    - **Jadwal:** Setiap hari jam 00:30 (`30 0 * * *`)
    - **Fungsi:** Memperbarui (refresh) token integrasi Shopee untuk semua toko yang aktif.
    - **Job:** `App\Jobs\RefreshShopeeTokenJob`

---

## 4. Cara Menambahkan Jadwal (Job) Baru

Jika di masa depan Anda perlu menambahkan jadwal otomatis baru, ikuti 2 langkah sederhana ini:

**Langkah 1: Buat File Job Baru**
Jalankan perintah ini di terminal lokal Anda (bukan di server):
```bash
php artisan make:job NamaJobBaru
```
File baru akan muncul di folder `app/Jobs/NamaJobBaru.php`. Tulis kode/logika yang ingin Anda jalankan di dalam function `handle()` di file tersebut.

**Langkah 2: Daftarkan Jadwalnya**
Buka file `routes/console.php`, lalu tambahkan jadwalnya di bagian paling bawah. Contoh:
```php
use App\Jobs\NamaJobBaru;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new NamaJobBaru())
    ->daily() // atau ->hourly(), ->everyMinute(), dll
    ->withoutOverlapping()
    ->onOneServer();
```
*(Tidak perlu mengubah cron di cPanel/Server. Selama Cron utama dari Langkah 1 sudah terpasang, jadwal baru ini akan otomatis berjalan).*

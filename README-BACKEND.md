# Backend Architecture & Technical Guide - SCADA Monitoring Dashboard

**Project:** Web Dashboard Monitoring Suhu & Kelembapan HMI Haiwell  
**Client:** Bpk. Ir. Syaefudhin, S.T.  
**Tech Stack:** Laravel (Backend/API), MySQL (Database), Python `pymodbus` (Hardware Poller), PM2 (Process Manager)  
**Deployment Environment:** Local Area Network (LAN) on Mini PC Intel J1900

---

## 1. System Architecture Flow

Arsitektur backend dipisah menjadi dua *service* utama untuk mencegah beban sinkron pada Laravel:
1. **Hardware Communicator (Python):** Bertugas melakukan *polling* ke IP Address HMI via Modbus TCP (Port 502) setiap 5 detik. Data mentah dikonversi (dibagi 10) lalu di-UPSERT langsung ke MySQL.
2. **Web & API Server (Laravel):** Bertugas mengelola *Business Logic* (CRUD Ruangan/Alat), menyajikan UI via Inertia.js, dan mengeksekusi *Cron Job* untuk riwayat grafik.

## 2. Server & Environment Setup (Mini PC J1900)

Mengingat spesifikasi Mini PC (Intel Celeron J1900, *low-power*), optimasi *resource* adalah prioritas utama.

### MySQL Optimization (`my.cnf` / `my.ini`)
Wajib tambahkan konfigurasi ini untuk menghemat RAM Mini PC:
- `performance_schema = OFF`
- `innodb_buffer_pool_size = 256M` (Atau sesuaikan dengan sisa RAM)

### Process Management (PM2)
Seluruh *service* wajib berjalan otomatis jika Mini PC mati listrik.
- **Start Laravel:** `pm2 start "php artisan serve --host=0.0.0.0 --port=80" --name "laravel-scada"`
- **Start Python Poller:** `pm2 start poller.py --name "python-modbus-worker" --interpreter python3`
- **Start Queue/Scheduler (Jika ada):** `pm2 start "php artisan schedule:work" --name "laravel-scheduler"`
- Simpan konfigurasi: `pm2 save` & `pm2 startup`

---

## 3. Python Modbus Worker (`poller.py`) Spec

*Script* berjalan tanpa henti (`while True`) dengan jeda `time.sleep(5)`.

**Aturan Eksekusi:**
- **Single Connection DB:** Buka koneksi MySQL di luar *looping* untuk mencegah CPU J1900 *overload*.
- **Timeout Modbus:** Atur `timeout=2` pada `ModbusTcpClient` agar *script* tidak *hang* jika kabel LAN HMI terputus.
- **Round-Robin:** *Looping* pembacaan IP berdasarkan data dari tabel `hmis` di MySQL.
- **Bulk Upsert:** Gunakan `INSERT ... ON DUPLICATE KEY UPDATE` di eksekusi SQL Python agar operasi penulisan ke tabel `sensor_latest_data` sangat ringan.

---

## 4. Laravel Backend Spec

### Laravel Task Scheduling (`app/Console/Kernel.php`)
Sistem harus memiliki mekanisme "Housekeeping" agar *database* tidak penuh dan membuat Mini PC lambat.
- **Tugas:** Menghapus data riwayat dari tabel `sensor_logs` yang usianya lebih dari 90 hari.
- **Frekuensi:** Dijalankan setiap hari (`->daily()`).

### Controller to Inertia (Data Preparation)
Untuk mendukung *Frontend* secara efisien, *Controller* yang merender halaman *Dashboard* wajib memformat data yang sudah matang.
- Gunakan *Eager Loading* untuk membasmi N+1 Query: `Room::with(['hmis.sensors.latestData'])->get()`.
- Kirim JSON ke Inertia yang sudah terstruktur (mengandung nama ruangan, *threshold* alarm, status *online/offline*, dan rata-rata global).

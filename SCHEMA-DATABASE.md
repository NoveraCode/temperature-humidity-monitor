# Database Schema & Migrations Guide
**Project:** SCADA Monitoring Dashboard
**Framework:** Laravel (MySQL/InnoDB)

Dokumen ini berisi rancangan arsitektur *database* relasional untuk sistem monitoring. Skema ini dirancang khusus untuk meminimalkan beban CPU pada Mini PC J1900 dan menghindari *N+1 Query Problem* saat melakukan *fetching* data ke Inertia.js.

---

## 1. Table: `users`
Tabel autentikasi bawaan Laravel untuk akses Admin Panel (Device & Room Settings).

- `id` (Primary Key, BigInt)
- `name` (String)
- `email` (String, Unique)
- `password` (String)
- `remember_token` (String)
- `timestamps`

---

## 2. Table: `rooms`
Menyimpan data master ruangan dan batas toleransi (threshold) alarm. 
*Catatan UI:* Data ini digunakan untuk merender kartu ruangan dan menentukan kapan indikator warna merah/hijau menyala.

- `id` (Primary Key, BigInt)
- `name` (String) — *Contoh: "RUANG CCTV"*
- `location` (String, Nullable) — *Contoh: "LT. 2"*
- `temp_max_limit` (Float) — *Batas atas suhu normal (default: 25.0)*
- `hum_max_limit` (Float) — *Batas atas kelembapan normal (default: 60.0)*
- `timestamps`

---

## 3. Table: `hmis`
Menyimpan data perangkat HMI/Konverter yang menjadi target *polling* Modbus TCP oleh *script* Python.

- `id` (Primary Key, BigInt)
- `room_id` (Foreign Key -> `rooms.id`, Cascade Delete)
- `name` (String) — *Contoh: "HMI 1"*
- `ip_address` (String) — *Contoh: "192.168.1.10"*
- `port` (Integer) — *Default: 502*
- `is_active` (Boolean) — *Default: true (Toggle untuk menghentikan polling jika alat sedang diservis)*
- `timestamps`

---

## 4. Table: `sensors`
Menyimpan data titik sensor fisik dan alamat register Modbus-nya. Python akan menggunakan alamat ini untuk menarik data spesifik.

- `id` (Primary Key, BigInt)
- `hmi_id` (Foreign Key -> `hmis.id`, Cascade Delete)
- `name` (String) — *Contoh: "R.CCTV T/H 1"*
- `modbus_address_temp` (Integer) — *Alamat register suhu (misal: 40001)*
- `modbus_address_hum` (Integer) — *Alamat register kelembapan (misal: 40002)*
- `timestamps`

---

## 5. Table: `sensor_latest_data` 🔥 (High-Frequency Table)
Tabel ini HANYA berisi 1 baris per sensor. *Script* Python akan terus melakukan **UPSERT (Update or Insert)** ke tabel ini setiap 5 detik. Tidak ada riwayat log di sini agar *query dashboard* utama sangat ringan.

- `id` (Primary Key, BigInt)
- `sensor_id` (Foreign Key -> `sensors.id`, Unique, Cascade Delete)
- `temperature` (Float, Nullable) — *Data pembacaan terakhir*
- `humidity` (Float, Nullable) — *Data pembacaan terakhir*
- `status` (String) — *Enum: 'NORMAL', 'WARNING', 'CRITICAL', 'OFFLINE'*
- `last_read_at` (Timestamp, Nullable) — *Waktu terakhir Python berhasil menarik data*
- `timestamps`

---

## 6. Table: `sensor_logs` (History Table)
Tabel ini digunakan untuk menyajikan data grafik (Line Chart). Di-*insert* secara berkala melalui Laravel Scheduler (misal: setiap 15 menit) berupa nilai **rata-rata ruangan**, bukan per sensor, agar *database* tidak cepat penuh.

- `id` (Primary Key, BigInt)
- `room_id` (Foreign Key -> `rooms.id`, Cascade Delete)
- `avg_temperature` (Float)
- `avg_humidity` (Float)
- `created_at` (Timestamp, **INDEXED**) — *Wajib di-index untuk mempercepat query filter tanggal pada chart*
- `updated_at` (Timestamp, Nullable)

---

## 💡 Panduan Eloquent Relationships (Model)

Untuk mendukung struktur di atas, definisikan relasi di Model Laravel Anda sebagai berikut:

**Room.php**
```php
public function hmis() { return $this->hasMany(Hmi::class); }
public function logs() { return $this->hasMany(SensorLog::class); }

**Hmi.php**
public function room() { return $this->belongsTo(Room::class); }
public function sensors() { return $this->hasMany(Sensor::class); }

Sensor.php
public function hmi() { return $this->belongsTo(Hmi::class); }
public function latestData() { return $this->hasOne(SensorLatestData::class); }

Eager logging 
// Panggil ini di Controller untuk merender Inertia secara utuh tanpa N+1 Problem
$rooms = Room::with(['hmis.sensors.latestData'])->get();


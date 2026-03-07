# Panduan Test Hardware — SCADA Monitoring

**Tanggal:** 7 Maret 2026  
**Kondisi:** Laptop terhubung ke jaringan LAN yang sama dengan HMI (switch/hub yang sama)

---

## Prasyarat

### 1. Verifikasi koneksi jaringan
```bash
# Pastikan IP laptop berada di subnet 192.168.1.x
ipconfig

# Ping masing-masing HMI
ping 192.168.1.200
ping 192.168.1.201
ping 192.168.1.202
ping 192.168.1.203
ping 192.168.1.204
```

Semua ping harus berhasil sebelum melanjutkan.

---

## Langkah 1 — Siapkan Database

```bash
php artisan migrate:fresh --seed
```

Ini akan mengisi:
- 5 ruangan dengan IP 192.168.1.200–204
- 5 sensor per HMI, `unit_id` 1–5
- `sensor_latest_data` awal: semua sensor OFFLINE

---

## Langkah 2 — Buat `.env` Poller

Buat file `.env` di root project (sejajar `poller.py`):

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=scada_db
DB_USER=root
DB_PASS=
```

> Sesuaikan `DB_USER` dan `DB_PASS` jika berbeda dari default Laragon.

---

## Langkah 3 — Install Dependensi Python (sekali saja)

```bash
pip install -r requirements.txt
```

---

## Langkah 4 — Jalankan Laragon

Pastikan **Apache/Nginx** dan **MySQL** sudah **Start All** di Laragon.

---

## Langkah 5 — Jalankan Poller

Buka terminal baru, jalankan:

```bash
python poller.py
```

Output yang diharapkan saat koneksi berhasil:
```
[HMI-1 | 192.168.1.200] Sensor R1 T/H 1: temp=22.4°C hum=55.1% → NORMAL
[HMI-1 | 192.168.1.200] Sensor R1 T/H 2: temp=23.0°C hum=58.3% → NORMAL
...
```

Output saat HMI tidak terjangkau:
```
[HMI-3 | 192.168.1.202] OFFLINE — Connection error
```

---

## Langkah 6 — Verifikasi Data di Database

```bash
php artisan tinker
```

```php
// Lihat semua status terkini
DB::table('sensor_latest_data')
    ->join('sensors', 'sensors.id', '=', 'sensor_latest_data.sensor_id')
    ->join('hmis', 'hmis.id', '=', 'sensors.hmi_id')
    ->select(
        'hmis.ip_address',
        'sensors.name',
        'sensors.unit_id',
        'sensor_latest_data.temperature',
        'sensor_latest_data.humidity',
        'sensor_latest_data.status',
        'sensor_latest_data.last_read_at'
    )
    ->orderBy('hmis.ip_address')
    ->orderBy('sensors.unit_id')
    ->get();
```

---

## Langkah 7 — Verifikasi Payload Dashboard

```bash
# Ambil session cookie dari browser setelah login, atau gunakan tinker:
php artisan tinker

$ctrl = app(App\Http\Controllers\DashboardController::class);
$response = $ctrl->index();
$data = $response->getData();
dump($data['globalStats']);
dump(collect($data['rooms'])->map(fn($r) => ['name' => $r['name'], 'status' => $r['status']]));
```

---

## Skenario Uji OFFLINE

### Simulasi: Cabut kabel salah satu HMI
1. Cabut kabel ethernet HMI ke-3 (192.168.1.202)
2. Tunggu maksimal **8 detik** (3 detik timeout Modbus + 5 detik interval polling)
3. Verifikasi di DB:

```php
DB::table('sensor_latest_data')
    ->join('sensors', 'sensors.id', '=', 'sensor_latest_data.sensor_id')
    ->join('hmis', 'hmis.id', '=', 'sensors.hmi_id')
    ->where('hmis.ip_address', '192.168.1.202')
    ->pluck('sensor_latest_data.status');
// Expected: ["OFFLINE", "OFFLINE", "OFFLINE", "OFFLINE", "OFFLINE"]
```

4. Pasang kembali kabel → dalam satu cycle berikutnya status kembali ke NORMAL/WARNING/CRITICAL sesuai nilai sensor

---

## Skenario Uji WARNING / CRITICAL

Jika nilai suhu atau kelembapan melampaui batas yang dikonfigurasi di tabel `rooms`, status akan berubah otomatis.

Untuk simulasi tanpa mengubah kondisi fisik ruangan, ubah batas limit sementara di DB:

```php
// Turunkan temp_max_limit ruangan agar sensor yang ada langsung WARNING
DB::table('rooms')->where('id', 1)->update(['temp_max_limit' => 15.00]);

// Tunggu satu cycle poller (5 detik), lalu cek
DB::table('sensor_latest_data')
    ->join('sensors', 'sensors.id', '=', 'sensor_latest_data.sensor_id')
    ->join('hmis', 'hmis.id', '=', 'sensors.hmi_id')
    ->where('hmis.ip_address', '192.168.1.200')
    ->pluck('sensor_latest_data.status');

// Kembalikan setelah test
DB::table('rooms')->where('id', 1)->update(['temp_max_limit' => 25.00]);
```

---

## Checklist Test Hardware

- [ ] Semua 5 HMI bisa di-ping
- [ ] `php artisan migrate:fresh --seed` berhasil
- [ ] `python poller.py` berjalan tanpa error
- [ ] Data masuk ke `sensor_latest_data` dalam 10 detik pertama
- [ ] Semua 25 sensor terbaca (status bukan OFFLINE semua)
- [ ] Cabut kabel 1 HMI → 5 sensor berubah OFFLINE
- [ ] Pasang kembali → status kembali normal
- [ ] Payload dashboard (`globalStats` + `rooms`) sesuai `API-SHAPE.md`

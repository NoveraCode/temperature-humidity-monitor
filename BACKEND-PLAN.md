# Backend Implementation Plan — SCADA Monitoring Dashboard

**Terakhir diperbarui:** 6 Maret 2026  
**Target Environment:** Mini PC Intel Celeron J1900 · MySQL · Laravel · Python `pymodbus` · PM2

---

## Keputusan Arsitektur (Final)

| Parameter | Keputusan |
|---|---|
| Database | MySQL / InnoDB |
| Jumlah Sensor | 25 sensor / 5 HMI |
| HMI IP Range | 192.168.1.200 – 192.168.1.204 · port 502 |
| Modbus Function Code | **FC4** (Input Register) |
| Register Mapping | Humidity = reg 0 · Temperature = reg 1 · Skala ÷10 |
| Slave ID per Sensor | `unit_id` 1–5 (kolom di tabel `sensors`) |
| Interval Polling | 5 detik |
| Chart History per-Room | Agregasi rata-rata per 15 menit |
| Chart History per-Sensor | Agregasi rata-rata per **5 menit** |
| Rentang Chart | 24 jam · 7 hari · 30 hari · 90 hari |
| Retensi Log | 90 hari (keduanya) |
| OFFLINE Rule | Timeout Modbus **3 detik** → langsung OFFLINE |
| Mekanisme OFFLINE Trigger | **Python → MySQL langsung** (Opsi A, tanpa HTTP) |
| Inertia SSR | **Dimatikan** (mengurangi beban J1900) |
| Self-Registration | **Ditutup** (LAN panel, akun dibuat admin) |

---

## 1. Skema Database

### Prinsip Desain
- `FLOAT` diganti `DECIMAL(5,2)` untuk suhu/kelembapan — mencegah rounding saat threshold & grafik.
- Kolom port/alamat Modbus pakai `UNSIGNED SMALLINT` / `UNSIGNED INT` — lebih hemat storage.
- Tabel "latest" (`sensor_latest_data`) HANYA menyimpan 1 baris per sensor, di-UPSERT setiap cycle.
- Tabel "history" berupa agregasi berkala (bukan raw 5 detik) agar DB tidak tumbuh tak terkendali.

### Hitungan Volume Baris (90 hari)

| Tabel | Frekuensi Insert | Estimasi 90 hari |
|---|---|---|
| `sensor_latest_data` | UPSERT tiap 5 detik (bukan insert baru) | Tetap **30 baris** |
| `sensor_logs` (per-room) | Insert tiap 15 menit, 6 room | ~52.000 baris |
| `sensor_readings` (per-sensor) | Insert tiap 5 menit, 30 sensor | ~777.600 baris |

> Angka ini ringan untuk J1900 selama index dipasang benar.

---

### 1.1 Tabel `rooms`

```sql
CREATE TABLE rooms (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)    NOT NULL,
  location        VARCHAR(100)    NULL,
  temp_max_limit  DECIMAL(5,2)    NOT NULL DEFAULT 25.00,
  hum_max_limit   DECIMAL(5,2)    NOT NULL DEFAULT 60.00,
  created_at      TIMESTAMP       NULL,
  updated_at      TIMESTAMP       NULL
) ENGINE=InnoDB;
```

---

### 1.2 Tabel `hmis`

```sql
CREATE TABLE hmis (
  id          BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  room_id     BIGINT UNSIGNED  NOT NULL,
  name        VARCHAR(100)     NOT NULL,
  ip_address  VARCHAR(45)      NOT NULL,            -- IPv4/IPv6
  port        SMALLINT UNSIGNED NOT NULL DEFAULT 502,
  is_active   TINYINT(1)       NOT NULL DEFAULT 1,
  created_at  TIMESTAMP        NULL,
  updated_at  TIMESTAMP        NULL,
  CONSTRAINT fk_hmis_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

### 1.3 Tabel `sensors`

```sql
CREATE TABLE sensors (
  id                   BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  hmi_id               BIGINT UNSIGNED  NOT NULL,
  name                 VARCHAR(100)     NOT NULL,
  modbus_address_temp  INT UNSIGNED     NOT NULL,
  modbus_address_hum   INT UNSIGNED     NOT NULL,
  created_at           TIMESTAMP        NULL,
  updated_at           TIMESTAMP        NULL,
  CONSTRAINT fk_sensors_hmi FOREIGN KEY (hmi_id) REFERENCES hmis(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

### 1.4 Tabel `sensor_latest_data` 🔥 (High-Frequency UPSERT)

```sql
CREATE TABLE sensor_latest_data (
  id           BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  sensor_id    BIGINT UNSIGNED  NOT NULL,
  temperature  DECIMAL(5,2)     NULL,
  humidity     DECIMAL(5,2)     NULL,
  status       ENUM('NORMAL','WARNING','CRITICAL','OFFLINE') NOT NULL DEFAULT 'OFFLINE',
  last_read_at TIMESTAMP        NULL,
  created_at   TIMESTAMP        NULL,
  updated_at   TIMESTAMP        NULL,

  UNIQUE KEY uq_sensor_latest (sensor_id),           -- wajib untuk UPSERT
  INDEX  idx_status           (status),              -- filter alarm dashboard
  INDEX  idx_last_read_at     (last_read_at),        -- deteksi sensor stagnan

  CONSTRAINT fk_latest_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

> **Aturan nilai saat OFFLINE:** `temperature` dan `humidity` **tidak di-null-kan** — nilai terakhir dipertahankan, hanya kolom `status` yang berubah ke `OFFLINE`. Ini agar dashboard selalu bisa menampilkan nilai terakhir yang diketahui.

---

### 1.5 Tabel `sensor_logs` (History per-Room, Agregasi 15 Menit)

```sql
CREATE TABLE sensor_logs (
  id              BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  room_id         BIGINT UNSIGNED  NOT NULL,
  avg_temperature DECIMAL(5,2)     NOT NULL,
  avg_humidity    DECIMAL(5,2)     NOT NULL,
  created_at      TIMESTAMP        NOT NULL,
  updated_at      TIMESTAMP        NULL,

  INDEX idx_room_time (room_id, created_at),         -- query chart per ruangan + rentang waktu

  CONSTRAINT fk_logs_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

---

### 1.6 Tabel `sensor_readings` (History per-Sensor, Agregasi 5 Menit)

```sql
CREATE TABLE sensor_readings (
  id          BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  sensor_id   BIGINT UNSIGNED  NOT NULL,
  avg_temp    DECIMAL(5,2)     NOT NULL,
  avg_hum     DECIMAL(5,2)     NOT NULL,
  created_at  TIMESTAMP        NOT NULL,

  INDEX idx_sensor_time (sensor_id, created_at),     -- query chart per sensor + rentang waktu

  CONSTRAINT fk_readings_sensor FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

> Tidak ada `updated_at` — baris ini tidak pernah diubah setelah di-insert.

---

## 2. Eloquent Models & Relationships

```
Room          hasMany   Hmi
              hasMany   SensorLog

Hmi           belongsTo Room
              hasMany   Sensor

Sensor        belongsTo Hmi
              hasOne    SensorLatestData
              hasMany   SensorReading

SensorLatestData  belongsTo  Sensor
SensorLog         belongsTo  Room
SensorReading     belongsTo  Sensor
```

### Eager Loading untuk Dashboard (tanpa N+1)
```php
$rooms = Room::with(['hmis.sensors.latestData'])->get();
```

### Select Kolom Minimal (hemat CPU pada J1900)
```php
$rooms = Room::with([
    'hmis.sensors' => fn($q) => $q->select(['id', 'hmi_id', 'name']),
    'hmis.sensors.latestData' => fn($q) => $q->select([
        'id', 'sensor_id', 'temperature', 'humidity', 'status', 'last_read_at'
    ]),
])
->select(['id', 'name', 'location', 'temp_max_limit', 'hum_max_limit'])
->get();
```

---

## 3. Python Poller — Mekanisme Status & OFFLINE

### Prinsip Utama
- **Koneksi MySQL dibuka sekali** di luar loop, tidak di-reconnect tiap cycle.
- **Timeout Modbus = 3 detik.** Jika exception terjadi, langsung eksekusi bulk update OFFLINE.
- **Tidak ada HTTP/API call** ke Laravel untuk update status — semua tulis langsung ke MySQL.

### Pemetaan Status

| Kondisi | Status |
|---|---|
| Baca berhasil, suhu/RH dalam batas `temp_max_limit` / `hum_max_limit` | `NORMAL` |
| Nilai melampaui batas room | `WARNING` |
| Nilai melampaui 2× batas (atau threshold kritis custom) | `CRITICAL` |
| Timeout Modbus / exception koneksi | `OFFLINE` |

### Alur per Cycle (pseudo-code)

```python
for hmi in active_hmis:
    try:
        client = ModbusTcpClient(hmi.ip_address, port=hmi.port, timeout=3)
        client.connect()

        bulk_data = []
        for sensor in hmi.sensors:
            temp = read_register(sensor.modbus_address_temp) / 10
            hum  = read_register(sensor.modbus_address_hum)  / 10
            status = compute_status(temp, hum, room.thresholds)
            bulk_data.append((sensor.id, temp, hum, status, now()))

        # 1 query untuk semua sensor di HMI ini
        cursor.executemany("""
            INSERT INTO sensor_latest_data
              (sensor_id, temperature, humidity, status, last_read_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
              temperature  = VALUES(temperature),
              humidity     = VALUES(humidity),
              status       = VALUES(status),
              last_read_at = VALUES(last_read_at),
              updated_at   = VALUES(updated_at)
        """, bulk_data)
        db.commit()

    except (ModbusException, ConnectionException):
        # Bulk update OFFLINE via JOIN — 1 query, tanpa loop per sensor
        cursor.execute("""
            UPDATE sensor_latest_data sld
            JOIN sensors s ON s.id = sld.sensor_id
            WHERE s.hmi_id = %s
              AND sld.status != 'OFFLINE'    -- hindari write berulang saat sudah OFFLINE
        """, (hmi.id,))
        db.commit()

time.sleep(5)
```

> **Mengapa `status != 'OFFLINE'` pada update offline?**
> Mencegah Python menulis ke disk tiap 5 detik meskipun HMI sudah pasti mati — mengurangi write amplification secara signifikan.

---

## 4. Laravel Scheduler (Housekeeping)

Definisikan di `routes/console.php` menggunakan closure schedule (Laravel 11+ tidak ada `Kernel.php`):

```php
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

// Aggregasi per-room ke sensor_logs (setiap 15 menit)
Schedule::call(function () {
    $rooms = \App\Models\Room::with('hmis.sensors.latestData')->get();
    foreach ($rooms as $room) {
        $sensors = $room->hmis->flatMap->sensors;
        $online  = $sensors->filter(fn($s) => $s->latestData?->status !== 'OFFLINE');
        if ($online->isEmpty()) continue;

        \App\Models\SensorLog::create([
            'room_id'         => $room->id,
            'avg_temperature' => $online->avg(fn($s) => $s->latestData->temperature),
            'avg_humidity'    => $online->avg(fn($s) => $s->latestData->humidity),
        ]);
    }
})->everyFifteenMinutes()->name('aggregate-room-logs');

// Aggregasi per-sensor ke sensor_readings (setiap 5 menit)
Schedule::call(function () {
    $sensors = \App\Models\Sensor::with('latestData')
        ->whereHas('latestData', fn($q) => $q->where('status', '!=', 'OFFLINE'))
        ->get();

    $rows = $sensors->map(fn($s) => [
        'sensor_id'  => $s->id,
        'avg_temp'   => $s->latestData->temperature,
        'avg_hum'    => $s->latestData->humidity,
        'created_at' => now(),
    ])->toArray();

    \App\Models\SensorReading::insert($rows);   // bulk insert 1 query
})->everyFiveMinutes()->name('aggregate-sensor-readings');

// Purge data > 90 hari (setiap hari tengah malam)
Schedule::call(function () {
    DB::table('sensor_logs')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();

    DB::table('sensor_readings')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();
})->daily()->name('purge-old-logs')->withoutOverlapping();
```

---

## 5. Dashboard Controller — Payload ke Inertia

### Controller
```php
// app/Http/Controllers/DashboardController.php
public function index(): Response
{
    $rooms = Room::with([
        'hmis.sensors' => fn($q) => $q->select(['id', 'hmi_id', 'name']),
        'hmis.sensors.latestData' => fn($q) =>
            $q->select(['id', 'sensor_id', 'temperature', 'humidity', 'status', 'last_read_at']),
    ])
    ->select(['id', 'name', 'location', 'temp_max_limit', 'hum_max_limit'])
    ->get();

    $payload = $rooms->map(function (Room $room) {
        $sensors = $room->hmis->flatMap->sensors;
        $online  = $sensors->filter(fn($s) => $s->latestData?->status !== 'OFFLINE');

        return [
            'id'           => $room->id,
            'name'         => $room->name,
            'location'     => $room->location,
            'room_avg_temp' => $online->avg(fn($s) => $s->latestData->temperature),
            'room_avg_hum'  => $online->avg(fn($s) => $s->latestData->humidity),
            'status'        => $this->resolveRoomStatus($sensors),
            'sensors'       => $sensors->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'temperature' => $s->latestData?->temperature,
                'humidity'    => $s->latestData?->humidity,
                'status'      => $s->latestData?->status ?? 'OFFLINE',
            ]),
        ];
    });

    $globalAvgTemp = $payload->whereNotNull('room_avg_temp')->avg('room_avg_temp');
    $globalAvgHum  = $payload->whereNotNull('room_avg_hum')->avg('room_avg_hum');
    $activeAlarms  = $payload->flatMap(fn($r) => $r['sensors'])
                             ->whereIn('status', ['WARNING', 'CRITICAL'])
                             ->count();

    return Inertia::render('dashboard', [
        'globalStats' => [
            'avg_temp'      => round($globalAvgTemp, 1),
            'avg_hum'       => round($globalAvgHum, 1),
            'active_alarms' => $activeAlarms,
            'last_update'   => now()->toDateTimeString(),
        ],
        'rooms' => $payload,
    ]);
}

private function resolveRoomStatus($sensors): string
{
    $statuses = $sensors->pluck('latestData.status')->filter()->unique();
    if ($statuses->contains('CRITICAL'))   return 'CRITICAL';
    if ($statuses->contains('WARNING'))    return 'WARNING';
    if ($statuses->every(fn($s) => $s === 'OFFLINE')) return 'OFFLINE';
    return 'NORMAL';
}
```

### JSON Payload (sesuai API-SHAPE.md)
```json
{
  "globalStats": {
    "avg_temp": 21.4,
    "avg_hum": 49.0,
    "active_alarms": 2,
    "last_update": "2026-03-02 13:25:00"
  },
  "rooms": [
    {
      "id": 1,
      "name": "RUANG CCTV",
      "location": "LT.2",
      "room_avg_temp": 22.5,
      "room_avg_hum": 49.0,
      "status": "WARNING",
      "sensors": [
        { "id": 101, "name": "R.CCTV T/H 1", "temperature": 22.5, "humidity": 49.0, "status": "NORMAL" },
        { "id": 102, "name": "R.CCTV T/H 2", "temperature": 23.1, "humidity": 50.2, "status": "WARNING" }
      ]
    },
    {
      "id": 2,
      "name": "RUANG FIDS",
      "location": "LT.1",
      "room_avg_temp": null,
      "room_avg_hum": null,
      "status": "OFFLINE",
      "sensors": [
        { "id": 106, "name": "R.FIDS T/H 1", "temperature": null, "humidity": null, "status": "OFFLINE" }
      ]
    }
  ]
}
```

---

## 6. Konfigurasi Keamanan & Operasional

### Matikan Self-Registration
```php
// config/fortify.php
'features' => [
    // Features::registration(),  <-- comment out / hapus
    Features::resetPasswords(),
    Features::emailVerification(),
    Features::updateProfileInformation(),
    Features::updatePasswords(),
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]),
],
```

### Matikan Inertia SSR
```php
// config/inertia.php
'ssr' => [
    'enabled' => false,
    ...
]
```

### DB User Poller (MySQL — prinsip least privilege)
```sql
-- Buat user khusus poller dengan akses minimal
CREATE USER 'poller'@'localhost' IDENTIFIED BY 'strong_password';

-- Hanya baca config (rooms, hmis, sensors)
GRANT SELECT ON scada_db.rooms   TO 'poller'@'localhost';
GRANT SELECT ON scada_db.hmis    TO 'poller'@'localhost';
GRANT SELECT ON scada_db.sensors TO 'poller'@'localhost';

-- Write hanya ke tabel high-frequency
GRANT INSERT, UPDATE ON scada_db.sensor_latest_data TO 'poller'@'localhost';
FLUSH PRIVILEGES;
```

### MySQL Optimization (my.ini)
```ini
[mysqld]
performance_schema   = OFF
innodb_buffer_pool_size = 256M
innodb_flush_log_at_trx_commit = 2   ; sedikit lebih ringan, masih aman untuk SCADA LAN
```

### PM2 Process List
```bash
pm2 start "php artisan serve --host=0.0.0.0 --port=80" --name "laravel-scada"
pm2 start poller.py --name "python-modbus-worker" --interpreter python3
pm2 start "php artisan schedule:work" --name "laravel-scheduler"
pm2 save
pm2 startup
```

---

## 7. Checklist Implementasi

### Fase 1 — Database ✅
- [x] Buat migration: `rooms`, `hmis`, `sensors`
- [x] Buat migration: `sensor_latest_data` (dengan UNIQUE + index)
- [x] Buat migration: `sensor_logs` (dengan composite index)
- [x] Buat migration: `sensor_readings` (dengan composite index)
- [x] Tambah kolom `unit_id` (Modbus Slave ID) ke tabel `sensors`
- [x] Jalankan `php artisan migrate`

### Fase 2 — Models & Relationships ✅
- [x] Buat model `Room`, `Hmi`, `Sensor`, `SensorLatestData`, `SensorLog`, `SensorReading`
- [x] Definisikan semua relasi Eloquent
- [x] Buat factory + seeder untuk testing lokal (5 ruangan, IP 192.168.1.200–204, unit_id 1–5)

### Fase 3 — Dashboard Backend ✅
- [x] Buat `DashboardController` dengan eager loading + kolom minimal
- [x] Update `routes/web.php` agar `/dashboard` pakai controller
- [x] Verifikasi payload sesuai `API-SHAPE.md`

### Fase 4 — Scheduler ✅
- [x] Tambah 3 scheduled task di `routes/console.php`
- [x] Test manual: `php artisan schedule:run`

### Fase 5 — Python Poller ✅
- [x] Implementasi `poller.py` dengan single DB connection
- [x] Implementasi UPSERT bulk untuk data normal
- [x] Implementasi bulk OFFLINE via JOIN saat timeout
- [x] Gunakan Function Code 4 (Input Register) dengan `unit_id` sebagai Slave ID
- [ ] Test simulasi: cabut kabel / matikan 1 HMI → status berubah ke OFFLINE

### Fase 6 — Security & Hardening
- [ ] Matikan self-registration di `config/fortify.php`
- [ ] Matikan SSR di `config/inertia.php`
- [ ] Buat DB user `poller` dengan hak minimal
- [ ] Terapkan MySQL optimization di `my.ini`
- [ ] Setup PM2 + `pm2 startup`

---

## Referensi
- [SCHEMA-DATABASE.md](SCHEMA-DATABASE.md) — Rancangan skema awal
- [README-BACKEND.md](README-BACKEND.md) — Arsitektur backend & environment setup
- [API-SHAPE.md](API-SHAPE.md) — Kontrak JSON payload dashboard ↔ frontend

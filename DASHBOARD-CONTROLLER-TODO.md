# DashboardController — Yang Masih Dibutuhkan

## 1. `chartLogs` — Data Grafik Per Ruangan

**Dibutuhkan oleh:** `dashboard.tsx` → komponen `MiniLineChart`

**Tipe yang diharapkan frontend:**

```ts
chartLogs: Record<number, ChartPoint[]>;
// key = room_id
// ChartPoint = { time: string, avg_temperature: number, avg_humidity: number }
```

**Yang perlu dilakukan:**

- Query `SensorLog` per ruangan, dikelompokkan per `room_id`
- Ambil ~20 entri terakhir diurutkan `created_at ASC`
- Format kolom `created_at` ke string waktu singkat, misalnya `"14:35"`
- Tambahkan ke response `Inertia::render` sebagai key `chartLogs`

**Contoh query:**

```php
$chartLogs = SensorLog::query()
    ->whereIn('room_id', $rooms->pluck('id'))
    ->orderBy('created_at')
    ->get()
    ->groupBy('room_id')
    ->map(fn ($logs) => $logs->takeLast(20)->map(fn ($log) => [
        'time'            => $log->created_at->format('H:i'),
        'avg_temperature' => round((float) $log->avg_temperature, 1),
        'avg_humidity'    => round((float) $log->avg_humidity, 1),
    ])->values()->all());
```

---

## 2. `rooms[].temp_max_limit` & `rooms[].hum_max_limit`

**Status:** Sudah di-`select` dari DB, tapi **tidak dimasukkan ke dalam array payload** di method `index()`.

**Yang perlu dilakukan:**
Tambahkan dua field ini ke dalam array yang di-return di dalam `$rooms->map(...)`:

```php
'temp_max_limit' => $room->temp_max_limit,
'hum_max_limit'  => $room->hum_max_limit,
```

**Kegunaan:** Dipakai untuk menentukan batas threshold tampilan (warning/critical) di gauge dan sensor card.

---

## 3. `rooms[].last_update`

**Status:** Ada di tipe `RoomData` frontend, tapi **tidak ada di payload controller**.

**Saat ini:** Footer menggunakan `globalStats.last_update` sebagai fallback, tapi idealnya tiap ruangan punya timestamp update sendiri berdasarkan `last_read_at` sensor terbaru.

**Yang perlu dilakukan:**

```php
'last_update' => $online->max(fn ($s) => $s->latestData?->last_read_at),
```

---

## 4. `sensors[].last_read_at`

**Status:** Ada di tipe `SensorData` frontend, tapi **tidak dipass** oleh controller.

**Yang perlu dilakukan:**

```php
'last_read_at' => $s->latestData?->last_read_at,
```

> Catatan: `last_read_at` sudah ada di query `latestData` select, tinggal di-map saja.

---

## 5. Auto-refresh `chartLogs`

Saat ini `router.reload()` di frontend hanya mereload `['rooms', 'globalStats']`.
Setelah `chartLogs` ditambahkan ke controller, tambahkan `'chartLogs'` ke array reload:

```ts
router.reload({ only: ['rooms', 'globalStats', 'chartLogs'] });
```

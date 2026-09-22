# Optimisasi Layer Peta Bencana (GET /api/layers/{type})

Dokumen ini menjelaskan masalah performa pada layer peta bencana (`flood`,
`landslide`, `drought`, `eruption`, `liquefaction`, `extreme_weather`) dan
perubahan yang dilakukan untuk mengatasinya, di backend (Laravel + PostGIS)
maupun frontend (Expo / React Native).

## Gejala awal

- Data layer dibatasi (`LIMIT 150`) supaya tidak berat — sebagian data tidak
  pernah tampil di peta.
- Warna polygon tidak konsisten dengan warna di UI (filter chip/legend).
- Setelah limit dihapus: load lama, dan peta lag parah saat zoom in/out.
- Untuk layer `extreme_weather` (23.587 baris), `drought` (11.724 baris), dan
  `liquefaction` (8.346 baris): app sampai "Not Responding" di Expo Go.

## Root cause

1. **`LIMIT 150`** di query memang sengaja dipasang supaya tidak berat, tapi
   efeknya banyak data hilang dari peta.
2. **MultiPolygon hanya diambil bagian pertamanya** — kode lama cuma
   mengambil ring pertama dari `MultiPolygon`, membuang bagian lain yang
   terpisah secara geografis.
3. **Query PostGIS (`ST_Transform` + `ST_Simplify`) dihitung ulang setiap
   request** — tidak ada caching, jadi tiap kali app minta layer, server
   PostGIS harus memproses ulang dari nol.
4. **Data hazard grid (`drought`, `liquefaction`, `extreme_weather`) sangat
   padat**: puluhan ribu polygon kecil (grid cell) yang tersebar di area
   geografis yang justru kecil (~30 km). Ini artinya:
   - Viewport/bbox filtering saja tidak banyak membantu — begitu area itu
     terlihat di peta, hampir semua datanya ikut ter-load.
   - `react-native-maps` harus me-render ribuan komponen `<Polygon>` native
     sekaligus, dan me-redraw semuanya tiap kali kamera peta berubah
     (pan/zoom) — inilah penyebab utama lag, bukan kecepatan fetch data.
5. **Backend hanya listen di `127.0.0.1`**, bukan `0.0.0.0` — device fisik di
   jaringan yang sama tidak bisa mengakses API sama sekali (`Network Error`
   di Axios). Ini masalah terpisah dari performa layer, tapi sempat jadi
   blocker di awal.

## Solusi

### 1. Warna polygon sesuai jenis bencana (frontend)

`app/maps.tsx` sekarang menentukan warna `fillColor`/`strokeColor` polygon
dari `disasterType`-nya sendiri (via `DISASTER_LAYERS` di `data/riskData.ts`),
bukan dari nilai yang dikirim backend. Banjir = biru (`#3B82F6`), cuaca
ekstrem = ungu (`#7C3AED`), dst — konsisten dengan filter chip di UI.

### 2. Backend bind ke `0.0.0.0`

```
php artisan serve --host=0.0.0.0 --port=8000
```

Supaya device fisik di jaringan WiFi yang sama bisa akses API lewat IP LAN
laptop (`192.168.x.x:8000`), bukan cuma `127.0.0.1`.

### 3. Hapus `LIMIT`, ambil semua bagian `MultiPolygon`

`LayerController::rowsToPolygons()` sekarang mengambil **setiap ring/bagian**
dari `MultiPolygon` (bukan cuma yang pertama), masing-masing jadi entri
polygon sendiri dengan id `{type}-{id}-{partIndex}`.

### 4. Spatial index (GiST) di semua tabel hazard

Migration `2026_09_22_000001_add_gist_index_to_hazard_tables.php` menambahkan
index spasial ke kolom `geom` di tabel `flood`, `landslide`, `drought`,
`eruption`, `liquefaction`, `extremeweather` — supaya query berbasis area
(bounding box) bisa pakai index, bukan full table scan.

### 5. Query per-viewport (bbox) untuk layer kecil

Untuk layer yang datanya relatif sedikit (`flood`, `landslide`, `eruption` —
di bawah ~1.500 baris), endpoint `GET /api/layers/{type}` menerima parameter
opsional `min_lat`, `max_lat`, `min_lng`, `max_lng`. Query memakai operator
`&&` (bounding-box overlap, index-backed) untuk hanya mengambil baris yang
beririsan dengan area yang diminta. Bbox di-snap ke grid 0,05° supaya
pan/zoom kecil di area yang sama bisa hit cache yang sama.

### 6. Union + simplify agresif untuk layer padat (hazard grid)

Untuk `drought`, `liquefaction`, `extreme_weather` — yang merupakan grid
hazard sangat padat di area geografis kecil — pendekatan bbox saja tidak
cukup. Solusinya:

- **`ST_Union`** semua polygon dikelompokkan per level bahaya (`gridcode`:
  low/medium/high), menggabungkan sel-sel grid yang bersebelahan jadi satu
  bentuk.
- **`ST_Simplify`** dengan tolerance **150 meter** (dalam satuan proyeksi asli
  data, bukan derajat) diterapkan sebelum transform ke lat/lng — memangkas
  jumlah titik/vertex secara drastis.

Hasilnya, jumlah fitur per layer turun drastis:

| Layer | Baris asli | Setelah union+simplify |
|---|---|---|
| extreme_weather | 23.587 | 313 fitur |
| drought | 11.724 | 422 fitur |
| liquefaction | 8.346 | 123 fitur |

### 7. Union dihitung di luar request (artisan command), bukan live

`ST_Union` atas puluhan ribu geometri butuh waktu ~2–7 detik per layer.
Kalau dihitung langsung saat ada request dari app, itu akan:

- Memblokir `php artisan serve` (single-threaded) selama proses berjalan,
  membuat **semua** request lain (termasuk dari HP) ikut macet.
- Membuat Expo Go terlihat "Not Responding" karena menunggu response yang
  tidak kunjung datang.

Solusinya: komputasi ini dipindah ke command terpisah yang dijalankan
manual/terjadwal, hasilnya disimpan di cache Laravel (30 hari):

```
php artisan layers:warm
```

Command ini didefinisikan di `routes/console.php`, memanggil
`LayerController::computeHeavyLayer()` untuk tiap layer di
`LayerController::HEAVY_TYPES`, lalu menyimpan hasilnya ke cache dengan key
dari `LayerController::heavyCacheKey()`.

Request dari app ke `GET /api/layers/extreme_weather` (dst) sekarang tinggal
baca dari cache — response ~400ms, bukan menunggu union selesai.

### 8. Viewport culling di frontend (render, bukan fetch)

`app/maps.tsx`:

- `MapView` punya `onRegionChangeComplete` yang menyimpan region peta yang
  sedang terlihat (`visibleRegion`), di-debounce 350ms supaya gesture
  pan/zoom tidak memicu banyak fetch beruntun.
- Layer di-fetch dengan bbox yang **lebih lebar dari viewport** (padding 1.5x
  di tiap sisi) supaya pan kecil tidak perlu fetch ulang.
- Dari data yang sudah di-fetch, hanya polygon yang bounding box-nya
  beririsan dengan viewport yang benar-benar terlihat yang di-render
  (`visibleDisasterPolygons`, dihitung dari `polygonsWithBBox` yang di-cache
  per data load). Ini mengurangi jumlah komponen `<Polygon>` yang harus
  di-redraw React Native Maps saat kamera berubah — inilah yang paling
  berpengaruh terhadap kelancaran zoom/pan, lebih dari sekadar ukuran data.

### 9. Caching bertingkat

- **Backend**: `Cache::remember()` per kombinasi `type + tolerance + bbox`
  (layer kecil) atau `type` saja (layer padat/heavy), TTL 30 hari.
- **Frontend**: `services/LayerService.ts` cache in-memory per sesi, key
  `${type}:${snappedBBoxKey}` (grid 0,05° — harus sama dengan `GRID_SIZE` di
  backend), supaya pan berulang di tile yang sama tidak fetch ulang ke server
  sama sekali selama sesi masih berjalan.

## File yang berubah

**Backend** (`backend-laravel`):
- `app/Http/Controllers/Api/LayerController.php` — logic query, union,
  simplify, caching.
- `routes/console.php` — command `layers:warm`.
- `database/migrations/2026_09_22_000001_add_gist_index_to_hazard_tables.php`
  — spatial index.

**Frontend** (`titikhuni-main`):
- `app/maps.tsx` — warna polygon per disasterType, viewport culling,
  fetch berbasis bbox + debounce.
- `services/LayerService.ts` — `fetchLayer`/`fetchActiveLayers` menerima
  bbox, cache per tile.

## Cara menjalankan / maintenance

1. Backend harus jalan dengan bind ke semua interface supaya bisa diakses HP:
   ```
   php artisan serve --host=0.0.0.0 --port=8000
   ```
2. Migration index spasial (sekali saja, atau tiap deploy baru):
   ```
   php artisan migrate
   ```
3. **Setiap kali data hazard di database berubah** (import shapefile baru,
   dsb.), jalankan ulang supaya cache & hasil union ikut ter-update:
   ```
   php artisan cache:clear
   php artisan layers:warm
   ```
4. Kalau tolerance simplify atau grid size diubah di
   `LayerController.php`, cache key otomatis berubah (ada versi `v4` di
   key), jadi tidak perlu manual invalidasi — tapi tetap disarankan jalankan
   `layers:warm` ulang setelah deploy supaya user pertama tidak menunggu
   union pertama kali (walau untuk layer kecil ini tidak masalah, union
   besar tetap sebaiknya di-warm duluan).

## Batasan yang masih ada

- Union+simplify di `layers:warm` pakai tolerance tetap (150m). Kalau data
  hazard berubah drastis (jauh lebih detail/luas), tolerance ini mungkin
  perlu disesuaikan lagi.
- Layer kecil (`flood`, `landslide`, `eruption`) masih full-fetch (tanpa
  union) kalau endpoint dipanggil tanpa parameter bbox — ini dipakai di
  halaman admin (`app/(tabsAdmin)/spatial.tsx`), yang belum diupdate untuk
  kirim bbox maupun untuk menampilkan layer `extreme_weather`.

# TEMUAN: Sinkronisasi Frontend (titikhuni-main) & Backend (LOKATANI API)

Hasil analisis FE (React Native/Expo, `titikhuni-main`) vs BE (Laravel 12, `backend-laravel`). Diurutkan berdasarkan prioritas perbaikan.

---

## 1. [KRITIS] Fitur inti masih mock, tidak persist ke server

- **`app/(tabsOwner)/addland.tsx`** (`handlePublish`, ~baris 229-301) — form tambah properti hanya `setLands(prev => [newLand, ...prev])`. **Tidak pernah memanggil `POST /api/lands`.** Data hilang saat app di-refresh/restart.
- **`app/(tabsAdmin)/approval.tsx`** (`handleAccept` ~baris 63-84, `submitRejection` ~baris 91-113) — approve/reject listing hanya `setLands(prev => prev.map(...))` lokal. **Tidak pernah memanggil `PATCH /api/lands/{id}/status`.** Keputusan admin tidak tersimpan di server.

**Perbaikan:** ganti kedua handler untuk memanggil endpoint backend yang sudah ada, lalu update state dari response (bukan objek lokal).

---

## 2. [TINGGI] Modul notifikasi backend tidak dipakai sama sekali

- `GET /api/notifications`, `PUT /api/notifications/read-all`, `PUT /api/notifications/{id}/read` — **tidak ada satupun panggilan** dari FE.
- Notifikasi yang tampil di `homeAdmin.tsx` / `homeOwner.tsx` berasal dari `NotificationItem[]` lokal di `contexts/LandContext.tsx:45-54,137-155` (`addNotification`).
- Komentar di `LandContext.tsx:136` sendiri mengakui: *"Notification creation is also done server-side in PATCH /api/lands/:id/status"* — developer sadar backend seharusnya jadi sumber kebenaran, tapi belum disambungkan.

**Perbaikan:** setelah endpoint status/lands disambung (poin 1), ganti sumber notifikasi FE dari local state ke `GET /api/notifications`, dan panggil `PUT /read` / `PUT /read-all` saat notifikasi dibuka.

---

## 3. [TINGGI] Ketidakpastian casing response (camelCase vs snake_case)

- `contexts/AuthContext.tsx:122-124` defensif baca **dua kemungkinan casing sekaligus**: `apiUser.fullName || apiUser.full_name`, `apiUser.userType || apiUser.user_type` — tanda kontrak response belum difinalisasi.
- `contexts/LandContext.tsx` (`mapApiLand`, ~baris 78-110) sebaliknya **asumsi backend sudah camelCase murni** (`d.isForSale`, `d.certificateImage`, `d.inquiriesCount`) tanpa fallback apapun. Kalau Eloquent Laravel default (snake_case: `is_for_sale`, `certificate_image`, `inquiries_count`, `owner_id`) belum di-cast lewat API Resource, field-field ini akan **`undefined` di FE**.

**Perbaikan:** buat Laravel API Resource (`JsonResource`) untuk `Land`, `User`, `Conversation`, `Message`, `Notification` dengan casing eksplisit (rekomendasi: camelCase di semua response agar cocok dengan asumsi FE saat ini). Setelah itu hapus logic dual-casing di `AuthContext`.

---

## 4. [SEDANG] Format error tidak konsisten

- FE hardcode baca `error.response?.data?.error` (string) — lihat `AuthContext.tsx:108,135`.
- Default Laravel validation error (`ValidationException`) adalah `{"message": "...", "errors": {field: [...]}}`, **bukan** `{"error": "..."}`.

**Perbaikan:** seragamkan format error di semua controller Laravel (custom exception handler atau helper response), lalu update FE untuk parsing format yang disepakati.

---

## 5. [SEDANG] Auth lifecycle tidak lengkap

- `GET /api/auth/me` — **tidak pernah dipanggil**. Sesi FE hanya divalidasi dari cache AsyncStorage (`AuthContext.tsx:68-80`), token expired/invalid di server tidak terdeteksi sampai request lain gagal 401.
- `POST /api/auth/logout` — `logout()` di `AuthContext.tsx:141-148` hanya `AsyncStorage.multiRemove([TOKEN_KEY, USER_KEY])`, **tidak memanggil backend**. Token JWT lama tetap valid di server (tidak di-blacklist) — celah keamanan kecil.
- Interceptor 401 di `services/apiClient.ts:58-67` cuma `console.warn`, tidak auto-logout/redirect.

**Perbaikan:** panggil `GET /auth/me` saat app start untuk validasi sesi; panggil `POST /auth/logout` sungguhan saat logout; tangani 401 global dengan clear session + redirect ke login.

---

## 6. [SEDANG] Field registrasi hilang saat dikirim ke backend

- `register()` di `AuthContext.tsx:86-90` hanya mengirim `{email, password, fullName, userType}`.
- Field `phone, address, gender, dob, location, photo` yang ada di type `RegisterData` (baris 30-41) **tidak pernah dikirim** ke backend meski kemungkinan dikumpulkan di form.

**Perbaikan:** lengkapi payload request register agar semua field yang dikumpulkan form ikut terkirim, sesuaikan dengan field yang diterima `AuthController@register`.

---

## 7. [RENDAH] Type `Land` FE tidak punya representasi "tanah"

- Type properti FE hanya `"house" | "apartment"` (`LandContext.tsx:23`), padahal backend mendukung rumah/tanah/apartemen.

**Perbaikan:** tambahkan `"land"` ke union type dan sesuaikan UI (label, icon, filter) yang bergantung pada tipe properti.

---

## 8. [RENDAH/CLEANUP] Duplikasi & dead code pemanggilan API

- `contexts/LandContext.tsx:121` memanggil `api.get("/lands")` **langsung**, padahal `services/PropertyService.ts:43-49` sudah punya `fetchLands()` untuk endpoint yang sama.
- `services/PropertyService.ts:52-60` (`fetchNearbyLands`, untuk `GET /lands/spatial/nearby`) sudah siap tapi **tidak pernah dipanggil** dari layar manapun — fitur "cari properti terdekat" belum ada di UI.

**Perbaikan:** konsolidasikan semua panggilan `/lands` lewat `PropertyService`, hapus panggilan langsung di context. Implementasikan UI untuk fitur nearby search kalau memang direncanakan.

---

## 9. [PERLU KLARIFIKASI] Format response array vs paginated

- Semua service (`PropertyService`, `LayerService`, `FacilityService`, `RiskService`, `chatService`) mengharapkan **`res.data` langsung sebagai array/objek**, tanpa unwrap `{data: [...]}` ala Laravel API Resource collection atau `{data, meta, links}` ala `paginate()`.
- Kalau backend menambah `->paginate()` di kemudian hari pada `GET /lands` atau `GET /conversations`, FE akan pecah (`.map()` gagal karena `res.data` jadi objek, bukan array).

**Perbaikan:** kalau berencana pakai pagination, samakan lebih dulu — sepakati bentuk response final sebelum diimplementasikan di kedua sisi.

---

## 10. [PERLU KLARIFIKASI] Payload conversation/message tidak lengkap dari sisi FE

- `getOrCreateConversation()` (`services/chatService.ts:64-68`) hanya kirim `{propertyId, buyerId, ownerId}` — field lain (`buyerName`, `ownerName`, `propertyTitle`, dll) di parameter fungsi **tidak pernah dikirim**. Backend harus resolve semua data ini sendiri dari relasi.
- `postMessage()` (`chatService.ts:79-83`) hanya kirim `{text, senderRole}` — `senderId`/`senderName` diasumsikan backend ambil dari JWT auth user.

**Perbaikan:** verifikasi `ConversationController`/`MessageController` sudah resolve field-field ini dari relasi Eloquent, bukan mengharapkannya dari request body.

---

## 11. [INFORMASI] Modul yang masih 100% dummy/lokal, belum ada endpoint backend

Modul-modul ini berjalan penuh di atas data lokal (state React / AsyncStorage), **tidak ada endpoint backend yang berkaitan** dalam daftar API LOKATANI saat ini — perlu diklarifikasi apakah in-scope backend ini atau modul terpisah:

| File | Keterangan |
|---|---|
| `app/(tabsAdmin)/users.tsx:31-38` | `dummyUsers` — 8 user hardcoded |
| `app/(tabsAdmin)/complaints.tsx:33` | `dummyComplaints` hardcoded |
| `app/(tabsAdmin)/verification.tsx:26` | `dummyData` verifikasi dokumen/sertifikat |
| `app/(tabsAdmin)/spatial.tsx:36` | `dummySpatialData`, walau `LayerService`/`FacilityService` real sudah ada |
| `app/(tabsGuest)/homeGuest.tsx:86` | Campuran data real (`useLands()`) dan dummy dalam satu layar |
| `contexts/ProductContext.tsx:38-79` | Produk pertanian (vegetables/grains/fruits) — full CRUD lokal di AsyncStorage, tidak ada koneksi API |
| `contexts/CartContext.tsx`, `data/products.ts` | Keranjang belanja — full lokal, tidak terhubung API |
| `contexts/BookmarkContext.tsx` | Favorit properti disimpan lokal (`@loka:favorites`) di AsyncStorage, bukan lewat backend — padahal backend punya counter `favorites` di `Land`. Bookmark hilang kalau ganti device. |
| `contexts/ActivityContext.tsx`, `contexts/StatsContext.tsx` | State in-memory murni, tidak persist bahkan ke AsyncStorage — dipakai untuk activity log/statistik dashboard yang seharusnya dari agregasi data backend |

---

## Referensi cepat: endpoint backend & status pemakaian FE

| Endpoint | Dipakai FE? | Lokasi |
|---|---|---|
| `POST /auth/register` | ✅ | `AuthContext.tsx:86` |
| `POST /auth/login` | ✅ | `AuthContext.tsx:116` |
| `GET /auth/me` | ❌ | - |
| `POST /auth/logout` | ❌ | - |
| `GET /lands` | ✅ (duplikat, lihat poin 8) | `LandContext.tsx:121`, `PropertyService.ts:47` |
| `GET /lands/spatial/nearby` | ⚠️ ada service, tidak dipanggil UI | `PropertyService.ts:58` |
| `GET /lands/{id}` | ✅ | `PropertyService.ts:64` |
| `GET /lands/{id}/risk` | ✅ | `RiskService.ts:16` |
| `GET /lands/{id}/facilities` | ✅ | `FacilityService.ts:24` |
| `POST /lands` | ❌ (kritis, poin 1) | - |
| `PATCH /lands/{id}/status` | ❌ (kritis, poin 1) | - |
| `PUT /lands/{id}/analytics` | ✅ | `LandContext.tsx:162,169,176` |
| `GET /layers/{type}` | ✅ | `LayerService.ts:48` |
| `GET /conversations` | ✅ | `chatService.ts:43` |
| `POST /conversations` | ✅ | `chatService.ts:64` |
| `PUT /conversations/{id}/read` | ✅ | `chatService.ts:87` |
| `GET /conversations/{id}/messages` | ✅ | `chatService.ts:48` |
| `POST /conversations/{id}/messages` | ✅ | `chatService.ts:79` |
| `GET /notifications` | ❌ (tinggi, poin 2) | - |
| `PUT /notifications/read-all` | ❌ | - |
| `PUT /notifications/{id}/read` | ❌ | - |
</content>
</invoke>

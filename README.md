# Hajj API Proxy

Dokumentasi singkat project ini hanya mencakup endpoint yang tersedia dan cara penggunaannya.

## Cara Menjalankan

1. Siapkan file `.env` dengan minimal konfigurasi berikut:

```env
HAJJ_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/web/external/15/your-endpoint"
HAJJ_UPSTREAM_KEY="isi_dengan_x_key_asli"
HAJJ_JADWAL_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/web/external/16/4df1d070-3748-11ea-838f-e170a6dffa79"
HAJJ_JADWAL_CACHE_TTL=3600
HAJJ_EMBARKASI_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/mobile/detail/4"
HAJJ_EMBARKASI_UPSTREAM_KEY="isi_dengan_x_key_embarkasi"
HAJJ_EMBARKASI_CACHE_TTL=86400
HAJJ_ADMIN_TOKEN="isi_dengan_token_admin_kuat"
# Opsional bila PHP/cURL di Windows belum punya CA bundle:
HAJJ_TLS_VERIFY=false
# HAJJ_CURL_CAINFO="C:\path\to\cacert.pem"
```

2. Jalankan server lokal:

```powershell
php -S 127.0.0.1:8000 routes.php
```

3. Akses API di:

```text
http://127.0.0.1:8000
```

## Endpoint Yang Tersedia

### `GET /jadwal/{embarkasi}/{kloter}`

Cek jadwal keberangkatan haji berdasarkan embarkasi dan kloter.

Aturan:

- hanya menerima `GET`
- `embarkasi` wajib 3 huruf kapital (contoh: `SUB`)
- `kloter` wajib 1-3 digit angka (contoh: `100`)
- path atau format parameter invalid mengembalikan `400`

Contoh:

```bash
curl http://127.0.0.1:8000/jadwal/SUB/100
```

Contoh response sukses:

```json
{
  "success": true,
  "code": "00",
  "message": "Berhasil",
  "data": [
    {
      "tahun": 1447,
      "kloter": 100,
      "embarkasi": "SUB",
      "masuk_asrama_at": "2026-05-17T06:00:00",
      "berangkat_at": "2026-05-18T01:35:00"
    }
  ]
}
```

Catatan:
- `data` selalu array item jadwal.
- field waktu mengikuti pola `*_at`.
- Cast ke integer hanya untuk: `tahun`, `kloter`, `no_maktab`, `no_rumah`, `no_subdaker`.
- Saat ada perubahan schema jadwal, lakukan prune cache sebelum trafik normal agar payload cache lama tidak dipakai.

### `GET /embarkasi`

Ambil daftar area embarkasi haji.

Aturan:

- hanya menerima `GET`
- method selain `GET` mengembalikan `405`
- path turunan tidak valid mengembalikan `400`

Contoh:

```bash
curl http://127.0.0.1:8000/embarkasi
```

Contoh response sukses:

```json
{
  "success": true,
  "code": "00",
  "message": "Sukses",
  "data": [
    {
      "detail_id": 17,
      "header_id": 4,
      "detail_short": "JKG",
      "detail_long": "Jakarta - Pondok Gede (JKG)",
      "keterangan": "Jakarta",
      "is_aktif": "Y",
      "urutan": 1,
      "created_by": null,
      "created_date": "2019-12-06T02:51:48.594Z",
      "modified_by": "sa",
      "modified_date": "2020-01-17T02:47:34.122Z"
    }
  ]
}
```

### `GET /check/{no_porsi}`

Cek status porsi haji.

Aturan:

- hanya menerima `GET`
- `no_porsi` harus 10 digit angka
- method selain `GET` mengembalikan `405`
- nomor porsi atau path tidak valid mengembalikan `400`

Contoh:

```bash
curl http://127.0.0.1:8000/check/1234567890
```

Contoh response sukses:

```json
{
  "success": true,
  "code": "00",
  "message": "Berhasil",
  "data": {
    "no_porsi": "1234567890",
    "nama": "Budi",
    "kab_kode": null,
    "kabupaten": null,
    "prov_kode": null,
    "provinsi": null,
    "posisi_porsi": null,
    "kuota_prov": null,
    "estimasi_masehi": null,
    "estimasi_hijriah": null,
    "status_bayar": null
  }
}
```

### `GET /health`

Cek kesehatan service.

Contoh:

```bash
curl http://127.0.0.1:8000/health
```

### `GET /metrics`

Lihat metrics request, cache, dan upstream. Membutuhkan token admin.

Gunakan salah satu header:

- `X-Admin-Token: <token>`
- `Authorization: <token>`

Contoh:

```bash
curl -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/metrics
```

### `POST /metrics/reset`

Reset metrics. Membutuhkan token admin.

Contoh:

```bash
curl -X POST -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/metrics/reset
```

### `DELETE /metrics/reset`

Reset metrics. Membutuhkan token admin.

Contoh:

```bash
curl -X DELETE -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/metrics/reset
```

### `POST /cache/prune`

Hapus cache yang sudah expired. Membutuhkan token admin.

Contoh:

```bash
curl -X POST -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/cache/prune
```

### `DELETE /cache/prune`

Hapus cache yang sudah expired. Membutuhkan token admin.

Contoh:

```bash
curl -X DELETE -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/cache/prune
```

### `GET /`

Menampilkan info singkat service dalam format plain text.

Contoh:

```bash
curl http://127.0.0.1:8000/
```

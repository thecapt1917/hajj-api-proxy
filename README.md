# Hajj API Proxy

Reverse proxy API berbasis PHP (tanpa framework) untuk:
- cek status porsi haji (`/check`)
- daftar embarkasi (`/embarkasi`)
- jadwal keberangkatan (`/jadwal`)

## Menjalankan Lokal

1. Buat file `.env` di root project.
2. Isi dengan setup awal berikut (sesuai `.env.example`):

```env
HAJJ_CACHE_ENABLED=true
HAJJ_CACHE_TTL=900
HAJJ_EMBARKASI_CACHE_TTL=86400
HAJJ_JADWAL_CACHE_TTL=3600
HAJJ_CACHE_DIR="storage/cache/check"
HAJJ_METRICS_FILE="storage/metrics/check_metrics.json"

HAJJ_ADMIN_TOKEN="<TOKEN_ADMIN>"

# Jangan diubah untuk endpoint check default
HAJJ_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/web/external/15/fea54e26-3746-11ea-872f-4795600ca312"
HAJJ_UPSTREAM_KEY="isi_dengan_x_key_asli"

HAJJ_JADWAL_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/web/external/16/4df1d070-3748-11ea-838f-e170a6dffa79"
HAJJ_EMBARKASI_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/mobile/detail/4"
HAJJ_EMBARKASI_UPSTREAM_KEY="isi_dengan_x_key_embarkasi"

# Opsional untuk environment Windows/Laragon yang belum punya CA bundle
HAJJ_TLS_VERIFY=false
# HAJJ_CURL_CAINFO="C:\path\to\cacert.pem"
```

3. Jalankan server:

```powershell
php -S 127.0.0.1:8000 routes.php
```

4. Base URL:

```text
http://127.0.0.1:8000
```

## Konfigurasi Env

### Wajib
- `HAJJ_UPSTREAM_URL`
- `HAJJ_UPSTREAM_KEY`
- `HAJJ_EMBARKASI_UPSTREAM_KEY`

### Opsional
- `HAJJ_EMBARKASI_UPSTREAM_URL` (default ke endpoint Kemenag mobile)
- `HAJJ_JADWAL_UPSTREAM_URL` (default ke endpoint Kemenag jadwal)
- `HAJJ_CACHE_ENABLED` (`true/false`, default `true`)
- `HAJJ_CACHE_TTL` (default `900`)
- `HAJJ_EMBARKASI_CACHE_TTL` (default `86400`)
- `HAJJ_JADWAL_CACHE_TTL` (default `3600`)
- `HAJJ_CACHE_DIR` (default `storage/cache/check`)
- `HAJJ_METRICS_FILE` (default `storage/metrics/check_metrics.json`)
- `HAJJ_ADMIN_TOKEN` (wajib hanya jika pakai endpoint admin)
- `HAJJ_TLS_VERIFY` (`true/false`)
- `HAJJ_CURL_CAINFO` (path CA bundle)

## Perilaku Routing

- `GET /` mengembalikan halaman info `text/plain`.
- Untuk path tidak dikenal:
  - jika request `GET` dan header `Accept` berisi `text/html`, akan redirect `302` ke `/`
  - selain itu, response JSON `404`

## Header Response API

Semua endpoint API JSON mengirim header:
- `Content-Type: application/json; charset=utf-8`
- `Cache-Control: no-store`
- `X-Content-Type-Options: nosniff`

Endpoint dengan cache (`/check`, `/embarkasi`, `/jadwal`) juga mengirim `X-Cache`:
- `HIT` (dari cache)
- `MISS` (cache aktif, data diambil ke upstream)
- `BYPASS` (cache nonaktif)

## Endpoint

### `GET /check/{no_porsi}`

Validasi:
- method harus `GET` (selain itu `405`, `Allow: GET`)
- `no_porsi` harus 10 digit (`400` jika tidak valid)

Catatan response:
- sukses: `200`, `success: true`, `code: "00"`
- error bisnis upstream (`RC`): tetap `200`, `success: false`
- body upstream rusak/tidak valid: `502`
- exception internal: `500`

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
    "nama": "Nama Jamaah",
    "kab_kode": "0000",
    "kabupaten": "Nama Kabupaten",
    "prov_kode": "00",
    "provinsi": "Nama Provinsi",
    "posisi_porsi": "12345",
    "kuota_prov": "67890",
    "estimasi_masehi": "2035",
    "estimasi_hijriah": "1456",
    "status_bayar": "LUNAS"
  }
}
```

### `GET /embarkasi`

Validasi:
- method harus `GET` (selain itu `405`)
- path turunan namespace `/embarkasi/...` dianggap invalid (`400`)

Catatan response:
- success/error bisnis upstream valid tetap `200`
- upstream invalid `502`

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
      "detail_short": "JKG",
      "detail_long": "Jakarta - Pondok Gede (JKG)",
      "is_aktif": true
    }
  ]
}
```

### `GET /jadwal/{embarkasi}/{kloter}`

Validasi:
- method harus `GET` (selain itu `405`)
- `embarkasi` harus 3 huruf kapital (`^[A-Z]{3}$`)
- `kloter` harus 1-3 digit (`^\d{1,3}$`)

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

### `GET /health`

Status health berdasarkan:
- ketersediaan cURL
- konfigurasi upstream check (`HAJJ_UPSTREAM_URL`, `HAJJ_UPSTREAM_KEY`)
- probe upstream (dengan opsi TLS yang sama seperti runtime client)

Hasil:
- `200` jika sehat
- `503` jika tidak sehat

```bash
curl http://127.0.0.1:8000/health
```

Contoh response:

```json
{
  "success": true,
  "code": "00",
  "message": "Service sehat",
  "data": {
    "app": "ok",
    "php_version": "8.3.x",
    "curl_available": true,
    "upstream_configured": true,
    "upstream_reachable": true,
    "upstream_http_code": 200,
    "timestamp_utc": "2026-04-19T00:00:00+00:00"
  }
}
```

### Endpoint Admin

Endpoint:
- `GET /metrics`
- `POST /metrics/reset`
- `DELETE /metrics/reset`
- `POST /cache/prune`
- `DELETE /cache/prune`

Autorisasi:
- `X-Admin-Token: <token>` atau
- `Authorization: <token>` (raw token, tanpa prefix `Bearer`)

Jika token tidak dikonfigurasi: `503`  
Jika token salah/kosong: `403`

Contoh:

```bash
curl -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/metrics
```

```bash
curl -X POST -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/metrics/reset
```

```bash
curl -X DELETE -H "X-Admin-Token: token-rahasia" http://127.0.0.1:8000/cache/prune
```

## Ringkasan Status Code

- `200` sukses, atau error bisnis upstream yang masih valid
- `400` path/parameter tidak valid
- `403` admin token salah/kosong
- `404` route tidak ditemukan (JSON untuk non-HTML request)
- `405` method tidak diizinkan
- `500` error internal aplikasi
- `502` response upstream tidak valid/rusak
- `503` health tidak sehat atau admin token belum dikonfigurasi

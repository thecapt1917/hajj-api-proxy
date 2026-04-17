# Hajj API Proxy

Dokumentasi singkat project ini hanya mencakup endpoint yang tersedia dan cara penggunaannya.

## Cara Menjalankan

1. Siapkan file `.env` dengan minimal konfigurasi berikut:

```env
HAJJ_UPSTREAM_URL="https://haji.kemenag.go.id/haji-pintar/api/web/external/15/your-endpoint"
HAJJ_UPSTREAM_KEY="isi_dengan_x_key_asli"
HAJJ_ADMIN_TOKEN="isi_dengan_token_admin_kuat"
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
- `Authorization: Bearer <token>`

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

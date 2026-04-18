<?php

header("Content-Type: text/plain; charset=utf-8");

echo <<<TEXT
+======================================================================+
|                           HAJJ API PROXY                             |
|                    Cek Status Porsi Haji (Kemenag)                   |
+======================================================================+

FITUR YANG TERSEDIA SAAT INI
----------------------------
1) Cek Porsi Haji
   GET /check/{no_porsi}
   - no_porsi wajib 10 digit angka
   - method selain GET => 405 (Allow: GET)
   - contoh: /check/1203123456

2) Daftar Embarkasi Haji
   GET /embarkasi
   - ambil daftar area embarkasi haji dari upstream mobile
   - method selain GET => 405 (Allow: GET)
   - path turunan invalid => 400

3) Jadwal Keberangkatan Haji
   GET /jadwal/{embarkasi}/{kloter}
   - embarkasi wajib 3 huruf kapital, contoh SUB
   - kloter wajib 1-3 digit angka, contoh 100
   - method selain GET => 405 (Allow: GET)
   - path/parameter invalid => 400

4) Health Check
   GET /health
   - cek status service, cURL, konfigurasi upstream, dan probe upstream
   - status 200 jika sehat, 503 jika tidak sehat

5) Metrics (Admin)
   GET /metrics
   - lihat snapshot metrik request/cache/upstream
   - butuh token admin

6) Reset Metrics (Admin)
   POST /metrics/reset
   DELETE /metrics/reset
   - reset data metrik
   - butuh token admin

7) Prune Cache (Admin)
   POST /cache/prune
   DELETE /cache/prune
   - hapus cache yang sudah expired
   - butuh token admin

AUTH ADMIN
----------
Gunakan salah satu:
- Header: X-Admin-Token: <token>
- Header: Authorization: <token>

Konfigurasi env yang dipakai:
- HAJJ_UPSTREAM_URL
- HAJJ_UPSTREAM_KEY
- HAJJ_JADWAL_UPSTREAM_URL
- HAJJ_JADWAL_CACHE_TTL
- HAJJ_EMBARKASI_UPSTREAM_URL
- HAJJ_EMBARKASI_UPSTREAM_KEY
- HAJJ_ADMIN_TOKEN
- HAJJ_CACHE_ENABLED
- HAJJ_CACHE_TTL

CATATAN RESPONSE
----------------
- Default response API: application/json; charset=utf-8
- Endpoint root ini hanya halaman informasi (plain text)
TEXT;

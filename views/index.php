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

2) Health Check
   GET /health
   - cek status service, cURL, konfigurasi upstream, dan probe upstream
   - status 200 jika sehat, 503 jika tidak sehat

3) Metrics (Admin)
   GET /metrics
   - lihat snapshot metrik request/cache/upstream
   - butuh token admin

4) Reset Metrics (Admin)
   POST /metrics/reset
   DELETE /metrics/reset
   - reset data metrik
   - butuh token admin

5) Prune Cache (Admin)
   POST /cache/prune
   DELETE /cache/prune
   - hapus cache yang sudah expired
   - butuh token admin

AUTH ADMIN
----------
Gunakan salah satu:
- Header: X-Admin-Token: <token>
- Header: Authorization: Bearer <token>

Konfigurasi env yang dipakai:
- HAJJ_UPSTREAM_URL
- HAJJ_UPSTREAM_KEY
- HAJJ_ADMIN_TOKEN
- HAJJ_CACHE_ENABLED
- HAJJ_CACHE_TTL

CATATAN RESPONSE
----------------
- Default response API: application/json; charset=utf-8
- Endpoint root ini hanya halaman informasi (plain text)
TEXT;

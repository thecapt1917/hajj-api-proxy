<?php

final class CheckApiConfig
{
    private const DEFAULT_CACHE_ENABLED = true;
    private const DEFAULT_CACHE_TTL_SECONDS = 900;
    private const DEFAULT_EMBARKATION_CACHE_TTL_SECONDS = 86400;
    private const DEFAULT_SCHEDULE_CACHE_TTL_SECONDS = 3600;
    private const DEFAULT_CACHE_DIR = "storage/cache/check";
    private const DEFAULT_METRICS_FILE = "storage/metrics/check_metrics.json";
    private const DEFAULT_EMBARKATION_UPSTREAM_URL = "https://haji.kemenag.go.id/haji-pintar/api/mobile/detail/4";
    private const DEFAULT_SCHEDULE_UPSTREAM_URL = "https://haji.kemenag.go.id/haji-pintar/api/web/external/16/4df1d070-3748-11ea-838f-e170a6dffa79";

    public function getUpstreamUrl(): string
    {
        $url = trim((string) getenv("HAJJ_UPSTREAM_URL"));
        if ($url === "") {
            throw new RuntimeException("Konfigurasi HAJJ_UPSTREAM_URL belum diatur");
        }

        return $url;
    }

    public function getUpstreamKey(): string
    {
        $key = trim((string) getenv("HAJJ_UPSTREAM_KEY"));
        if ($key === "") {
            throw new RuntimeException("Konfigurasi HAJJ_UPSTREAM_KEY belum diatur");
        }

        return $key;
    }

    public function getEmbarkationUpstreamUrl(): string
    {
        $url = trim((string) getenv("HAJJ_EMBARKASI_UPSTREAM_URL"));
        if ($url === "") {
            return self::DEFAULT_EMBARKATION_UPSTREAM_URL;
        }

        return $url;
    }

    public function getEmbarkationUpstreamKey(): string
    {
        $key = trim((string) getenv("HAJJ_EMBARKASI_UPSTREAM_KEY"));
        if ($key === "") {
            throw new RuntimeException("Konfigurasi HAJJ_EMBARKASI_UPSTREAM_KEY belum diatur");
        }

        return $key;
    }

    public function getScheduleUpstreamUrl(): string
    {
        $url = trim((string) getenv("HAJJ_JADWAL_UPSTREAM_URL"));
        if ($url === "") {
            return self::DEFAULT_SCHEDULE_UPSTREAM_URL;
        }

        return $url;
    }

    public function isCacheEnabled(): bool
    {
        $raw = strtolower(trim((string) getenv("HAJJ_CACHE_ENABLED")));
        if ($raw === "") {
            return self::DEFAULT_CACHE_ENABLED;
        }

        return in_array($raw, ["1", "true", "yes", "on"], true);
    }

    public function getCacheTtlSeconds(): int
    {
        $raw = trim((string) getenv("HAJJ_CACHE_TTL"));
        if ($raw === "") {
            return self::DEFAULT_CACHE_TTL_SECONDS;
        }

        $ttl = (int) $raw;
        return $ttl > 0 ? $ttl : self::DEFAULT_CACHE_TTL_SECONDS;
    }

    public function getEmbarkationCacheTtlSeconds(): int
    {
        $raw = trim((string) getenv("HAJJ_EMBARKASI_CACHE_TTL"));
        if ($raw === "") {
            return self::DEFAULT_EMBARKATION_CACHE_TTL_SECONDS;
        }

        $ttl = (int) $raw;
        return $ttl > 0 ? $ttl : self::DEFAULT_EMBARKATION_CACHE_TTL_SECONDS;
    }

    public function getScheduleCacheTtlSeconds(): int
    {
        $raw = trim((string) getenv("HAJJ_JADWAL_CACHE_TTL"));
        if ($raw === "") {
            return self::DEFAULT_SCHEDULE_CACHE_TTL_SECONDS;
        }

        $ttl = (int) $raw;
        return $ttl > 0 ? $ttl : self::DEFAULT_SCHEDULE_CACHE_TTL_SECONDS;
    }

    public function getCacheDirectory(): string
    {
        $raw = trim((string) getenv("HAJJ_CACHE_DIR"));
        if ($raw === "") {
            $raw = self::DEFAULT_CACHE_DIR;
        }

        if ($this->isAbsolutePath($raw)) {
            return $raw;
        }

        return $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $raw);
    }

    public function getMetricsFilePath(): string
    {
        $raw = trim((string) getenv("HAJJ_METRICS_FILE"));
        if ($raw === "") {
            $raw = self::DEFAULT_METRICS_FILE;
        }

        if ($this->isAbsolutePath($raw)) {
            return $raw;
        }

        return $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $raw);
    }

    public function getAdminToken(): string
    {
        return trim((string) getenv("HAJJ_ADMIN_TOKEN"));
    }

    public function shouldVerifyTls(): bool
    {
        $raw = strtolower(trim((string) getenv("HAJJ_TLS_VERIFY")));
        if ($raw !== "") {
            return in_array($raw, ["1", "true", "yes", "on"], true);
        }

        return $this->getCurlCaInfoPath() !== "" || trim((string) ini_get("openssl.capath")) !== "";
    }

    public function getCurlCaInfoPath(): string
    {
        $envPath = trim((string) getenv("HAJJ_CURL_CAINFO"));
        if ($envPath !== "" && is_file($envPath)) {
            return $envPath;
        }

        $curlCaInfo = trim((string) ini_get("curl.cainfo"));
        if ($curlCaInfo !== "" && is_file($curlCaInfo)) {
            return $curlCaInfo;
        }

        $opensslCaFile = trim((string) ini_get("openssl.cafile"));
        if ($opensslCaFile !== "" && is_file($opensslCaFile)) {
            return $opensslCaFile;
        }

        return "";
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (preg_match("/^[a-zA-Z]:[\\\\\\/]/", $path) === 1) {
            return true;
        }

        if (str_starts_with($path, "\\\\") || str_starts_with($path, "/")) {
            return true;
        }

        return false;
    }
}

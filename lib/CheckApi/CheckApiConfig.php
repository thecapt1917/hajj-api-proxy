<?php

final class CheckApiConfig
{
    private const DEFAULT_CACHE_ENABLED = true;
    private const DEFAULT_CACHE_TTL_SECONDS = 900;
    private const DEFAULT_CACHE_DIR = "storage/cache/check";
    private const DEFAULT_METRICS_FILE = "storage/metrics/check_metrics.json";

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

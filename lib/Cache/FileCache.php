<?php

final class FileCache
{
    public function __construct(private readonly string $cacheDirectory)
    {
    }

    public function get(string $key): ?array
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return null;
        }

        try {
            $raw = file_get_contents($path);
            if (!is_string($raw) || $raw === "") {
                $this->delete($key);
                return null;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !array_key_exists("expires_at", $decoded) || !array_key_exists("payload", $decoded)) {
                $this->delete($key);
                return null;
            }

            $expiresAt = (int) $decoded["expires_at"];
            if ($expiresAt <= time()) {
                $this->delete($key);
                return null;
            }

            $payload = $decoded["payload"];
            return is_array($payload) ? $payload : null;
        } catch (Throwable $throwable) {
            $this->logCacheIssue("get", $key, $throwable->getMessage());
            return null;
        }
    }

    public function set(string $key, array $payload, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }

        if (!$this->ensureCacheDirectory()) {
            $this->logCacheIssue("set", $key, "cache directory tidak bisa dibuat");
            return;
        }

        $record = [
            "expires_at" => time() + $ttlSeconds,
            "payload" => $payload,
        ];

        $json = json_encode($record, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $this->logCacheIssue("set", $key, "gagal encode cache payload");
            return;
        }

        $path = $this->pathForKey($key);

        try {
            $writeResult = file_put_contents($path, $json, LOCK_EX);
            if ($writeResult === false) {
                $this->logCacheIssue("set", $key, "gagal menulis file cache");
            }
        } catch (Throwable $throwable) {
            $this->logCacheIssue("set", $key, $throwable->getMessage());
        }
    }

    public function delete(string $key): void
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return;
        }

        try {
            if (!@unlink($path)) {
                $this->logCacheIssue("delete", $key, "gagal menghapus file cache");
            }
        } catch (Throwable $throwable) {
            $this->logCacheIssue("delete", $key, $throwable->getMessage());
        }
    }

    public function pruneExpired(): array
    {
        $result = [
            "scanned" => 0,
            "deleted" => 0,
            "errors" => 0,
        ];

        if (!is_dir($this->cacheDirectory)) {
            return $result;
        }

        $pattern = rtrim($this->cacheDirectory, "\\/") . DIRECTORY_SEPARATOR . "*.json";
        $files = glob($pattern);
        if ($files === false) {
            $result["errors"]++;
            return $result;
        }

        foreach ($files as $file) {
            $result["scanned"]++;
            try {
                $raw = file_get_contents($file);
                if (!is_string($raw) || $raw === "") {
                    if (@unlink($file)) {
                        $result["deleted"]++;
                    } else {
                        $result["errors"]++;
                    }
                    continue;
                }

                $decoded = json_decode($raw, true);
                $expiresAt = is_array($decoded) ? (int) ($decoded["expires_at"] ?? 0) : 0;
                if ($expiresAt > 0 && $expiresAt > time()) {
                    continue;
                }

                if (@unlink($file)) {
                    $result["deleted"]++;
                } else {
                    $result["errors"]++;
                }
            } catch (Throwable $throwable) {
                $result["errors"]++;
                $this->logCacheIssue("prune", $file, $throwable->getMessage());
            }
        }

        return $result;
    }

    private function ensureCacheDirectory(): bool
    {
        if (is_dir($this->cacheDirectory)) {
            return true;
        }

        return @mkdir($this->cacheDirectory, 0775, true) || is_dir($this->cacheDirectory);
    }

    private function pathForKey(string $key): string
    {
        return rtrim($this->cacheDirectory, "\\/") . DIRECTORY_SEPARATOR . hash("sha256", $key) . ".json";
    }

    private function logCacheIssue(string $action, string $key, string $reason): void
    {
        error_log(sprintf("hajj_cache_issue action=%s key=%s reason=%s", $action, $key, $reason !== "" ? $reason : "-"));
    }
}

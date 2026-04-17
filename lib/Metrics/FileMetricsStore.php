<?php

final class FileMetricsStore
{
    public function __construct(private readonly string $filePath)
    {
    }

    public function increment(string $name, int $delta = 1): void
    {
        if ($delta === 0 || $name === "") {
            return;
        }

        $this->bulkIncrement([$name => $delta]);
    }

    public function bulkIncrement(array $deltas): void
    {
        $filtered = [];
        foreach ($deltas as $name => $delta) {
            if (!is_string($name) || $name === "") {
                continue;
            }
            $value = (int) $delta;
            if ($value === 0) {
                continue;
            }
            $filtered[$name] = ($filtered[$name] ?? 0) + $value;
        }

        if ($filtered === []) {
            return;
        }

        $this->mutate(function (array $current) use ($filtered): array {
            foreach ($filtered as $name => $delta) {
                $current[$name] = (int) ($current[$name] ?? 0) + $delta;
            }
            return $current;
        });
    }

    public function snapshot(): array
    {
        try {
            if (!is_file($this->filePath)) {
                return [];
            }

            $raw = file_get_contents($this->filePath);
            if (!is_string($raw) || $raw === "") {
                return [];
            }

            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $throwable) {
            error_log("hajj_metrics_issue action=snapshot reason=" . $throwable->getMessage());
            return [];
        }
    }

    public function reset(): void
    {
        $this->mutate(function (array $current): array {
            return [];
        });
    }

    private function mutate(callable $mutator): void
    {
        try {
            $dir = dirname($this->filePath);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                error_log("hajj_metrics_issue action=mutate reason=mkdir_failed");
                return;
            }

            $handle = fopen($this->filePath, "c+");
            if ($handle === false) {
                error_log("hajj_metrics_issue action=mutate reason=open_failed");
                return;
            }

            try {
                if (!flock($handle, LOCK_EX)) {
                    error_log("hajj_metrics_issue action=mutate reason=lock_failed");
                    return;
                }

                $contents = stream_get_contents($handle);
                if (!is_string($contents)) {
                    $contents = "";
                }
                $current = $contents !== "" ? json_decode($contents, true) : [];
                if (!is_array($current)) {
                    $current = [];
                }

                $next = $mutator($current);
                if (!is_array($next)) {
                    $next = $current;
                }

                rewind($handle);
                ftruncate($handle, 0);
                $json = json_encode($next, JSON_UNESCAPED_UNICODE);
                if (!is_string($json)) {
                    $json = "{}";
                }
                fwrite($handle, $json);
                fflush($handle);
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable $throwable) {
            error_log("hajj_metrics_issue action=mutate reason=" . $throwable->getMessage());
        }
    }
}

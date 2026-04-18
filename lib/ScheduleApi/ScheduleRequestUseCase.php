<?php

final class ScheduleRequestUseCase
{
    public function __construct(
        private readonly ScheduleUpstreamClient $upstreamClient,
        private readonly ScheduleResponseNormalizer $responseNormalizer,
        private readonly FileCache $fileCache,
        private readonly FileMetricsStore $metricsStore,
        private readonly bool $cacheEnabled,
        private readonly int $cacheTtlSeconds,
    ) {
    }

    public function execute(string $requestMethod, string $embarkasi, string $kloter): array
    {
        $metrics = ["requests_total" => 1];

        if ($requestMethod !== "GET") {
            return $this->errorResult($metrics, 405, "405", "Method tidak diizinkan", ["Allow" => "GET"]);
        }

        if (preg_match('/^[A-Z]{3}$/', $embarkasi) !== 1) {
            return $this->errorResult($metrics, 400, "400", "Embarkasi tidak valid. Gunakan 3 huruf kapital.");
        }

        if (preg_match('/^\d{1,3}$/', $kloter) !== 1) {
            return $this->errorResult($metrics, 400, "400", "Kloter tidak valid. Gunakan 1-3 digit angka.");
        }

        $cacheKey = "jadwal:" . $embarkasi . ":" . $kloter;
        $cacheHeader = $this->cacheEnabled ? "MISS" : "BYPASS";
        if ($this->cacheEnabled) {
            $cachedPayload = $this->fileCache->get($cacheKey);
            if (is_array($cachedPayload)) {
                $cachedPayload = $this->responseNormalizer->normalizePayloadShape($cachedPayload);
                $metrics["cache_hit"] = 1;
                return $this->finish($metrics, [
                    "status_code" => 200,
                    "payload" => $cachedPayload,
                    "headers" => ["X-Cache" => "HIT"],
                ]);
            }
            $metrics["cache_miss"] = 1;
        } else {
            $metrics["cache_bypass"] = 1;
        }

        $metrics["upstream_calls"] = 1;
        $upstreamStart = microtime(true);
        try {
            $rawBody = $this->upstreamClient->requestSchedule($embarkasi, $kloter);
        } catch (Throwable $throwable) {
            $this->recordUpstreamLatencyMetrics($upstreamStart);
            $metrics["upstream_errors"] = ($metrics["upstream_errors"] ?? 0) + 1;
            $this->logInternalError("execute", $throwable);
            return $this->errorResult($metrics, 500, "500", "Terjadi kesalahan internal", ["X-Cache" => $cacheHeader]);
        }
        $this->recordUpstreamLatencyMetrics($upstreamStart);

        if ($rawBody === null) {
            $metrics["upstream_errors"] = ($metrics["upstream_errors"] ?? 0) + 1;
            return $this->errorResult(
                $metrics,
                502,
                "502",
                "Response upstream tidak valid",
                ["X-Cache" => $cacheHeader]
            );
        }

        $normalized = $this->responseNormalizer->normalize($rawBody);
        $normalized = $this->responseNormalizer->normalizePayloadShape($normalized);
        $statusCode = ($normalized["code"] ?? "") === "502" ? 502 : 200;

        if (
            $this->cacheEnabled &&
            $statusCode === 200 &&
            ($normalized["success"] ?? false) === true &&
            ($normalized["code"] ?? "") === "00"
        ) {
            $this->fileCache->set($cacheKey, $normalized, $this->cacheTtlSeconds);
        }

        return $this->finish($metrics, [
            "status_code" => $statusCode,
            "payload" => $normalized,
            "headers" => ["X-Cache" => $cacheHeader],
        ]);
    }

    private function finish(array $metrics, array $result): array
    {
        $statusCode = (int) ($result["status_code"] ?? 500);
        $this->recordStatusBucketMetrics($metrics, $statusCode);
        $this->metricsStore->bulkIncrement($metrics);
        return $result;
    }

    private function errorResult(
        array $metrics,
        int $statusCode,
        string $code,
        string $message,
        array $headers = []
    ): array {
        return $this->finish($metrics, [
            "status_code" => $statusCode,
            "payload" => $this->errorPayload($code, $message),
            "headers" => $headers,
        ]);
    }

    private function recordStatusBucketMetrics(array &$metrics, int $statusCode): void
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            $metrics["responses_2xx"] = ($metrics["responses_2xx"] ?? 0) + 1;
            return;
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            $metrics["responses_4xx"] = ($metrics["responses_4xx"] ?? 0) + 1;
            return;
        }

        if ($statusCode >= 500 && $statusCode < 600) {
            $metrics["responses_5xx"] = ($metrics["responses_5xx"] ?? 0) + 1;
        }
    }

    private function recordUpstreamLatencyMetrics(float $startedAt): void
    {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->metricsStore->recordUpstreamLatency($durationMs);
    }

    private function errorPayload(string $code, string $message): array
    {
        return [
            "success" => false,
            "code" => $code,
            "message" => $message,
            "data" => null,
        ];
    }

    private function logInternalError(string $context, Throwable $throwable): void
    {
        $message = sprintf(
            "hajj_schedule_internal_error context=%s type=%s message=%s file=%s line=%d",
            $context,
            $throwable::class,
            $throwable->getMessage() !== "" ? $throwable->getMessage() : "-",
            $throwable->getFile(),
            $throwable->getLine()
        );
        error_log($message);
    }
}

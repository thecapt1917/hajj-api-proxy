<?php

final class CheckRequestUseCase
{
    public function __construct(
        private readonly UpstreamClient $upstreamClient,
        private readonly ResponseNormalizer $responseNormalizer,
        private readonly FileCache $fileCache,
        private readonly FileMetricsStore $metricsStore,
        private readonly bool $cacheEnabled,
        private readonly int $cacheTtlSeconds
    ) {
    }

    public function execute(string $requestMethod, string $noPorsi): array
    {
        $metrics = ["requests_total" => 1];

        if ($requestMethod !== "GET") {
            return $this->finish(
                $metrics,
                405,
                [
                    "success" => false,
                    "code" => "405",
                    "message" => "Method tidak diizinkan",
                    "data" => null,
                ],
                ["Allow" => "GET"]
            );
        }

        if (preg_match("/^\d{10}$/", $noPorsi) !== 1) {
            return $this->finish(
                $metrics,
                400,
                [
                    "success" => false,
                    "code" => "400",
                    "message" => "No porsi tidak valid. Gunakan 10 digit angka.",
                    "data" => null,
                ]
            );
        }

        $cacheKey = "check:" . $noPorsi;
        $cacheHeader = $this->cacheEnabled ? "MISS" : "BYPASS";
        if ($this->cacheEnabled) {
            $cachedPayload = $this->fileCache->get($cacheKey);
            if (is_array($cachedPayload)) {
                $metrics["cache_hit"] = 1;
                return $this->finish($metrics, 200, $cachedPayload, ["X-Cache" => "HIT"]);
            }
            $metrics["cache_miss"] = 1;
        } else {
            $metrics["cache_bypass"] = 1;
        }

        $metrics["upstream_calls"] = 1;
        $upstreamStart = microtime(true);

        try {
            $rawBody = $this->upstreamClient->requestCheck($noPorsi);
        } catch (Throwable $throwable) {
            $this->recordUpstreamLatencyMetrics($metrics, $upstreamStart);
            $metrics["upstream_errors"] = ($metrics["upstream_errors"] ?? 0) + 1;
            $this->logInternalError("execute", $throwable);

            return $this->finish(
                $metrics,
                500,
                [
                    "success" => false,
                    "code" => "500",
                    "message" => "Terjadi kesalahan internal",
                    "data" => null,
                ],
                ["X-Cache" => $cacheHeader]
            );
        }

        $this->recordUpstreamLatencyMetrics($metrics, $upstreamStart);

        if ($rawBody === null) {
            $metrics["upstream_errors"] = ($metrics["upstream_errors"] ?? 0) + 1;
            return $this->finish(
                $metrics,
                502,
                [
                    "success" => false,
                    "code" => "502",
                    "message" => "Response upstream tidak valid",
                    "data" => null,
                ],
                ["X-Cache" => $cacheHeader]
            );
        }

        $normalized = $this->responseNormalizer->normalize($rawBody);
        $statusCode = ($normalized["code"] ?? "") === "502" ? 502 : 200;

        if (
            $this->cacheEnabled &&
            $statusCode === 200 &&
            ($normalized["success"] ?? false) === true &&
            ($normalized["code"] ?? "") === "00"
        ) {
            $this->fileCache->set($cacheKey, $normalized, $this->cacheTtlSeconds);
        }

        return $this->finish($metrics, $statusCode, $normalized, ["X-Cache" => $cacheHeader]);
    }

    private function finish(array $metrics, int $statusCode, array $payload, array $headers = []): array
    {
        $this->recordStatusBucketMetrics($metrics, $statusCode);
        $this->metricsStore->bulkIncrement($metrics);

        return [
            "status_code" => $statusCode,
            "payload" => $payload,
            "headers" => $headers,
        ];
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

    private function recordUpstreamLatencyMetrics(array &$metrics, float $startedAt): void
    {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $metrics["upstream_latency_sum_ms"] = ($metrics["upstream_latency_sum_ms"] ?? 0) + $durationMs;
        $metrics["upstream_latency_count"] = ($metrics["upstream_latency_count"] ?? 0) + 1;
    }

    private function logInternalError(string $context, Throwable $throwable): void
    {
        $message = sprintf(
            "hajj_internal_error context=%s type=%s message=%s file=%s line=%d",
            $context,
            $throwable::class,
            $throwable->getMessage() !== "" ? $throwable->getMessage() : "-",
            $throwable->getFile(),
            $throwable->getLine()
        );
        error_log($message);
    }
}

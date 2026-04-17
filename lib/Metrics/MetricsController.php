<?php

final class MetricsController
{
    public function __construct(
        private readonly JsonResponder $jsonResponder,
        private readonly FileMetricsStore $metricsStore,
        private readonly AdminAccessGuard $adminAccessGuard
    ) {
    }

    public function handle(): void
    {
        if (($this->requestMethod()) !== "GET") {
            $this->jsonResponder->send(
                405,
                $this->jsonResponder->errorPayload("405", "Method tidak diizinkan"),
                ["Allow" => "GET"]
            );
        }

        $auth = $this->adminAccessGuard->authorize();
        if (($auth["ok"] ?? false) !== true) {
            $statusCode = (int) ($auth["status_code"] ?? 403);
            $payload = $auth["payload"] ?? $this->jsonResponder->errorPayload("403", "Akses ditolak");
            if (!is_array($payload)) {
                $payload = $this->jsonResponder->errorPayload("403", "Akses ditolak");
            }
            $this->jsonResponder->send($statusCode, $payload);
        }

        $snapshot = $this->metricsStore->snapshot();
        $latencyCount = (int) ($snapshot["upstream_latency_count"] ?? 0);
        $latencySumMs = (int) ($snapshot["upstream_latency_sum_ms"] ?? 0);
        $avgLatencyMs = $latencyCount > 0 ? round($latencySumMs / $latencyCount, 2) : 0;

        $payload = [
            "success" => true,
            "code" => "00",
            "message" => "Berhasil",
            "data" => [
                "requests_total" => (int) ($snapshot["requests_total"] ?? 0),
                "cache_hit" => (int) ($snapshot["cache_hit"] ?? 0),
                "cache_miss" => (int) ($snapshot["cache_miss"] ?? 0),
                "cache_bypass" => (int) ($snapshot["cache_bypass"] ?? 0),
                "upstream_calls" => (int) ($snapshot["upstream_calls"] ?? 0),
                "upstream_errors" => (int) ($snapshot["upstream_errors"] ?? 0),
                "responses_2xx" => (int) ($snapshot["responses_2xx"] ?? 0),
                "responses_4xx" => (int) ($snapshot["responses_4xx"] ?? 0),
                "responses_5xx" => (int) ($snapshot["responses_5xx"] ?? 0),
                "upstream_avg_latency_ms" => $avgLatencyMs,
                "generated_at_utc" => gmdate("c"),
            ],
        ];

        $this->jsonResponder->send(200, $payload);
    }

    public function handleReset(): void
    {
        $method = $this->requestMethod();
        if ($method !== "POST" && $method !== "DELETE") {
            $this->jsonResponder->send(
                405,
                $this->jsonResponder->errorPayload("405", "Method tidak diizinkan"),
                ["Allow" => "POST, DELETE"]
            );
        }

        $auth = $this->adminAccessGuard->authorize();
        if (($auth["ok"] ?? false) !== true) {
            $statusCode = (int) ($auth["status_code"] ?? 403);
            $payload = $auth["payload"] ?? $this->jsonResponder->errorPayload("403", "Akses ditolak");
            if (!is_array($payload)) {
                $payload = $this->jsonResponder->errorPayload("403", "Akses ditolak");
            }
            $this->jsonResponder->send($statusCode, $payload);
        }

        $this->metricsStore->reset();
        $payload = [
            "success" => true,
            "code" => "00",
            "message" => "Metrics berhasil direset",
            "data" => [
                "reset_at_utc" => gmdate("c"),
            ],
        ];
        $this->jsonResponder->send(200, $payload);
    }

    private function requestMethod(): string
    {
        return (string) ($_SERVER["REQUEST_METHOD"] ?? "");
    }
}

<?php

final class HealthController
{
    public function __construct(
        private readonly JsonResponder $jsonResponder,
        private readonly CheckApiConfig $config
    )
    {
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

        $upstreamUrl = trim((string) getenv("HAJJ_UPSTREAM_URL"));
        $upstreamKey = trim((string) getenv("HAJJ_UPSTREAM_KEY"));
        $hasCurl = function_exists("curl_init");
        $hasUpstreamConfig = ($upstreamUrl !== "" && $upstreamKey !== "");

        $upstreamReachable = false;
        $upstreamHttpCode = 0;
        if ($hasCurl && $hasUpstreamConfig) {
            [$upstreamReachable, $upstreamHttpCode] = $this->probeUpstream($upstreamUrl, $upstreamKey);
        }

        $ok = $hasCurl && $hasUpstreamConfig && $upstreamReachable;

        $payload = [
            "success" => $ok,
            "code" => $ok ? "00" : "503",
            "message" => $ok ? "Service sehat" : "Service tidak sehat",
            "data" => [
                "app" => "ok",
                "php_version" => PHP_VERSION,
                "curl_available" => $hasCurl,
                "upstream_configured" => $hasUpstreamConfig,
                "upstream_reachable" => $upstreamReachable,
                "upstream_http_code" => $upstreamHttpCode,
                "timestamp_utc" => gmdate("c"),
            ],
        ];

        $this->jsonResponder->send($ok ? 200 : 503, $payload);
    }

    private function probeUpstream(string $upstreamUrl, string $upstreamKey): array
    {
        $curl = curl_init($upstreamUrl);
        if ($curl === false) {
            return [false, 0];
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_NOBODY => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "x-key: " . $upstreamKey,
            ],
        ]);
        $this->applyTlsOptions($curl);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $hasError = $response === false;
        curl_close($curl);

        if ($hasError) {
            return [false, 0];
        }

        return [$httpCode > 0, $httpCode];
    }

    private function applyTlsOptions(CurlHandle $curl): void
    {
        if (!$this->config->shouldVerifyTls()) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            return;
        }

        $caInfo = $this->config->getCurlCaInfoPath();
        if ($caInfo !== "") {
            curl_setopt($curl, CURLOPT_CAINFO, $caInfo);
        }
    }

    private function requestMethod(): string
    {
        return (string) ($_SERVER["REQUEST_METHOD"] ?? "");
    }
}

<?php

final class UpstreamClient
{
    public function __construct(private readonly CheckApiConfig $config)
    {
    }

    public function requestCheck(string $noPorsi): ?string
    {
        $upstreamUrl = $this->config->getUpstreamUrl();
        $upstreamKey = $this->config->getUpstreamKey();

        if ($upstreamUrl === "" || $upstreamKey === "") {
            throw new RuntimeException("Konfigurasi upstream belum lengkap");
        }

        if (!function_exists("curl_init")) {
            throw new RuntimeException("Ekstensi cURL tidak tersedia");
        }

        $requestBody = json_encode(["no_porsi" => $noPorsi], JSON_UNESCAPED_UNICODE);
        if (!is_string($requestBody)) {
            return null;
        }

        $curl = curl_init($upstreamUrl);
        if ($curl === false) {
            return null;
        }

        $startTime = microtime(true);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "content-type: application/json; charset=utf-8",
                "x-key: " . $upstreamKey,
            ],
            CURLOPT_POSTFIELDS => $requestBody,
        ]);

        $responseBody = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        curl_close($curl);

        if ($responseBody === false) {
            $this->logUpstreamIssue($httpCode, $curlErrno, $curlError, $durationMs);
            return null;
        }

        if ($httpCode >= 500 || $httpCode === 0) {
            $this->logUpstreamIssue($httpCode, $curlErrno, $curlError, $durationMs);
            return null;
        }

        if ($httpCode >= 400) {
            $this->logUpstreamIssue($httpCode, $curlErrno, $curlError, $durationMs);
        }

        return is_string($responseBody) ? $responseBody : null;
    }

    private function logUpstreamIssue(int $httpCode, int $curlErrno, string $curlError, int $durationMs): void
    {
        $message = sprintf(
            "hajj_upstream_issue http_code=%d curl_errno=%d duration_ms=%d curl_error=%s",
            $httpCode,
            $curlErrno,
            $durationMs,
            $curlError !== "" ? $curlError : "-"
        );
        error_log($message);
    }
}

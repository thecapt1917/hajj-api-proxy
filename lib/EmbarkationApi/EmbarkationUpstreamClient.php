<?php

class EmbarkationUpstreamClient
{
    public function __construct(private readonly CheckApiConfig $config)
    {
    }

    public function requestList(): ?string
    {
        $upstreamUrl = $this->config->getEmbarkationUpstreamUrl();
        $upstreamKey = $this->config->getEmbarkationUpstreamKey();

        if (!function_exists("curl_init")) {
            throw new RuntimeException("Ekstensi cURL tidak tersedia");
        }

        $curl = curl_init($upstreamUrl);
        if ($curl === false) {
            return null;
        }

        $startTime = microtime(true);
        curl_setopt_array($curl, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => "",
            CURLOPT_HTTPHEADER => [
                "User-Agent: Dart/2.17 (dart:io)",
                "Accept: application/json",
                "Accept-Encoding: gzip",
                "content-type: application/json",
                "x-key: " . $upstreamKey,
            ],
        ]);
        $this->applyTlsOptions($curl);

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
            "hajj_embarkation_upstream_issue http_code=%d curl_errno=%d duration_ms=%d curl_error=%s",
            $httpCode,
            $curlErrno,
            $durationMs,
            $curlError !== "" ? $curlError : "-"
        );
        error_log($message);
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
}

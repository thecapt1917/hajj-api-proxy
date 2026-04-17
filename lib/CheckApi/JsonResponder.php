<?php

final class JsonResponder
{
    public function send(int $statusCode, array $payload, array $extraHeaders = []): void
    {
        $this->emit($statusCode, $payload, $extraHeaders, true);
    }

    public function emit(int $statusCode, array $payload, array $extraHeaders = [], bool $terminate = false): void
    {
        http_response_code($statusCode);
        foreach ($this->buildHeaders($extraHeaders) as $headerLine) {
            header($headerLine);
        }

        echo $this->encodePayload($payload);
        if ($terminate) {
            exit();
        }
    }

    public function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : "{\"success\":false,\"code\":\"500\",\"message\":\"Gagal encode JSON\",\"data\":null}";
    }

    public function buildHeaders(array $extraHeaders = []): array
    {
        $headers = [
            "Content-Type: application/json; charset=utf-8",
            "Cache-Control: no-store",
            "X-Content-Type-Options: nosniff",
        ];

        foreach ($extraHeaders as $name => $value) {
            $headers[] = $name . ": " . $value;
        }

        return $headers;
    }

    public function errorPayload(string $code, string $message): array
    {
        return [
            "success" => false,
            "code" => $code,
            "message" => $message,
            "data" => null,
        ];
    }
}

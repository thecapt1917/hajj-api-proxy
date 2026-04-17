<?php

final class CacheMaintenanceController
{
    public function __construct(
        private readonly JsonResponder $jsonResponder,
        private readonly FileCache $fileCache,
        private readonly AdminAccessGuard $adminAccessGuard
    ) {
    }

    public function handlePrune(): void
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

        $result = $this->fileCache->pruneExpired();
        $payload = [
            "success" => true,
            "code" => "00",
            "message" => "Berhasil",
            "data" => [
                "scanned" => (int) ($result["scanned"] ?? 0),
                "deleted" => (int) ($result["deleted"] ?? 0),
                "errors" => (int) ($result["errors"] ?? 0),
                "executed_at_utc" => gmdate("c"),
            ],
        ];

        $this->jsonResponder->send(200, $payload);
    }

    private function requestMethod(): string
    {
        return (string) ($_SERVER["REQUEST_METHOD"] ?? "");
    }
}

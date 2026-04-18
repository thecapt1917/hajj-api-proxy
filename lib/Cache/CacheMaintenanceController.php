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
        AdminRequestGuard::ensureAllowedMethod($this->jsonResponder, $this->requestMethod(), ["POST", "DELETE"]);
        AdminRequestGuard::ensureAuthorized($this->jsonResponder, $this->adminAccessGuard);

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

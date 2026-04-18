<?php

final class AdminAccessGuard
{
    public function __construct(private readonly CheckApiConfig $config)
    {
    }

    public function authorize(): array
    {
        $expectedToken = trim((string) $this->config->getAdminToken());
        if ($expectedToken === "") {
            return [
                "ok" => false,
                "status_code" => 503,
                "payload" => [
                    "success" => false,
                    "code" => "503",
                    "message" => "Admin token belum dikonfigurasi",
                    "data" => null,
                ],
            ];
        }

        $providedToken = $this->extractProvidedToken();
        if ($providedToken === "" || !hash_equals($expectedToken, $providedToken)) {
            return [
                "ok" => false,
                "status_code" => 403,
                "payload" => [
                    "success" => false,
                    "code" => "403",
                    "message" => "Akses ditolak",
                    "data" => null,
                ],
            ];
        }

        return ["ok" => true];
    }

    private function extractProvidedToken(): string
    {
        $tokenFromHeader = trim((string) ($_SERVER["HTTP_X_ADMIN_TOKEN"] ?? ""));
        if ($tokenFromHeader !== "") {
            return $tokenFromHeader;
        }

        $authorization = trim((string) ($_SERVER["HTTP_AUTHORIZATION"] ?? ""));
        // Accept raw token in Authorization header (without Bearer prefix).
        if ($authorization !== "") {
            return $authorization;
        }

        return "";
    }
}

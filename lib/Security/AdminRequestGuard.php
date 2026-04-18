<?php

final class AdminRequestGuard
{
    public static function ensureAllowedMethod(
        JsonResponder $jsonResponder,
        string $requestMethod,
        array $allowedMethods
    ): void {
        if (in_array($requestMethod, $allowedMethods, true)) {
            return;
        }

        $allowHeader = implode(", ", $allowedMethods);
        $jsonResponder->send(
            405,
            $jsonResponder->errorPayload("405", "Method tidak diizinkan"),
            ["Allow" => $allowHeader]
        );
    }

    public static function ensureAuthorized(JsonResponder $jsonResponder, AdminAccessGuard $adminAccessGuard): void
    {
        $auth = $adminAccessGuard->authorize();
        if (($auth["ok"] ?? false) === true) {
            return;
        }

        $statusCode = (int) ($auth["status_code"] ?? 403);
        $payload = $auth["payload"] ?? $jsonResponder->errorPayload("403", "Akses ditolak");
        if (!is_array($payload)) {
            $payload = $jsonResponder->errorPayload("403", "Akses ditolak");
        }

        $jsonResponder->send($statusCode, $payload);
    }
}

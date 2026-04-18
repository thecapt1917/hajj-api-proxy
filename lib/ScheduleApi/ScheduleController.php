<?php

final class ScheduleController
{
    public function __construct(
        private readonly ScheduleRequestUseCase $scheduleRequestUseCase,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    public function handleBadPath(): void
    {
        if (RequestContext::method() !== "GET") {
            $this->jsonResponder->send(
                405,
                $this->jsonResponder->errorPayload("405", "Method tidak diizinkan"),
                ["Allow" => "GET"]
            );
        }

        $this->jsonResponder->send(400, $this->jsonResponder->errorPayload("400", "Format path tidak valid"));
    }

    public function handleSchedule(string $embarkasi, string $kloter): void
    {
        $result = $this->scheduleRequestUseCase->execute(
            (string) ($_SERVER["REQUEST_METHOD"] ?? ""),
            $embarkasi,
            $kloter
        );

        $statusCode = (int) ($result["status_code"] ?? 500);
        $payload = $result["payload"] ?? $this->jsonResponder->errorPayload("500", "Terjadi kesalahan internal");
        $headers = $result["headers"] ?? [];

        if (!is_array($payload)) {
            $payload = $this->jsonResponder->errorPayload("500", "Terjadi kesalahan internal");
            $statusCode = 500;
        }
        if (!is_array($headers)) {
            $headers = [];
        }

        $this->jsonResponder->send($statusCode, $payload, $headers);
    }
}

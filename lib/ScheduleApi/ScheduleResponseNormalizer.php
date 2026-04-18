<?php

final class ScheduleResponseNormalizer
{
    private const INTEGER_CAST_FIELDS = [
        "tahun",
        "kloter",
        "no_maktab",
        "no_rumah",
        "no_subdaker",
    ];

    public function normalizePayloadShape(array $payload): array
    {
        $data = $payload["data"] ?? null;
        if (is_array($data) && $this->isAssoc($data)) {
            $data = [$data];
        }

        if (!is_array($data)) {
            $payload["data"] = [];
            return $payload;
        }

        $normalized = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = $this->normalizeScheduleRow($row);
        }

        $payload["data"] = $normalized;
        return $payload;
    }

    public function normalize(string $rawBody): array
    {
        $errorCandidate = null;
        foreach ($this->iterateJsonObjects($rawBody) as $item) {
            if (($item["data"]["ResponseCode"] ?? null) === "00") {
                $payload = $item["data"];
                $rows = [];
                if (isset($payload["Data"]) && is_array($payload["Data"])) {
                    foreach ($payload["Data"] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $rows[] = $this->normalizeScheduleRow($row);
                    }
                }

                return [
                    "success" => true,
                    "code" => (string) ($payload["ResponseCode"] ?? "00"),
                    "message" => (string) ($payload["ResposeMessage"] ?? $payload["ResponseMessage"] ?? "Berhasil"),
                    "data" => $rows,
                ];
            }

            if ($errorCandidate === null && array_key_exists("RC", $item)) {
                $message = $item["message"] ?? "Error Koneksi atau Data";
                if (!is_string($message) || trim($message) === "") {
                    $message = "Error Koneksi atau Data";
                }

                $errorCandidate = [
                    "success" => false,
                    "code" => (string) ($item["RC"] ?? "99"),
                    "message" => $message,
                    "data" => null,
                ];
            }
        }

        if (is_array($errorCandidate)) {
            return $errorCandidate;
        }

        return [
            "success" => false,
            "code" => "502",
            "message" => "Response upstream tidak valid",
            "data" => null,
        ];
    }

    private function normalizeScheduleRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
                $row[$key] = str_replace(" ", "T", $value);
                continue;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $row[$key] = $value;
                continue;
            }

            if (in_array($key, self::INTEGER_CAST_FIELDS, true) && ctype_digit($value)) {
                $row[$key] = (int) $value;
            }
        }

        $this->normalizeAtFields($row);

        return $row;
    }

    private function normalizeAtFields(array &$row): void
    {
        $fieldMap = [
            "masuk_asrama_at" => ["jam_masuk", "tgl_masuk_asrama"],
            "berangkat_at" => ["berangkat_jam", "berangkat_tgl"],
            "tiba_at" => ["tiba_jam", "tiba_tgl"],
            "pulang_at" => ["pulang_jam", "pulang_tgl"],
            "pulang_tiba_at" => ["pulang_tiba_jam", "pulang_tiba_tgl"],
        ];

        foreach ($fieldMap as $targetKey => $sourceKeys) {
            $resolved = $this->resolveAtValue($row, $sourceKeys);
            if ($resolved !== null) {
                $row[$targetKey] = $resolved;
            }
        }

        foreach ($fieldMap as $sourceKeys) {
            foreach ($sourceKeys as $sourceKey) {
                unset($row[$sourceKey]);
            }
        }
    }

    private function resolveAtValue(array $row, array $sourceKeys): ?string
    {
        foreach ($sourceKeys as $sourceKey) {
            $value = $row[$sourceKey] ?? null;
            if (!is_string($value) || $value === "") {
                continue;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value) === 1) {
                return $value;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return $value . "T00:00:00";
            }
        }

        return null;
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function iterateJsonObjects(string $text): Generator
    {
        $start = -1;
        $depth = 0;
        $inString = false;
        $escaped = false;

        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === "\\") {
                    $escaped = true;
                } elseif ($char === "\"") {
                    $inString = false;
                }
                continue;
            }

            if ($char === "\"") {
                $inString = true;
                continue;
            }

            if ($char === "{") {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
                continue;
            }

            if ($char !== "}") {
                continue;
            }

            $depth--;
            if ($depth !== 0 || $start === -1) {
                continue;
            }

            $candidate = substr($text, $start, ($i - $start) + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                yield $decoded;
            }
            $start = -1;
        }
    }
}

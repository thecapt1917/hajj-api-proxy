<?php

final class EmbarkationResponseNormalizer
{
    public function normalizePayloadShape(array $payload): array
    {
        $data = $payload["data"] ?? null;
        if (!is_array($data)) {
            return $payload;
        }

        $normalized = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = $this->normalizeRow($row);
        }

        $payload["data"] = $normalized;
        return $payload;
    }

    public function normalize(string $rawBody): array
    {
        foreach ($this->iterateJsonObjects($rawBody) as $item) {
            if (!array_key_exists("RC", $item)) {
                continue;
            }

            $code = (string) ($item["RC"] ?? "99");
            $message = $item["message"] ?? ($code === "00" ? "Berhasil" : "Error Koneksi atau Data");
            if (!is_string($message) || trim($message) === "") {
                $message = $code === "00" ? "Berhasil" : "Error Koneksi atau Data";
            }

            if ($code === "00") {
                $rows = $item["rows"] ?? [];
                return [
                    "success" => true,
                    "code" => "00",
                    "message" => $message,
                    "data" => is_array($rows) ? $this->normalizeRows($rows) : [],
                ];
            }

            return [
                "success" => false,
                "code" => $code,
                "message" => $message,
                "data" => null,
            ];
        }

        return [
            "success" => false,
            "code" => "502",
            "message" => "Response upstream tidak valid",
            "data" => null,
        ];
    }

    private function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized[] = $this->normalizeRow($row);
        }

        return $normalized;
    }

    private function normalizeRow(array $row): array
    {
        $row["is_aktif"] = (($row["is_aktif"] ?? null) === true || ($row["is_aktif"] ?? null) === "Y");
        return $row;
    }

    private function iterateJsonObjects(string $text): Generator
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            yield $decoded;
            return;
        }

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

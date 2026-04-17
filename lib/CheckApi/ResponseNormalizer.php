<?php

final class ResponseNormalizer
{
    public function normalize(string $rawBody): array
    {
        $errorCandidate = null;
        foreach ($this->iterateJsonObjects($rawBody) as $item) {
            if (($item["data"]["ResponseCode"] ?? null) === "00") {
                $payload = $item["data"];
                $row = null;
                if (isset($payload["Data"]) && is_array($payload["Data"])) {
                    $row = $payload["Data"][0] ?? null;
                }

                return [
                    "success" => true,
                    "code" => (string) ($payload["ResponseCode"] ?? "00"),
                    "message" => (string) ($payload["ResposeMessage"] ?? $payload["ResponseMessage"] ?? "Berhasil"),
                    "data" => [
                        "no_porsi" => is_array($row) ? ($row["kd_porsi"] ?? null) : null,
                        "nama" => is_array($row) ? ($row["nama"] ?? null) : null,
                        "kab_kode" => is_array($row) ? ($row["kd_kab"] ?? null) : null,
                        "kabupaten" => is_array($row) ? ($row["kabupaten"] ?? null) : null,
                        "prov_kode" => is_array($row) ? ($row["kd_prop"] ?? null) : null,
                        "provinsi" => is_array($row) ? ($row["propinsi"] ?? null) : null,
                        "posisi_porsi" => is_array($row) ? ($row["posisiporsi"] ?? null) : null,
                        "kuota_prov" => is_array($row) ? ($row["kuotapropinsi"] ?? null) : null,
                        "estimasi_masehi" => is_array($row) ? ($row["berangkatmasehi"] ?? null) : null,
                        "estimasi_hijriah" => is_array($row) ? ($row["berangkathijriah"] ?? null) : null,
                        "status_bayar" => is_array($row) ? ($row["status_bayar"] ?? null) : null,
                    ],
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

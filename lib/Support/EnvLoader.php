<?php

final class EnvLoader
{
    private static array $loadedFiles = [];

    public function load(string $filePath): void
    {
        $realPath = realpath($filePath);
        if ($realPath === false || !is_file($realPath)) {
            return;
        }

        if (isset(self::$loadedFiles[$realPath])) {
            return;
        }

        $lines = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === "" || str_starts_with($line, "#")) {
                continue;
            }

            if (str_starts_with($line, "export ")) {
                $line = trim(substr($line, 7));
            }

            $separatorIndex = strpos($line, "=");
            if ($separatorIndex === false) {
                continue;
            }

            $name = trim(substr($line, 0, $separatorIndex));
            if ($name === "") {
                continue;
            }

            $value = trim(substr($line, $separatorIndex + 1));
            $value = $this->normalizeValue($value);

            if (getenv($name) !== false) {
                continue;
            }

            putenv($name . "=" . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        self::$loadedFiles[$realPath] = true;
    }

    private function normalizeValue(string $value): string
    {
        if ($value === "") {
            return "";
        }

        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === "\"" && $last === "\"") || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return $value;
    }
}

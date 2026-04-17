<?php

final class RequestContext
{
    public static function method(): string
    {
        return (string) ($_SERVER["REQUEST_METHOD"] ?? "");
    }

    public static function path(): string
    {
        $requestUri = $_SERVER["REQUEST_URI"] ?? "/";
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === "") {
            return "/";
        }

        if ($path !== "/") {
            $path = rtrim($path, "/");
        }

        return $path === "" ? "/" : $path;
    }

    public static function startsWithPath(string $prefix): bool
    {
        if ($prefix === "") {
            return false;
        }

        return preg_match("#^" . preg_quote($prefix, "#") . "(?:/|$)#", self::path()) === 1;
    }

    public static function wantsHtml(): bool
    {
        $accept = strtolower((string) ($_SERVER["HTTP_ACCEPT"] ?? ""));
        if ($accept === "") {
            return false;
        }

        return str_contains($accept, "text/html");
    }
}

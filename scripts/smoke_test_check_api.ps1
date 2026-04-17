$ErrorActionPreference = "Stop"

$workspace = "D:\laragon\www\hajj"
$mockDir = Join-Path $env:TEMP "hajj_mock_upstream_smoke"
New-Item -ItemType Directory -Path $mockDir -Force | Out-Null

$mockFile = Join-Path $mockDir "index.php"
$mockContent = @'
<?php
$raw = file_get_contents("php://input") ?: "";
$payload = json_decode($raw, true);
$noPorsi = is_array($payload) ? ($payload["no_porsi"] ?? "") : "";
header("Content-Type: text/plain");

if ($noPorsi === "9999999999") {
    echo json_encode(["RC" => "14", "message" => "Data tidak ditemukan"], JSON_UNESCAPED_UNICODE);
    return;
}

if ($noPorsi === "8888888888") {
    echo "broken-upstream-response";
    return;
}

echo "noise-before ";
echo json_encode([
    "data" => [
        "ResponseCode" => "00",
        "ResposeMessage" => "Berhasil",
        "Data" => [[
            "kd_porsi" => $noPorsi,
            "nama" => "Budi"
        ]]
    ]
], JSON_UNESCAPED_UNICODE);
echo " noise-after";
'@
Set-Content -Path $mockFile -Value $mockContent -Encoding UTF8

function Parse-Response {
    param(
        [string] $Method,
        [string] $Uri
    )

    $headerText = curl.exe -s -D - -o NUL -X $Method $Uri
    if ($LASTEXITCODE -ne 0) {
        throw "curl gagal untuk $Method $Uri"
    }

    $lines = @($headerText -split "`r?`n" | Where-Object { $_ -ne "" })
    if ($lines.Count -lt 1) {
        throw "Header kosong untuk $Method $Uri"
    }

    $status = 0
    if ($lines[0] -match "HTTP/\d+\.\d+\s+(\d+)") {
        $status = [int]$Matches[1]
    } else {
        throw "Gagal parse status line: $($lines[0])"
    }

    $headers = @{}
    for ($i = 1; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match "^\s*([^:]+):\s*(.*)$") {
            $headers[$Matches[1].Trim().ToLowerInvariant()] = $Matches[2].Trim()
        }
    }

    return [PSCustomObject]@{
        Status = $status
        Headers = $headers
    }
}

function Assert-Equal {
    param(
        [string] $Name,
        [object] $Actual,
        [object] $Expected
    )

    if ($Actual -ne $Expected) {
        Write-Output ("[FAIL] {0} expected={1} actual={2}" -f $Name, $Expected, $Actual)
        return $false
    }

    Write-Output ("[PASS] {0} value={1}" -f $Name, $Actual)
    return $true
}

function Assert-OneOf {
    param(
        [string] $Name,
        [object] $Actual,
        [object[]] $ExpectedValues
    )

    if ($ExpectedValues -notcontains $Actual) {
        Write-Output ("[FAIL] {0} expected one of [{1}] actual={2}" -f $Name, ($ExpectedValues -join ", "), $Actual)
        return $false
    }

    Write-Output ("[PASS] {0} value={1}" -f $Name, $Actual)
    return $true
}

function Run-Scenario {
    param(
        [string] $CacheDir,
        [int] $CacheTtl,
        [scriptblock] $Runner
    )

    if (Test-Path -LiteralPath $CacheDir) {
        Remove-Item -LiteralPath $CacheDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $CacheDir -Force | Out-Null

    $env:HAJJ_UPSTREAM_URL = "http://127.0.0.1:18111/"
    $env:HAJJ_UPSTREAM_KEY = "test-secret"
    $env:HAJJ_CACHE_ENABLED = "true"
    $env:HAJJ_CACHE_TTL = [string]$CacheTtl
    $env:HAJJ_CACHE_DIR = $CacheDir

    $upstreamProc = $null
    $appProc = $null
    try {
        $upstreamProc = Start-Process -FilePath php -ArgumentList "-S", "127.0.0.1:18111", "-t", $mockDir -PassThru -WindowStyle Hidden
        $appProc = Start-Process -FilePath php -ArgumentList "-S", "127.0.0.1:18110", (Join-Path $workspace "routes.php") -PassThru -WindowStyle Hidden
        Start-Sleep -Seconds 1
        & $Runner
    }
    finally {
        if ($appProc -and !$appProc.HasExited) { Stop-Process -Id $appProc.Id -Force }
        if ($upstreamProc -and !$upstreamProc.HasExited) { Stop-Process -Id $upstreamProc.Id -Force }
    }
}

$failed = $false

Run-Scenario -CacheDir (Join-Path $env:TEMP "hajj_cache_smoke_a") -CacheTtl 900 -Runner {
    $r1 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/1234567890"
    if (!(Assert-Equal -Name "a.valid.status.first" -Actual $r1.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.valid.cache.first" -Actual $r1.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $r2 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/1234567890"
    if (!(Assert-Equal -Name "a.valid.status.second" -Actual $r2.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.valid.cache.second" -Actual $r2.Headers["x-cache"] -Expected "HIT")) { $script:failed = $true }

    $r3 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/1234567891"
    if (!(Assert-Equal -Name "a.different.status" -Actual $r3.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.different.cache" -Actual $r3.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $r4 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/9999999999"
    if (!(Assert-Equal -Name "a.rc.status.first" -Actual $r4.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.rc.cache.first" -Actual $r4.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $r5 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/9999999999"
    if (!(Assert-Equal -Name "a.rc.status.second" -Actual $r5.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.rc.cache.second" -Actual $r5.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $r6 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/8888888888"
    if (!(Assert-Equal -Name "a.invalid.status.first" -Actual $r6.Status -Expected 502)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.invalid.cache.first" -Actual $r6.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $r7 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/8888888888"
    if (!(Assert-Equal -Name "a.invalid.status.second" -Actual $r7.Status -Expected 502)) { $script:failed = $true }
    if (!(Assert-Equal -Name "a.invalid.cache.second" -Actual $r7.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $r8 = Parse-Response -Method "POST" -Uri "http://127.0.0.1:18110/check/1234567890"
    if (!(Assert-Equal -Name "a.method.status" -Actual $r8.Status -Expected 405)) { $script:failed = $true }

    $r9 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/12345"
    if (!(Assert-Equal -Name "a.no_porsi.status" -Actual $r9.Status -Expected 400)) { $script:failed = $true }

    $r10 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/health"
    if (!(Assert-OneOf -Name "a.health.status" -Actual $r10.Status -ExpectedValues @(200, 503))) { $script:failed = $true }
}

Run-Scenario -CacheDir (Join-Path $env:TEMP "hajj_cache_smoke_b") -CacheTtl 1 -Runner {
    $t1 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/5555555555"
    if (!(Assert-Equal -Name "b.ttl.status.first" -Actual $t1.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "b.ttl.cache.first" -Actual $t1.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }

    $t2 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/5555555555"
    if (!(Assert-Equal -Name "b.ttl.status.second" -Actual $t2.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "b.ttl.cache.second" -Actual $t2.Headers["x-cache"] -Expected "HIT")) { $script:failed = $true }

    Start-Sleep -Seconds 2

    $t3 = Parse-Response -Method "GET" -Uri "http://127.0.0.1:18110/check/5555555555"
    if (!(Assert-Equal -Name "b.ttl.status.third" -Actual $t3.Status -Expected 200)) { $script:failed = $true }
    if (!(Assert-Equal -Name "b.ttl.cache.third" -Actual $t3.Headers["x-cache"] -Expected "MISS")) { $script:failed = $true }
}

if ($failed) {
    exit 1
}

Write-Output "Smoke test selesai: semua case cache lulus."

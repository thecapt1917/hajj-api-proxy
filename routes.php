<?php
# comment
require_once __DIR__ . "/router.php";
require_once __DIR__ . "/lib/check_api_bootstrap.php";
require_once __DIR__ . "/lib/Support/RequestContext.php";

function controller_instance(string $key, callable $builder): mixed
{
    static $instances = [];
    if (!array_key_exists($key, $instances)) {
        $instances[$key] = $builder();
    }

    return $instances[$key];
}

function check_controller(): CheckController
{
    return controller_instance("check", fn() => build_check_controller());
}

function health_controller(): HealthController
{
    return controller_instance("health", fn() => build_health_controller());
}

function embarkation_controller(): EmbarkationController
{
    return controller_instance(
        "embarkation",
        fn() => build_embarkation_controller(),
    );
}

function schedule_controller(): ScheduleController
{
    return controller_instance("schedule", fn() => build_schedule_controller());
}

function metrics_controller(): MetricsController
{
    return controller_instance("metrics", fn() => build_metrics_controller());
}

function cache_maintenance_controller(): CacheMaintenanceController
{
    return controller_instance(
        "cache_maintenance",
        fn() => build_cache_maintenance_controller(),
    );
}

function is_check_namespace_request_path(): bool
{
    return RequestContext::startsWithPath("/check");
}

function is_embarkation_namespace_request_path(): bool
{
    return RequestContext::startsWithPath("/embarkasi");
}

function is_schedule_namespace_request_path(): bool
{
    return RequestContext::startsWithPath("/jadwal");
}

function should_redirect_unknown_path_to_home(): bool
{
    return RequestContext::method() === "GET" && RequestContext::wantsHtml();
}

function send_json_not_found(): void
{
    http_response_code(404);
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");
    header("X-Content-Type-Options: nosniff");
    echo json_encode(
        [
            "success" => false,
            "code" => "404",
            "message" => "Route tidak ditemukan",
            "data" => null,
        ],
        JSON_UNESCAPED_UNICODE,
    );
    exit();
}

any("/check", fn() => check_controller()->handleBadPath());
any(
    '/check/$no_porsi',
    fn($no_porsi) => check_controller()->handleCheck($no_porsi),
);
any("/embarkasi", fn() => embarkation_controller()->handleList());
any(
    '/jadwal/$embarkasi/$kloter',
    fn($embarkasi, $kloter) => schedule_controller()->handleSchedule(
        $embarkasi,
        $kloter,
    ),
);
any("/health", fn() => health_controller()->handle());
any("/metrics", fn() => metrics_controller()->handle());
any("/metrics/reset", fn() => metrics_controller()->handleReset());
any("/cache/prune", fn() => cache_maintenance_controller()->handlePrune());

// Static GET
// In the URL -> http://localhost
// The output -> Index
get("/", "views/index.php");

if (is_check_namespace_request_path()) {
    check_controller()->handleBadPath();
}

if (is_embarkation_namespace_request_path()) {
    embarkation_controller()->handleBadPath();
}

if (is_schedule_namespace_request_path()) {
    schedule_controller()->handleBadPath();
}

if (should_redirect_unknown_path_to_home()) {
    header("Location: /", true, 302);
    exit();
}

send_json_not_found();

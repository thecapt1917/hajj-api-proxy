<?php

require_once __DIR__ . "/CheckApi/CheckApiConfig.php";
require_once __DIR__ . "/CheckApi/JsonResponder.php";
require_once __DIR__ . "/CheckApi/UpstreamClient.php";
require_once __DIR__ . "/CheckApi/ResponseNormalizer.php";
require_once __DIR__ . "/CheckApi/CheckRequestUseCase.php";
require_once __DIR__ . "/Metrics/FileMetricsStore.php";
require_once __DIR__ . "/Metrics/MetricsController.php";
require_once __DIR__ . "/Cache/FileCache.php";
require_once __DIR__ . "/Cache/CacheMaintenanceController.php";
require_once __DIR__ . "/CheckApi/CheckController.php";
require_once __DIR__ . "/Health/HealthController.php";
require_once __DIR__ . "/Security/AdminAccessGuard.php";
require_once __DIR__ . "/Support/EnvLoader.php";
require_once __DIR__ . "/Support/RequestContext.php";

function load_app_env(): void
{
    $envLoader = new EnvLoader();
    $envLoader->load(dirname(__DIR__) . "/.env");
}

function build_check_controller(): CheckController
{
    load_app_env();
    $config = new CheckApiConfig();
    $responder = new JsonResponder();
    $upstreamClient = new UpstreamClient($config);
    $normalizer = new ResponseNormalizer();
    $fileCache = new FileCache($config->getCacheDirectory());
    $metricsStore = new FileMetricsStore($config->getMetricsFilePath());
    $checkRequestUseCase = new CheckRequestUseCase(
        $upstreamClient,
        $normalizer,
        $fileCache,
        $metricsStore,
        $config->isCacheEnabled(),
        $config->getCacheTtlSeconds()
    );

    return new CheckController(
        $checkRequestUseCase,
        $responder,
    );
}

function build_health_controller(): HealthController
{
    load_app_env();
    $responder = new JsonResponder();
    return new HealthController($responder);
}

function build_metrics_controller(): MetricsController
{
    load_app_env();
    $config = new CheckApiConfig();
    $responder = new JsonResponder();
    $metricsStore = new FileMetricsStore($config->getMetricsFilePath());
    $adminAccessGuard = new AdminAccessGuard($config);
    return new MetricsController($responder, $metricsStore, $adminAccessGuard);
}

function build_cache_maintenance_controller(): CacheMaintenanceController
{
    load_app_env();
    $config = new CheckApiConfig();
    $responder = new JsonResponder();
    $fileCache = new FileCache($config->getCacheDirectory());
    $adminAccessGuard = new AdminAccessGuard($config);
    return new CacheMaintenanceController($responder, $fileCache, $adminAccessGuard);
}

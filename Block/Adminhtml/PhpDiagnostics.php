<?php

namespace MDOQ\Infrastructure\Block\Adminhtml;

use Magento\Backend\Block\Template;

class PhpDiagnostics extends Template
{
    public function getFpmStatus(): array
    {
        if (!function_exists('fpm_get_status')) {
            return ['available' => false, 'reason' => __('fpm_get_status() is unavailable in this PHP runtime.')];
        }

        try {
            $status = fpm_get_status();
        } catch (\Throwable) {
            return ['available' => false, 'reason' => __('PHP-FPM status could not be read.')];
        }

        if (!is_array($status)) {
            return ['available' => false, 'reason' => __('PHP-FPM did not return status data.')];
        }

        return ['available' => true, 'status' => $status];
    }

    public function getOpcacheDashboard(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['available' => false, 'reason' => __('The OPcache extension is not loaded.')];
        }

        $status = @opcache_get_status(true);
        $configuration = @opcache_get_configuration();
        if (!is_array($status) || empty($status['opcache_enabled']) || !is_array($configuration)) {
            return ['available' => false, 'reason' => __('OPcache is disabled or status access is restricted.')];
        }

        $memory = $status['memory_usage'] ?? [];
        $strings = $status['interned_strings_usage'] ?? [];
        $statistics = $status['opcache_statistics'] ?? [];
        $directives = array_filter(
            $configuration['directives'] ?? [],
            static fn($name) => strpos((string)$name, 'opcache.') === 0,
            ARRAY_FILTER_USE_KEY
        );
        ksort($directives, SORT_NATURAL | SORT_FLAG_CASE);

        $scripts = $status['scripts'] ?? [];
        if (!is_array($scripts)) {
            $scripts = [];
        }
        foreach ($scripts as $path => &$script) {
            $script['full_path'] = $script['full_path'] ?? $path;
        }
        unset($script);
        usort($scripts, static fn($first, $second) => ($second['last_used_timestamp'] ?? 0) <=> ($first['last_used_timestamp'] ?? 0));

        $blacklist = [];
        $blacklistFile = $directives['opcache.blacklist_filename'] ?? '';
        if (is_string($blacklistFile) && is_readable($blacklistFile)) {
            foreach (file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && $line[0] !== ';' && $line[0] !== '#') {
                    $blacklist[] = $line;
                }
            }
        }

        $memoryLimit = max(0, (float)($directives['opcache.memory_consumption'] ?? 0)) * 1024 * 1024;
        $maximumKeys = (int)($statistics['max_cached_keys'] ?? 0);
        $preloaded = $status['preload_statistics']['scripts'] ?? [];

        return [
            'available' => true,
            'version' => $configuration['version']['version'] ?? __('Unknown'),
            'status' => [
                'cache_full' => !empty($status['cache_full']),
                'restart_pending' => !empty($status['restart_pending']),
                'start_time' => $statistics['start_time'] ?? null,
                'last_restart_time' => $statistics['last_restart_time'] ?? null,
                'cached_scripts' => $statistics['num_cached_scripts'] ?? count($scripts),
                'cached_keys' => $statistics['num_cached_keys'] ?? 0,
                'maximum_keys' => $maximumKeys,
                'key_usage_percent' => $maximumKeys > 0
                    ? round(100 * (int)($statistics['num_cached_keys'] ?? 0) / $maximumKeys, 1)
                    : null,
                'hits' => $statistics['hits'] ?? 0,
                'misses' => $statistics['misses'] ?? 0,
                'blacklist_misses' => $statistics['blacklist_misses'] ?? 0,
                'hit_rate' => $statistics['opcache_hit_rate'] ?? null,
                'used_memory' => $memory['used_memory'] ?? 0,
                'free_memory' => $memory['free_memory'] ?? 0,
                'wasted_memory' => $memory['wasted_memory'] ?? 0,
                'memory_limit' => $memoryLimit,
                'memory_usage_percent' => $memoryLimit > 0
                    ? round(100 * ((int)($memory['used_memory'] ?? 0) + (int)($memory['wasted_memory'] ?? 0)) / $memoryLimit, 1)
                    : null,
                'interned_strings' => $strings,
                'jit' => $status['jit'] ?? [],
            ],
            'directives' => $directives,
            'scripts' => array_values($scripts),
            'blacklist' => $blacklist,
            'preloaded' => is_array($preloaded) ? array_keys($preloaded) : [],
            'functions' => get_extension_funcs('Zend OPcache') ?: [],
        ];
    }

    public function getRuntimeStatus(): array
    {
        $memoryLimit = ini_get('memory_limit') ?: __('Unlimited');
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $sessionPath = session_save_path();

        return [
            'PHP version' => PHP_VERSION,
            'Execution mode' => PHP_SAPI,
            'Request time' => date('Y-m-d H:i:s', (int)($_SERVER['REQUEST_TIME'] ?? time())),
            'Memory in use' => $this->formatBytes(memory_get_usage(true)),
            'Peak memory' => $this->formatBytes(memory_get_peak_usage(true)),
            'Memory limit' => $memoryLimit,
            'Loaded extensions' => count(get_loaded_extensions()),
            'OPcache' => is_array($opcache) && !empty($opcache['opcache_enabled']) ? __('Enabled') : __('Unavailable'),
            'Session storage' => $sessionPath !== '' ? $sessionPath : __('PHP default'),
        ];
    }

    public function formatBytes($bytes): string
    {
        if (!is_numeric($bytes)) {
            return (string)__('Not available');
        }

        $bytes = (float)$bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return number_format($bytes, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
    }
}
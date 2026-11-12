<?php

namespace MDOQ\Infrastructure\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;

class CacheRecommendations
{
    private const SESSION_WARNING =
        'The installed Redis session handler does not document zstd support; enabling it may cause session writes to fail.';
    private const COMPRESSION_MISSING =
        'Compression missing: this PHP runtime has no supported compression library. Install zstd, lz4, or zlib before applying settings.';

    public function __construct(
        private DeploymentConfig $deploymentConfig,
        private ProductMetadataInterface $productMetadata,
        private CompressionLibraryResolver $compressionLibraryResolver
    ) {
    }

    public function getReport(): array
    {
        $env = $this->deploymentConfig->getConfigData();
        $version = $this->productMetadata->getVersion();
        $recommendationVersion = $this->getRecommendationVersion($version);
        $compressionLibrary = $this->compressionLibraryResolver->resolve();
        $scopes = [];

        if ($recommendationVersion === null) {
            $reason = __('No Redis compression recommendation is defined for Magento %1.', $version);
            foreach (['sessions', 'default', 'page_cache'] as $scope) {
                $scopes[] = $this->notApplicable($scope, $reason);
            }
        } else {
            $scopes[] = $this->getSessionScope($env, $compressionLibrary);
            $scopes[] = $this->getCacheScope($env, 'default', $recommendationVersion, $compressionLibrary);
            $scopes[] = $this->getCacheScope($env, 'page_cache', $recommendationVersion, $compressionLibrary);
        }

        return [
            'version' => $version,
            'recommendation_version' => $recommendationVersion,
            'compression_library' => $compressionLibrary,
            'compression_missing' => $compressionLibrary === null,
            'compression_message' => $this->getCompressionMessage($compressionLibrary),
            'scopes' => $scopes,
        ];
    }

    public function getUpdates(): array
    {
        $updates = [];
        foreach ($this->getReport()['scopes'] as $scope) {
            if (!$scope['applicable'] || !$scope['changes']) {
                continue;
            }

            $changedValues = [];
            foreach ($scope['changes'] as $change) {
                $changedValues[$change['key']] = $change['recommended'];
            }

            if ($scope['id'] === 'sessions') {
                $updates['session']['redis'] = $changedValues;
            } else {
                $updates['cache']['frontend'][$scope['id']]['backend_options'] = $changedValues;
            }
        }

        return $updates;
    }

    private function getSessionScope(array $env, ?string $compressionLibrary): array
    {
        $session = $env['session'] ?? [];
        if (!is_array($session) || ($session['save'] ?? null) !== 'redis') {
            return $this->notApplicable(
                'sessions',
                __('Session storage is not configured to use Redis.')
            );
        }

        if ($compressionLibrary === null) {
            return $this->notApplicable('sessions', __(self::COMPRESSION_MISSING));
        }

        return $this->buildScope(
            'sessions',
            $session['redis'] ?? [],
            [
                'compression_threshold' => '20480',
                'compression_library' => $compressionLibrary,
            ],
            $compressionLibrary === 'zstd' ? self::SESSION_WARNING : null
        );
    }

    private function getCacheScope(
        array $env,
        string $scope,
        string $recommendationVersion,
        ?string $compressionLibrary
    ): array
    {
        $frontend = $env['cache']['frontend'][$scope] ?? [];
        if (!is_array($frontend)) {
            return $this->notApplicable($scope, __('Cache frontend configuration is unavailable.'));
        }

        $backend = strtolower((string)($frontend['backend'] ?? ''));
        $legacyBackend = in_array(
            $backend,
            ['cm_cache_backend_redis', 'magento\\framework\\cache\\backend\\redis'],
            true
        );
        $newBackend = $recommendationVersion === '2.4.9' && in_array($backend, ['redis', 'valkey'], true);
        if (!$legacyBackend && !$newBackend) {
            return $this->notApplicable(
                $scope,
                __('The configured cache backend is not supported by the Redis compression recommendation.')
            );
        }

        if ($compressionLibrary === null) {
            return $this->notApplicable($scope, __(self::COMPRESSION_MISSING));
        }

        $backendLibrary = $legacyBackend && $compressionLibrary === 'lz4'
            ? 'l4z'
            : $compressionLibrary;
        if ($legacyBackend) {
            $options = [
                'compress_data' => '1',
                'compress_threshold' => '20480',
                'compression_lib' => $backendLibrary,
            ];
        } else {
            $options = [
                'compress_data' => '1',
                'compression_lib' => $backendLibrary,
                'compression_level' => '1',
                'compression_threshold' => '20480',
            ];
        }

        return $this->buildScope(
            $scope,
            $frontend['backend_options'] ?? [],
            $options
        );
    }

    private function getCompressionMessage(?string $compressionLibrary): string
    {
        if ($compressionLibrary === null) {
            return (string)__(self::COMPRESSION_MISSING);
        }
        if ($compressionLibrary === 'lz4') {
            return (string)__('zstd is unavailable; using lz4. Gzip is the final fallback.');
        }
        if ($compressionLibrary === 'gzip') {
            return (string)__('zstd and lz4 are unavailable; using gzip.');
        }

        return (string)__('Using zstd compression.');
    }

    private function buildScope(string $id, $current, array $recommended, ?string $warning = null): array
    {
        $current = is_array($current) ? $current : [];
        $changes = [];
        foreach ($recommended as $key => $value) {
            $currentValue = $current[$key] ?? null;
            if (
                array_key_exists($key, $current)
                && is_scalar($currentValue)
                && (string)$currentValue === (string)$value
            ) {
                continue;
            }
            $path = $id === 'sessions'
                ? 'session/redis/' . $key
                : 'cache/frontend/' . $id . '/backend_options/' . $key;
            $changes[] = [
                'key' => $key,
                'path' => $path,
                'current' => $currentValue,
                'recommended' => $value,
            ];
        }

        return [
            'id' => $id,
            'applicable' => true,
            'recommended' => $recommended,
            'changes' => $changes,
            'is_recommended' => !$changes,
            'reason' => null,
            'warning' => $warning,
        ];
    }

    private function notApplicable(string $id, $reason): array
    {
        return [
            'id' => $id,
            'applicable' => false,
            'recommended' => [],
            'changes' => [],
            'is_recommended' => false,
            'reason' => (string)$reason,
            'warning' => null,
        ];
    }

    private function getRecommendationVersion(string $version): ?string
    {
        if (preg_match('/^2\.4\.(8|9)(?:[-.]|$)/', $version, $matches)) {
            return '2.4.' . $matches[1];
        }

        return null;
    }
}

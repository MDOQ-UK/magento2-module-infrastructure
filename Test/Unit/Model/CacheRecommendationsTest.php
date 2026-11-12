<?php

declare(strict_types=1);

namespace MDOQ\Infrastructure\Test\Unit\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use MDOQ\Infrastructure\Model\CompressionLibraryResolver;
use MDOQ\Infrastructure\Model\CacheRecommendations;
use PHPUnit\Framework\TestCase;

class CacheRecommendationsTest extends TestCase
{
    public function testMagento248LegacyBackendsGetLegacyOptionsAndSessionSpecificKeys(): void
    {
        $recommendations = $this->createRecommendations('2.4.8-p5', [
            'session' => ['save' => 'redis', 'redis' => []],
            'cache' => ['frontend' => [
                'default' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
                'page_cache' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
            ]],
        ]);

        $updates = $recommendations->getUpdates();

        self::assertSame(
            ['compression_threshold' => '20480', 'compression_library' => 'zstd'],
            $updates['session']['redis']
        );
        self::assertSame(
            ['compress_data' => '1', 'compress_threshold' => '20480', 'compression_lib' => 'zstd'],
            $updates['cache']['frontend']['default']['backend_options']
        );
        self::assertArrayHasKey('page_cache', $updates['cache']['frontend']);
    }

    public function testLz4FallbackUsesBackendSpecificNames(): void
    {
        $recommendations = $this->createRecommendations('2.4.8', [
            'session' => ['save' => 'redis', 'redis' => []],
            'cache' => ['frontend' => [
                'default' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
                'page_cache' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
            ]],
        ], 'lz4');

        $updates = $recommendations->getUpdates();

        self::assertSame('lz4', $updates['session']['redis']['compression_library']);
        self::assertSame('l4z', $updates['cache']['frontend']['default']['backend_options']['compression_lib']);
    }

    public function testCompressionLibrariesUseTheRequestedFallbackOrder(): void
    {
        $resolver = new CompressionLibraryResolver();

        self::assertSame('zstd', $resolver->select(['gzip', 'lz4', 'zstd']));
        self::assertSame('lz4', $resolver->select(['gzip', 'lz4']));
        self::assertSame('gzip', $resolver->select(['gzip']));
        self::assertNull($resolver->select([]));
    }

    public function testNoAvailableLibraryDisablesSettingsAndOmitsUpdates(): void
    {
        $recommendations = $this->createRecommendations('2.4.8', [
            'session' => ['save' => 'redis', 'redis' => []],
            'cache' => ['frontend' => [
                'default' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
                'page_cache' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
            ]],
        ], null);
        $report = $recommendations->getReport();

        self::assertTrue($report['compression_missing']);
        self::assertFalse($report['scopes'][0]['applicable']);
        self::assertFalse($report['scopes'][1]['applicable']);
        self::assertSame([], $recommendations->getUpdates());
    }

    public function testMagento249UsesRecommendationsForEachBackendType(): void
    {
        $recommendations = $this->createRecommendations('2.4.9', [
            'session' => ['save' => 'redis', 'redis' => []],
            'cache' => ['frontend' => [
                'default' => ['backend' => 'redis', 'backend_options' => []],
                'page_cache' => ['backend' => 'Magento\\Framework\\Cache\\Backend\\Redis', 'backend_options' => []],
            ]],
        ]);

        $updates = $recommendations->getUpdates();
        $newBackendOptions = [
            'compress_data' => '1',
            'compression_lib' => 'zstd',
            'compression_level' => '1',
            'compression_threshold' => '20480',
        ];

        self::assertSame($newBackendOptions, $updates['cache']['frontend']['default']['backend_options']);
        self::assertSame(
            ['compress_data' => '1', 'compress_threshold' => '20480', 'compression_lib' => 'zstd'],
            $updates['cache']['frontend']['page_cache']['backend_options']
        );
    }

    public function testReportListsOnlyDifferencesAndIgnoresNonRedisScopes(): void
    {
        $recommendations = $this->createRecommendations('2.4.8', [
            'session' => ['save' => 'files'],
            'cache' => ['frontend' => [
                'default' => [
                    'backend' => 'Cm_Cache_Backend_Redis',
                    'backend_options' => [
                        'compress_data' => 1,
                        'compress_threshold' => '20480',
                        'compression_lib' => 'zstd',
                    ],
                ],
                'page_cache' => ['backend' => 'filesystem'],
            ]],
        ]);

        $report = $recommendations->getReport();

        self::assertSame([], $report['scopes'][1]['changes']);
        self::assertTrue($report['scopes'][1]['is_recommended']);
        self::assertFalse($report['scopes'][0]['applicable']);
        self::assertFalse($report['scopes'][2]['applicable']);
        self::assertSame([], $recommendations->getUpdates());
    }

    public function testReportDetailsIncorrectSettings(): void
    {
        $recommendations = $this->createRecommendations('2.4.8', [
            'session' => ['save' => 'redis', 'redis' => ['compression_library' => 'gzip']],
            'cache' => ['frontend' => [
                'default' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => [
                    'compress_data' => '0',
                ]],
                'page_cache' => ['backend' => 'Cm_Cache_Backend_Redis', 'backend_options' => []],
            ]],
        ]);

        $report = $recommendations->getReport();

        self::assertSame(
            [
                [
                    'key' => 'compression_threshold',
                    'path' => 'session/redis/compression_threshold',
                    'current' => null,
                    'recommended' => '20480',
                ],
                [
                    'key' => 'compression_library',
                    'path' => 'session/redis/compression_library',
                    'current' => 'gzip',
                    'recommended' => 'zstd',
                ],
            ],
            $report['scopes'][0]['changes']
        );
        self::assertSame(
            '1',
            $report['scopes'][1]['changes'][0]['recommended']
        );
    }

    public function testUnknownMagentoVersionHasNoUpdates(): void
    {
        $recommendations = $this->createRecommendations('2.4.10', [
            'session' => ['save' => 'redis'],
            'cache' => ['frontend' => [
                'default' => ['backend' => 'redis'],
                'page_cache' => ['backend' => 'redis'],
            ]],
        ]);

        self::assertNull($recommendations->getReport()['recommendation_version']);
        self::assertSame([], $recommendations->getUpdates());
    }

    private function createRecommendations(string $version, array $env, ?string $library = 'zstd'): CacheRecommendations
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('getConfigData')->willReturn($env);

        $productMetadata = $this->createMock(ProductMetadataInterface::class);
        $productMetadata->method('getVersion')->willReturn($version);

        $compressionLibraryResolver = $this->createMock(CompressionLibraryResolver::class);
        $compressionLibraryResolver->method('resolve')->willReturn($library);

        return new CacheRecommendations($deploymentConfig, $productMetadata, $compressionLibraryResolver);
    }
}

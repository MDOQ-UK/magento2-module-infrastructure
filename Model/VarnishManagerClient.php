<?php

namespace MDOQ\Infrastructure\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\HTTP\Client\Curl;
use RuntimeException;

class VarnishManagerClient
{
    private const PORT = 8090;

    private const ENDPOINTS = [
        'stats' => '/stats.json',
        'config' => '/config.json',
        'env' => '/env.json',
        'vcl' => '/vcl.json',
    ];

    public function __construct(
        private DeploymentConfig $deploymentConfig
    ) {
    }

    public function get(string $endpoint): array
    {
        if (!isset(self::ENDPOINTS[$endpoint])) {
            throw new RuntimeException('Unsupported Varnish manager endpoint.');
        }

        $curl = new Curl();
        $curl->setTimeout(15);
        $curl->get($this->getBaseUrl() . self::ENDPOINTS[$endpoint]);

        return ['status' => $curl->getStatus(), 'body' => $curl->getBody()];
    }

    public function uploadVcl(\CURLFile $file): array
    {
        $curl = new Curl();
        $curl->setTimeout(120);
        $curl->setOption(CURLOPT_POSTFIELDS, ['vcl_file' => $file]);
        $curl->post($this->getBaseUrl() . self::ENDPOINTS['vcl'], []);

        return ['status' => $curl->getStatus(), 'body' => $curl->getBody()];
    }

    private function getBaseUrl(): string
    {
        $hosts = $this->deploymentConfig->getConfigData('http_cache_hosts');
        $host = is_array($hosts) ? ($hosts[0]['host'] ?? null) : null;

        if (!is_string($host) || !preg_match('/\A[a-zA-Z0-9.-]+\z/', $host)) {
            throw new RuntimeException('No valid Varnish host is configured in Magento.');
        }

        return 'http://' . $host . ':' . self::PORT;
    }
}
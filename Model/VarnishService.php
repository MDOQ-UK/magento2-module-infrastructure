<?php

namespace MDOQ\Infrastructure\Model;

use MDOQ\Infrastructure\Api\VarnishServiceInterface;
use RuntimeException;

class VarnishService implements VarnishServiceInterface
{
    public function __construct(private VarnishManagerClient $managerClient)
    {
    }

    public function updateVcl(string $vclContent): array
    {
        if (trim($vclContent) === '') {
            throw new RuntimeException('VCL content cannot be empty.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mdoq-varnish-vcl-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to prepare the VCL upload.');
        }

        try {
            if (file_put_contents($temporaryPath, $vclContent) === false) {
                throw new RuntimeException('Unable to prepare the VCL upload.');
            }

            $file = new \CURLFile($temporaryPath, 'text/plain', 'varnish.vcl');
            $response = $this->managerClient->uploadVcl($file);
            $data = json_decode($response['body'], true);

            if (!is_array($data)) {
                throw new RuntimeException('Varnish manager returned an invalid response.');
            }

            if ($response['status'] < 200 || $response['status'] >= 300) {
                throw new RuntimeException($data['error'] ?? 'Varnish manager rejected the VCL upload.');
            }

            return $data;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to upload VCL to the Varnish manager.', 0, $exception);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
<?php

namespace MDOQ\Infrastructure\Api;

/**
 * Internal service contract for managing Varnish.
 *
 * @api
 */
interface VarnishServiceInterface
{
    /**
     * Upload and activate VCL content through the Varnish manager.
     *
     * @param string $vclContent VCL source code
     * @return array Varnish manager response
    * @throws \RuntimeException If the VCL cannot be uploaded or is rejected
     */
    public function updateVcl(string $vclContent): array;
}
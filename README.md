# Infrastructure

On the Varnish VCL tab, select **Upload VCL** to reveal the local file upload form.

The **Cache** tab compares the Redis compression settings in `app/etc/env.php`
with recommendations for Magento 2.4.8 and 2.4.9. It checks sessions, the
default cache, and the page cache independently, and only applies settings to
Redis-backed scopes. The Apply action preserves unrelated deployment settings,
asks for confirmation, and invalidates `env.php` in OPcache. Session
recommendations use the session handler's own option names; zstd is included
there at the operator's request even though the installed session handler does
not document support for it. The tab chooses the first available compressor
in the order zstd, lz4, then gzip, checking both compression and decompression
functions in the current PHP runtime. If none are available, it reports
compression as missing and disables applying settings.

## Rendering Varnish templates

`MDOQ\Infrastructure\Model\VarnishTemplateRenderer::render()` fetches the Varnish
manager's environment and renders a template with `dockerize`. Install a `dockerize`
binary on the PHP process's `PATH`, using the same version as Varnish. Only variables
returned by the manager are passed to the renderer; missing variables and rendering
errors are not replaced with PHP-container environment values.

## Internal Varnish API

Inject `MDOQ\Infrastructure\Api\VarnishServiceInterface` into another Magento module and call `updateVcl()` with the VCL source:

```php
use MDOQ\Infrastructure\Api\VarnishServiceInterface;

public function __construct(
	private VarnishServiceInterface $mdoqVarnishService
) {
}

public function updateVarnish(string $vclContent): array
{
	return $this->mdoqVarnishService->updateVcl($vclContent);
}
```

The service uploads the source as `vcl_file` to the configured Varnish manager and returns its decoded JSON response. It throws `RuntimeException` if the upload fails or is rejected. It is an internal PHP API; it does not expose a REST endpoint.
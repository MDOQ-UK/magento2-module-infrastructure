# Infrastructure

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
<?php

namespace MDOQ\Infrastructure\Api;

/**
 * A block of VCL that another module needs in the Varnish configuration.
 *
 * Register implementations against MDOQ\Infrastructure\Model\Vcl\SnippetComposer's "providers" argument in di.xml.
 * Every VCL uploaded through VarnishServiceInterface::updateVcl() has the registered snippets inserted.
 *
 * @api
 */
interface VclSnippetProviderInterface
{
    /**
     * Stable identifier used in the begin/end markers; letters, numbers, ".", "_" and "-" only.
     */
    public function getName(): string;

    /**
     * Name of the subroutine (e.g. "vcl_deliver") this snippet must appear above, or null to append at the end.
     *
     * Varnish concatenates same-named subroutines in source order, so a snippet defining "sub vcl_deliver"
     * placed above the main one runs first.
     */
    public function getInsertBefore(): ?string;

    /**
     * VCL to insert. Return an empty string when the snippet should not be present (e.g. feature disabled);
     * any previously inserted copy is then removed on the next upload.
     */
    public function getVcl(): string;
}

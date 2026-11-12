<?php

namespace MDOQ\Infrastructure\Model\Vcl;

use MDOQ\Infrastructure\Api\VclSnippetProviderInterface;
use RuntimeException;

/**
 * Inserts registered module snippets into a VCL document.
 *
 * Each snippet is wrapped in begin/end markers. Every marked block is stripped before re-inserting, so composing
 * is idempotent: re-uploading the live VCL never duplicates a snippet, and a snippet whose provider now returns
 * an empty string (or is no longer registered) is removed.
 */
class SnippetComposer
{
    private const BEGIN = '# >>> mdoq-snippet:%s (managed by MDOQ_Infrastructure; edits here are overwritten)';
    private const END = '# <<< mdoq-snippet:%s';
    private const NAME_PATTERN = '/\A[A-Za-z0-9._-]+\z/';
    private const SUB_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*\z/';

    /**
     * @param VclSnippetProviderInterface[] $providers
     */
    public function __construct(private array $providers = [])
    {
    }

    public function compose(string $vcl): string
    {
        $vcl = $this->strip($vcl);

        // Group by anchor so several snippets for the same subroutine keep their registration order.
        $byAnchor = [];
        foreach ($this->getProviders() as $provider) {
            $snippet = trim($provider->getVcl());
            if ($snippet === '') {
                continue;
            }
            $name = $provider->getName();
            $block = sprintf(self::BEGIN, $name) . "\n" . $snippet . "\n" . sprintf(self::END, $name) . "\n";
            $byAnchor[(string)$provider->getInsertBefore()][] = $block;
        }

        $insertions = [];
        foreach ($byAnchor as $anchor => $blocks) {
            $offset = $anchor === '' ? null : $this->findSubroutine($vcl, $anchor);
            if ($offset === null) {
                $vcl = rtrim($vcl) . "\n\n" . implode("\n", $blocks);
                continue;
            }
            $insertions[$offset] = implode("\n", $blocks) . "\n";
        }

        // Insert from the end so earlier offsets stay valid.
        krsort($insertions);
        foreach ($insertions as $offset => $text) {
            $vcl = substr($vcl, 0, $offset) . $text . substr($vcl, $offset);
        }

        return $vcl;
    }

    /**
     * Remove every managed block, whether or not its provider is still registered.
     */
    public function strip(string $vcl): string
    {
        return (string)preg_replace(
            '/^# >>> mdoq-snippet:([A-Za-z0-9._-]+)[^\n]*\n.*?^# <<< mdoq-snippet:\1[^\n]*(?:\n\n?|\z)/ms',
            '',
            $vcl
        );
    }

    /**
     * @return string[] names of the snippets that would currently be inserted
     */
    public function getActiveSnippetNames(): array
    {
        $names = [];
        foreach ($this->getProviders() as $provider) {
            if (trim($provider->getVcl()) !== '') {
                $names[] = $provider->getName();
            }
        }
        return $names;
    }

    /**
     * @return VclSnippetProviderInterface[]
     */
    private function getProviders(): array
    {
        foreach ($this->providers as $key => $provider) {
            if (!$provider instanceof VclSnippetProviderInterface) {
                throw new RuntimeException(sprintf('VCL snippet provider "%s" must implement %s.', $key, VclSnippetProviderInterface::class));
            }
            if (!preg_match(self::NAME_PATTERN, $provider->getName())) {
                throw new RuntimeException(sprintf('Invalid VCL snippet name "%s".', $provider->getName()));
            }
            $anchor = $provider->getInsertBefore();
            if ($anchor !== null && !preg_match(self::SUB_PATTERN, $anchor)) {
                throw new RuntimeException(sprintf('Invalid subroutine name "%s" for VCL snippet "%s".', $anchor, $provider->getName()));
            }
        }
        return $this->providers;
    }

    private function findSubroutine(string $vcl, string $name): ?int
    {
        if (!preg_match('/^[ \t]*sub[ \t]+' . preg_quote($name, '/') . '\b/m', $vcl, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        return $match[0][1];
    }
}

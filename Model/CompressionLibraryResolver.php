<?php

namespace MDOQ\Infrastructure\Model;

class CompressionLibraryResolver
{
    public function resolve(): ?string
    {
        return $this->select($this->getAvailableLibraries());
    }

    public function select(array $available): ?string
    {
        foreach (['zstd', 'lz4', 'gzip'] as $library) {
            if (in_array($library, $available, true)) {
                return $library;
            }
        }

        return null;
    }

    private function getAvailableLibraries(): array
    {
        $libraries = [];
        if (function_exists('zstd_compress') && function_exists('zstd_uncompress')) {
            $libraries[] = 'zstd';
        }
        if (function_exists('lz4_compress') && function_exists('lz4_uncompress')) {
            $libraries[] = 'lz4';
        }
        if (function_exists('gzcompress') && function_exists('gzuncompress')) {
            $libraries[] = 'gzip';
        }

        return $libraries;
    }
}

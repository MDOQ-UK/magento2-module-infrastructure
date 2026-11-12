<?php

declare(strict_types=1);

namespace MDOQ\Infrastructure\Model;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class VarnishTemplateRenderer
{
    public function __construct(private readonly VarnishManagerClient $managerClient)
    {
    }

    public function render(string $template): string
    {
        $response = $this->managerClient->get('env');
        $variables = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($variables) || !array_is_list($variables)) {
            throw new RuntimeException('Varnish manager did not return a valid environment.');
        }

        $inherited = $_ENV + (getenv() ?: []);
        $environment = array_fill_keys(array_keys($inherited), false);
        $seen = [];
        foreach ($variables as $variable) {
            if (!is_array($variable) || !isset($variable['key'], $variable['value'])
                || !is_string($variable['key']) || !is_string($variable['value'])
                || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $variable['key'])
                || str_contains($variable['value'], "\0") || isset($seen[$variable['key']])
            ) {
                throw new RuntimeException('Varnish manager returned an invalid environment variable.');
            }
            $environment[$variable['key']] = $variable['value'];
            $seen[$variable['key']] = true;
        }

        $binary = (new ExecutableFinder())->find('dockerize');
        if ($binary === null) {
            throw new RuntimeException('dockerize is not installed or is not on PATH.');
        }
        $path = tempnam(sys_get_temp_dir(), 'mdoq-vcl-template-');
        if ($path === false) {
            throw new RuntimeException('Unable to prepare the VCL template.');
        }
        try {
            if (file_put_contents($path, $template) !== strlen($template)) {
                throw new RuntimeException('Unable to prepare the VCL template.');
            }
            $process = new Process([$binary, '-template', $path], null, $environment, null, 30);
            $process->run();
            if (!$process->isSuccessful()) {
                $error = trim($process->getErrorOutput());
                if (preg_match('/at <\.Env\.([A-Za-z_][A-Za-z0-9_]*)>/', $error, $match)
                    && !isset($seen[$match[1]])
                ) {
                    throw new RuntimeException('Varnish environment does not contain ' . $match[1] . ', required to render the VCL template.');
                }
                throw new RuntimeException('Unable to render the VCL template: ' . $error);
            }
            return $process->getOutput();
        } finally {
            unlink($path);
        }
    }
}

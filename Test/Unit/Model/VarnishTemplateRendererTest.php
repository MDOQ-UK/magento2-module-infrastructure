<?php

declare(strict_types=1);

namespace MDOQ\Infrastructure\Test\Unit\Model;

use MDOQ\Infrastructure\Model\VarnishManagerClient;
use MDOQ\Infrastructure\Model\VarnishTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

class VarnishTemplateRendererTest extends TestCase
{
    public function testRendersWithVarnishEnvironmentOnly(): void
    {
        if (!(new ExecutableFinder())->find('dockerize')) {
            self::markTestSkipped('dockerize is not installed');
        }
        $client = $this->createMock(VarnishManagerClient::class);
        $client->method('get')->with('env')->willReturn([
            'status' => 200,
            'body' => json_encode([
                ['key' => 'BACKEND_HOST', 'value' => 'backend.example'],
                ['key' => 'PROBE_ENABLED', 'value' => 'true'],
                ['key' => 'PURGE_IPS', 'value' => 'one|two'],
            ]),
        ]);
        putenv('MDOQ_TEST_INHERITED=secret');
        try {
            $rendered = (new VarnishTemplateRenderer($client))->render(
                '{{ .Env.BACKEND_HOST }} {{ if isTrue .Env.PROBE_ENABLED }}probe{{ end }} '
                . '{{ range split .Env.PURGE_IPS "|" }}{{ . }} {{ end }}'
                . '{{ if .Env.MDOQ_TEST_INHERITED }}leaked{{ else }}isolated{{ end }}'
            );
            self::assertSame('backend.example probe one two isolated', $rendered);
        } finally {
            putenv('MDOQ_TEST_INHERITED');
        }
    }

    public function testMissingRequiredVariableFailsRendering(): void
    {
        if (!(new ExecutableFinder())->find('dockerize')) {
            self::markTestSkipped('dockerize is not installed');
        }
        $client = $this->createMock(VarnishManagerClient::class);
        $client->method('get')->willReturn(['status' => 200, 'body' => '[]']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Varnish environment does not contain PROBE_ENABLED');
        (new VarnishTemplateRenderer($client))->render('{{ if isTrue .Env.PROBE_ENABLED }}enabled{{ end }}');
    }

    public function testRejectsInvalidEnvironment(): void
    {
        $client = $this->createMock(VarnishManagerClient::class);
        $client->method('get')->willReturn(['status' => 500, 'body' => '[]']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('valid environment');
        (new VarnishTemplateRenderer($client))->render('vcl 4.0;');
    }
}

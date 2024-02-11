<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    protected Processor $processor;

    protected Configuration $configuration;

    public function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration('paradise_security_guzzle');
    }

    public function testSingleClientConfigWithOptions()
    {
        $config = $this->getBaseConfig();
        $processedConfig = $this->processConfig($config);
        $this->assertEquals($this->getProcessedBaseConfig($config), $processedConfig);
    }

    public function testSingleClientConfigWithCertAsArray()
    {
        $replacement = [
            'options' => [
                'cert' => [
                    'path/to/cert',
                    'password'
                ]
            ]
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $processedConfig = $this->processConfig($config);
        $this->assertEquals($this->getProcessedBaseConfig($config), $processedConfig);
    }

    public function testInvalidCertConfiguration()
    {
        $replacement = [
            'options' => [
                'cert' => [
                    'path/to/cert',
                    'password',
                    'Invalid'
                ]
            ]
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $this->expectException(InvalidConfigurationException::class);
        $this->processConfig($config);
    }

    public function testSingleClientConfigWithProxyAsString()
    {
        $replacement = [
            'options' => [
                'proxy' => 'http://proxy.org'
            ]
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $processedConfig = $this->processConfig($config);
        $replacement = [
            'options' => [
                'proxy' => [
                    'http' => 'http://proxy.org'
                ]
            ]
        ];
        $config = $this->replaceInConfig($config, $replacement);
        $this->assertEquals($this->getProcessedBaseConfig($config), $processedConfig);
    }

    public function testHeaderWithUnderscore()
    {
        $replacement = [
            'options' => [
                'headers' => [
                    'Header_underscored' => 'some-random-hash',
                    'Header-hyphened' => 'another-random-hash'
                ],
            ]
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $processedConfig = $this->processConfig($config);
        $headers = $processedConfig['clients']['test_client']['options']['headers'];
        $this->assertArrayHasKey('Header_underscored', $headers);
        $this->assertArrayHasKey('Header-hyphened', $headers);
    }

    public function testCurlOption()
    {
        $replacement = [
            'options' => [
                'curl' => [
                    'sslversion' => \CURL_HTTP_VERSION_1_1
                ]
            ]
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $processedConfig = $this->processConfig($config);
        $this->assertTrue(isset($processedConfig['clients']['test_client']['options']['curl']));
        $curlConfig = $processedConfig['clients']['test_client']['options']['curl'];
        $this->assertCount(1, $curlConfig);
        $this->assertArrayHasKey(\CURLOPT_SSLVERSION, $curlConfig);
        $this->assertEquals($curlConfig[\CURLOPT_SSLVERSION], \CURL_HTTP_VERSION_1_1);
    }

    public function testInvalidCustomHandlerOption()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('handler must be a valid FQCN for a loaded class');
        $replacement = [
            'handler' => 'GuzzleHttp\Handler\TestHandler'
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $this->processConfig($config);
    }

    public function testCustomHandlerOption()
    {
        $replacement = [
            'handler' => 'GuzzleHttp\Handler\MockHandler',
        ];
        $config = $this->replaceInConfig($this->getBaseConfig(), $replacement);
        $processedConfig = $this->processConfig($config);
        $this->assertTrue(isset($processedConfig['clients']['test_client']['handler']));
        $this->assertEquals('GuzzleHttp\Handler\MockHandler', $processedConfig['clients']['test_client']['handler']);
    }

    public function testSlowRequestTimeout()
    {
        $config = [
            'paradise_security_guzzle' => [
                'slow_response_time' => 1000,
                'clients' => []
            ]
        ];
        $processedConfig = $this->processConfig($config);
        $this->assertEquals(1000, $processedConfig['slow_response_time']);
    }

    private function processConfig(array $config): array
    {
        return $this->processor->processConfiguration($this->configuration, $config);
    }

    private function replaceInConfig(array $config, array $replacement): array
    {
        $client = $config['paradise_security_guzzle']['clients']['test_client'];

        $client = array_replace_recursive(
            $client,
            $replacement
        );

        $config['paradise_security_guzzle']['clients']['test_client'] = $client;

        return $config;
    }

    private function getProcessedBaseConfig(array $config): array
    {
        return array_merge_recursive(
            $config['paradise_security_guzzle'],
            [
                'logging' => false,
                'profiling' => false,
                'slow_response_time' => 0,
                'clients' => [
                    'test_client' => [
                        'logging' => null,
                    ]
                ],
            ]
        );
    }

    private function getBaseConfig(): array
    {
        return [
            'paradise_security_guzzle' => [
                'clients' => [
                    'test_client' => [
                        'base_url' => 'http://baseurl/path',
                        'lazy' => false,
                        'handler' => null,
                        'options' => [
                            'auth' => [
                                'user',
                                'pass'
                            ],
                            'headers' => [
                                'Accept' => 'application/json'
                            ],
                            'query' => [],
                            'curl' => [],
                            'cert' => 'path/to/cert',
                            'form_params' => [],
                            'multipart' => [],
                            'connect_timeout' => 5,
                            'debug' => false,
                            'decode_content' => true,
                            'delay' => 1,
                            'http_errors' => false,
                            'expect' => true,
                            'ssl_key' => 'key',
                            'stream' => true,
                            'synchronous' => true,
                            'timeout' => 30,
                            'read_timeout' => 30,
                            'verify' => true,
                            'proxy' => [
                                'http' => 'http://proxy.org',
                                'https' => 'https://proxy.org',
                                'no' => ['host.com', 'host.org']
                            ],
                            'version' => '1.0',
                        ],
                        'class' => '%paradise_security_guzzle_bundle.http_client.class%',
                        'middleware' => [],
                        'plugin' => [],
                    ]
                ]
            ]
        ];
    }
}

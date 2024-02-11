<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\Configuration;
use ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\ParadiseSecurityGuzzleExtension;
use ParadiseSecurity\Bundle\GuzzleBundle\Test\Fake\CustomClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use ParadiseSecurity\Bundle\GuzzleBundle\Log\DevNullLogger;
use Symfony\Component\DependencyInjection\Definition;

class ParadiseSecurityGuzzleExtensionTest extends TestCase
{
    protected string $alias;

    public function setUp(): void
    {
        $extension = $this->createExtension();
        $this->alias = $extension->getAlias();
    }

    public function testGuzzleExtension()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $client = sprintf('%s.client.test_api', $this->alias);
        $clientClass = sprintf('%s.client.test_api_with_custom_class', $this->alias);
        $clientHandler = sprintf('%s.client.test_api_with_custom_handler', $this->alias);

        // test Client
        $this->assertTrue($container->hasDefinition($client));
        $testApi = $container->get($client);
        $this->assertInstanceOf(Client::class, $testApi);
        $this->assertEquals(new Uri('//api.domain.tld/path'), $testApi->getConfig('base_uri'));

        $this->assertTrue($container->hasAlias(ClientInterface::class . ' $testApiClient'));
        $this->assertSame($testApi, $container->get(ClientInterface::class . ' $testApiClient'));
        $this->assertFalse($container->hasAlias('%paradise_security_guzzle.http_client.class% $testApiClient'));

        // test Services
        $this->assertTrue($container->hasDefinition(sprintf('%s.middleware.event_dispatch.test_api', $this->alias)));

        // test Client with custom class
        $this->assertTrue($container->hasDefinition($clientClass));
        $definition = $container->getDefinition($clientClass);
        $this->assertSame(CustomClient::class, $definition->getClass());

        $testApi = $container->get($clientClass);
        $this->assertTrue($container->hasAlias(CustomClient::class . ' $testApiWithCustomClassClient'));
        $this->assertSame($testApi, $container->get(ClientInterface::class . ' $testApiWithCustomClassClient'));

        // test Client with custom handler
        $this->assertTrue($container->hasDefinition($clientHandler));
        /** @var ClientInterface $client */
        $client = $container->get($clientHandler);
        $this->assertInstanceOf(HandlerStack::class, $client->getConfig('handler'));

        // The handler property doesn't have a setter so we have to use reflection to get to its value
        $handlerStackRefl = new \ReflectionClass($client->getConfig('handler'));
        $handler = $handlerStackRefl->getProperty('handler');
        $handler->setAccessible(true);

        $this->assertInstanceOf(MockHandler::class, $handler->getValue($client->getConfig('handler')));
    }

    public function testOverwriteHttpClientClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $container->setParameter(sprintf('%s.http_client.class', $this->alias), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get(sprintf('%s.client.test_api', $this->alias))
        );
    }

    public function testOverrideFormatterClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.formatter', $this->alias);
        $container->setParameter(sprintf('%s.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get($name)
        );
    }

    public function testOverrideSymfonyLogFormatterClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.symfony_log_formatter', $this->alias);
        $container->setParameter(sprintf('%s.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get($name)
        );
    }

    public function testOverrideDataCollectorClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.data_collector', $this->alias);
        $container->setParameter(sprintf('%s.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get($name)
        );
    }

    public function testOverrideLoggerClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $container->setParameter(sprintf('%s.logger.class', $this->alias), DevNullLogger::class);
        $this->assertInstanceOf(
            DevNullLogger::class,
            $container->get(sprintf('%s.test_api_logger', $this->alias))
        );
    }

    public function testOverrideLogMiddlewareClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.middleware.log', $this->alias);
        $container->setParameter(sprintf('%s.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get(sprintf('%s.test_api', $name))
        );
    }

    public function testOverrideSymfonyLogMiddlewareClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.middleware.symfony_log', $this->alias);
        $container->setParameter(sprintf('%s.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get($name)
        );
    }

    public function testOverrideEventDispatchMiddlewareClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.middleware', $this->alias);
        $container->setParameter(sprintf('%s.event_dispatcher.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get(sprintf('%s.event_dispatch.test_api', $name))
        );
    }

    public function testOverrideRequestTimeMiddlewareClass()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $name = sprintf('%s.middleware.request_time', $this->alias);
        $container->setParameter(sprintf('%s.class', $name), \stdClass::class);
        $this->assertInstanceOf(
            \stdClass::class,
            $container->get(sprintf('%s.test_api', $name))
        );
    }

    public function testLoadWithLogging()
    {
        $config = $this->getConfigs();
        $config[0]['logging'] = true;

        $container = $this->createContainer();
        $this->createExtension()->load($config, $container);
        $client = sprintf('%s.client', $this->alias);
        $middleware = sprintf('%s.middleware', $this->alias);

        // test Client
        $this->assertTrue($container->hasDefinition(sprintf('%s.test_api', $client)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.test_api_with_custom_class', $client)));

        // test Services
        $this->assertTrue($container->hasDefinition(sprintf('%s.log.test_api', $middleware)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.event_dispatch.test_api', $middleware)));

        // test logging services (logger, data collector and log middleware for each client)
        foreach (['test_api', 'test_api_with_custom_class','test_api_with_custom_handler'] as $clientName) {
            $this->assertTrue($container->hasDefinition(sprintf('%s.%s_logger', $this->alias, $clientName)));
        }
        $this->assertTrue($container->hasDefinition(sprintf('%s.data_collector', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.formatter', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.symfony_log_formatter', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.twig_extension.debug', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.symfony_log', $middleware)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.log.test_api', $middleware)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.log.test_api_with_custom_class', $middleware)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.request_time.test_api', $middleware)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.request_time.test_api_with_custom_class', $middleware)));

        // test log middleware in handler of the client
        $this->assertCount(1, $this->getClientLogMiddleware($container, sprintf('%s.test_api', $client)));
        $this->assertCount(1, $this->getClientLogMiddleware($container, sprintf('%s.test_api_with_custom_class', $client)));
    }

    public function testLoadWithLoggingSpecificClient()
    {
        $config = $this->getConfigs();
        $config[0]['clients']['test_api_with_custom_class']['logging'] = false;

        $container = $this->createContainer();
        $this->createExtension()->load($config, $container);
        $client = sprintf('%s.client', $this->alias);

        // test Client
        $this->assertTrue($container->hasDefinition(sprintf('%s.test_api', $client)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.test_api_with_custom_class', $client)));

        // test logging services (logger, data collector and log middleware for each client)
        $clientLoggingStatuses = [
            'test_api' => true,
            'test_api_with_custom_class' => false,
            'test_api_with_custom_handler' => true
        ];
        foreach ($clientLoggingStatuses as $clientName => $expectedStatus) {
            $this->assertSame($expectedStatus, $container->hasDefinition(sprintf('%s.%s_logger', $this->alias, $clientName)));
            $this->assertSame($expectedStatus, $container->hasDefinition(sprintf('%s.middleware.log.%s', $this->alias, $clientName)));
            $this->assertSame($expectedStatus, $container->hasDefinition(sprintf('%s.middleware.request_time.%s', $this->alias, $clientName)));

            // test log middleware in handler of the client
            $this->assertCount($expectedStatus ? 1 : 0, $this->getClientLogMiddleware($container, sprintf('%s.%s', $client, $clientName)));
        }

        $this->assertTrue($container->hasDefinition(sprintf('%s.data_collector', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.formatter', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.symfony_log_formatter', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.twig_extension.debug', $this->alias)));
        $this->assertTrue($container->hasDefinition(sprintf('%s.middleware.symfony_log', $this->alias)));
    }

    public function testLoadWithoutLogging()
    {
        $config = $this->getConfigs();
        $config[0]['logging'] = false;

        $container = $this->createContainer();
        $this->createExtension()->load($config, $container);
        $client = sprintf('%s.client', $this->alias);

        // test logging services (logger, data collector and log middleware for each client)
        $clientLoggingStatuses = [
            'test_api' => false,
            'test_api_with_custom_class' => false,
            'test_api_with_custom_handler' => false
        ];
        foreach ($clientLoggingStatuses as $clientName => $expectedStatus) {
            $this->assertSame($expectedStatus, $container->hasDefinition(sprintf('%s.%s_logger', $this->alias, $clientName)));
            $this->assertSame($expectedStatus, $container->hasDefinition(sprintf('%s.middleware.log.%s', $this->alias, $clientName)));
            $this->assertSame($expectedStatus, $container->hasDefinition(sprintf('%s.middleware.request_time.%s', $this->alias, $clientName)));

            // test log middleware in handler of the client
            $this->assertCount($expectedStatus ? 1 : 0, $this->getClientLogMiddleware($container, sprintf('%s.%s', $client, $clientName)));
        }

        $this->assertFalse($container->hasDefinition(sprintf('%s.data_collector', $this->alias)));
        $this->assertFalse($container->hasDefinition(sprintf('%s.formatter', $this->alias)));
        $this->assertFalse($container->hasDefinition(sprintf('%s.symfony_log_formatter', $this->alias)));
        $this->assertFalse($container->hasDefinition(sprintf('%s.twig_extension.debug', $this->alias)));
        $this->assertFalse($container->hasDefinition(sprintf('%s.middleware.symfony_log', $this->alias)));
    }

    public function testGetConfiguration()
    {
        $configuration = $this->createExtension()->getConfiguration([], $this->createContainer());
        $this->assertInstanceOf(Configuration::class, $configuration);
    }

    public function testLoadWithOptions()
    {
        $config = [
            [
                'clients' => [
                    'test_api' => [
                        'base_url' => '//api.domain.tld/path',
                        'options' => [
                            'auth' => ['acme', 'pa55w0rd'],
                            'headers' => [
                                'Accept' => 'application/json',
                            ],
                            'timeout' => 30,
                        ],
                    ],
                ],
            ],
        ];
        $container = $this->createContainer();
        $this->createExtension()->load($config, $container);
        $client = sprintf('%s.client', $this->alias);
        $this->assertTrue($container->hasDefinition(sprintf('%s.test_api', $client)));
        $definition = $container->getDefinition(sprintf('%s.test_api', $client));
        $this->assertCount(1, $definition->getArguments());
        $expectedDefinitionFirstArgumentSubset = [
            'base_uri' => '//api.domain.tld/path',
            'auth' => ['acme', 'pa55w0rd'],
            'headers' => [
                'Accept' => 'application/json',
            ],
            'timeout' => 30.0,
        ];
        $actualDefinitionFirstArgumentSubset = $definition->getArgument(0);
        foreach ($expectedDefinitionFirstArgumentSubset as $expectedKey => $expectedValue) {
            $this->assertArrayHasKey($expectedKey, $actualDefinitionFirstArgumentSubset);
            $this->assertSame($expectedValue, $actualDefinitionFirstArgumentSubset[$expectedKey]);
        }
    }

    public function testCompilation()
    {
        $container = $this->createContainer();
        $this->createExtension()->load($this->getConfigs(), $container);
        $client = sprintf('%s.client', $this->alias);
        $container->compile();
        $this->assertInstanceOf(Client::class, $container->get(sprintf('%s.test_api', $client)));
        $this->assertInstanceOf(CustomClient::class, $container->get(sprintf('%s.test_api_with_custom_class', $client)));
        $this->assertInstanceOf(Client::class, $container->get(sprintf('%s.test_api_with_custom_handler', $client)));
    }

    private function createExtension(): ParadiseSecurityGuzzleExtension
    {
        return new ParadiseSecurityGuzzleExtension();
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set('logger', $this->createMock(LoggerInterface::class));
        $container->set('debug.stopwatch', $this->createMock(Stopwatch::class));

        return $container;
    }

    private function getConfigs(): array
    {
        return [
            [
                'clients' => [
                    'test_api' => [
                        'base_url' => '//api.domain.tld/path',
                        'plugin' => [],
                    ],
                    'test_api_with_custom_class' => [
                        'class' => CustomClient::class,
                    ],
                    'test_api_with_custom_handler' => [
                        'handler' => 'GuzzleHttp\Handler\MockHandler',
                    ],
                ],
                'logging' => true // Tests fail with logger off!
            ],
        ];
    }

    private function getClientLogMiddleware(ContainerBuilder $container, string $clientName): array
    {
        $this->assertCount(1, $container->getDefinition($clientName)->getArguments());
        $clientOptions = $container->getDefinition($clientName)->getArgument(0);
        $this->assertArrayHasKey('handler', $clientOptions);

        /** @var Definition $handler */
        $handler = $clientOptions['handler'];
        $this->assertInstanceOf(Definition::class, $handler);

        return array_filter($handler->getMethodCalls(), function (array $a) {
            return isset($a[1][1]) && $a[1][1] === 'log';
        });
    }
}

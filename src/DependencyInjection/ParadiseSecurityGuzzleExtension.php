<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use ParadiseSecurity\Bundle\GuzzleBundle\Log\Logger;
use ParadiseSecurity\Bundle\GuzzleBundle\MiddlewareTags;
use ParadiseSecurity\Bundle\GuzzleBundle\Twig\Extension\DebugExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

use function array_filter;
use function array_keys;
use function array_unique;
use function count;
use function implode;
use function is_array;
use function reset;
use function sprintf;

class ParadiseSecurityGuzzleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configPath = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Resources', 'config']);
        $loader = new XmlFileLoader($container, new FileLocator($configPath));

        $configuration = $this->getConfiguration([], $container);
        $config = $this->processConfiguration($configuration, $configs);

        $loader->load('services.xml');

        $logging = $config['logging'] === true;

        $this->processClientsConfiguration($config, $container);

        $clientsWithLogging = array_filter($config['clients'], function ($options) use ($logging) {
            return $options['logging'] !== false && $logging !== false;
        });

        if (count($clientsWithLogging) > 0) {
            $this->defineTwigDebugExtension($container);
            $this->defineDataCollector($container, $config['slow_response_time'] / 1000);
            $this->defineFormatter($container);
            $this->defineSymfonyLogFormatter($container);
            $this->defineSymfonyLogMiddleware($container);
        }
    }

    private function processClientsConfiguration(array $config, ContainerBuilder $container)
    {
        if (count($config['clients']) === 1) {
            $keys = array_keys($config['clients']);
            $config['clients'][reset($keys)]['default_client'] = true;
        }

        $logging = $config['logging'] === true;
        $profiling = $config['profiling'] === true;

        foreach ($config['clients'] as $name => $options) {
            $options['logging'] = $logging ? ($options['logging'] ?? true) : false;

            $argument = [
                'base_uri' => $options['base_url'],
                'handler' => $this->createHandler($container, $name, $options, $profiling)
            ];

            // if present, add default options to the constructor argument for the Guzzle client
            if (isset($options['options']) && is_array($options['options'])) {
                foreach ($options['options'] as $key => $value) {
                    if ($value === null || (is_array($value) && count($value) === 0)) {
                        continue;
                    }

                    $argument[$key] = $value;
                }
            }

            $attributes = [];

            if (!empty($options['middleware'])) {
                $attributes['middleware'] = implode(' ', array_unique($options['middleware']));
            }

            $attributes['alias'] = $name;

            if (isset($options['identifier'])) {
                $attributes['identifier'] = $options['identifier'];
            }
            if (isset($options['base_url'])) {
                $attributes['host'] = $options['base_url'];
            }

            $client = new Definition($options['class']);
            $client->addArgument($argument);
            $client->setPublic(true);
            $client->addTag(MiddlewareTags::CLIENT_TAG, $attributes);
            $client->setLazy($options['lazy']);

            // set service name based on client name
            $serviceName = sprintf('%s.client.%s', $this->getAlias(), $name);
            $container->setDefinition($serviceName, $client);

            if ('%paradise_security_guzzle.http_client.class%' !== $options['class']) {
                $container->registerAliasForArgument($serviceName, $options['class'], $name . 'Client');
            }
            $container->registerAliasForArgument($serviceName, ClientInterface::class, $name . 'Client');

            if ($options['default_client']) {
                $container->setAlias(ClientInterface::class, $serviceName);
                $container->setAlias(Client::class, $serviceName);
            }
        }
    }

    private function createHandler(ContainerBuilder $container, string $clientName, array $options, bool $profiling): Definition
    {
        // Event Dispatching service
        $eventServiceName = sprintf('paradise_security_guzzle.middleware.event_dispatch.%s', $clientName);
        $eventService = $this->createEventMiddleware($clientName);
        $container->setDefinition($eventServiceName, $eventService);

        // Create the event Dispatch Middleware
        $eventExpression = new Expression(sprintf("service('%s').dispatchEvent()", $eventServiceName));

        $handler = new Definition(HandlerStack::class);
        $handler->setFactory([HandlerStack::class, 'create']);
        if (isset($options['handler'])) {
            $handlerServiceName = sprintf('paradise_security_guzzle.handler.%s', $clientName);
            $handlerService = new Definition($options['handler']);
            $container->setDefinition($handlerServiceName, $handlerService);

            $handler->addArgument(new Reference($handlerServiceName));
        }
        $handler->setPublic(true);
        $handler->setLazy($options['lazy']);

        $handlerStackServiceName = sprintf('paradise_security_guzzle.handler_stack.%s', $clientName);
        $container->setDefinition($handlerStackServiceName, $handler);

        if ($profiling) {
            $this->defineProfileMiddleware($container, $handler, $clientName);
        }

        $logMode = $this->convertLogMode($options['logging']);
        if ($logMode > Logger::LOG_MODE_NONE) {
            $loggerName = $this->defineLogger($container, $logMode, $clientName);
            $this->defineLogMiddleware($container, $handler, $clientName, $loggerName);
            $this->defineRequestTimeMiddleware($container, $handler, $clientName, $loggerName);
            $this->attachSymfonyLogMiddlewareToHandler($handler);
        }

        // goes on the end of the stack.
        $handler->addMethodCall('unshift', [$eventExpression, 'events']);

        return $handler;
    }

    private function convertLogMode(int|bool $logMode): int
    {
        if ($logMode === true) {
            return Logger::LOG_MODE_REQUEST_AND_RESPONSE;
        } elseif ($logMode === false) {
            return Logger::LOG_MODE_NONE;
        } else {
            return $logMode;
        }
    }

    protected function defineTwigDebugExtension(ContainerBuilder $container): void
    {
        $twigDebugExtensionDefinition = new Definition(DebugExtension::class);
        $twigDebugExtensionDefinition->addTag('twig.extension');
        $twigDebugExtensionDefinition->setPublic(false);
        $container->setDefinition('paradise_security_guzzle.twig_extension.debug', $twigDebugExtensionDefinition);
    }

    /**
     * Define Logger
     */
    protected function defineLogger(ContainerBuilder $container, int $logMode, string $clientName): string
    {
        $loggerDefinition = new Definition('%paradise_security_guzzle.logger.class%');
        $loggerDefinition->setPublic(false);
        $loggerDefinition->setArgument(0, $logMode);
        $loggerDefinition->addTag('paradise_security_guzzle.logger');

        $loggerName = sprintf('paradise_security_guzzle.%s_logger', $clientName);
        $container->setDefinition($loggerName, $loggerDefinition);

        return $loggerName;
    }

    /**
     * Define Data Collector
     */
    protected function defineDataCollector(ContainerBuilder $container, float $slowResponseTime): void
    {
        $dataCollectorDefinition = new Definition('%paradise_security_guzzle.data_collector.class%');
        $dataCollectorDefinition->addArgument(array_map(function ($loggerId): Reference {
            return new Reference($loggerId);
        }, array_keys($container->findTaggedServiceIds('paradise_security_guzzle.logger'))));

        $dataCollectorDefinition->addArgument($slowResponseTime);
        $dataCollectorDefinition->setPublic(false);
        $dataCollectorDefinition->addTag('data_collector', [
            'id' => 'paradise_security_guzzle',
            'template' => '@ParadiseSecurityGuzzle/debug.html.twig',
        ]);
        $container->setDefinition('paradise_security_guzzle.data_collector', $dataCollectorDefinition);
    }

    /**
     * Define Formatter
     */
    protected function defineFormatter(ContainerBuilder $container): void
    {
        $formatterDefinition = new Definition('%paradise_security_guzzle.formatter.class%');
        $formatterDefinition->setPublic(true);
        $container->setDefinition('paradise_security_guzzle.formatter', $formatterDefinition);
    }

    /**
     * Define Request Time Middleware
     */
    protected function defineRequestTimeMiddleware(ContainerBuilder $container, Definition $handler, string $clientName, string $loggerName): void
    {
        $requestTimeMiddlewareDefinitionName = sprintf('paradise_security_guzzle.middleware.request_time.%s', $clientName);
        $requestTimeMiddlewareDefinition = new Definition('%paradise_security_guzzle.middleware.request_time.class%');
        $requestTimeMiddlewareDefinition->addArgument(new Reference($loggerName));
        $requestTimeMiddlewareDefinition->addArgument(new Reference('paradise_security_guzzle.data_collector'));
        $requestTimeMiddlewareDefinition->setPublic(true);
        $container->setDefinition($requestTimeMiddlewareDefinitionName, $requestTimeMiddlewareDefinition);

        $requestTimeExpression = new Expression(sprintf("service('%s')", $requestTimeMiddlewareDefinitionName));
        $handler->addMethodCall('after', ['log', $requestTimeExpression, 'request_time']);
    }

    /**
     * Define Log Middleware for client
     */
    protected function defineLogMiddleware(ContainerBuilder $container, Definition $handler, string $clientName, string $loggerName): void
    {
        $logMiddlewareDefinitionName = sprintf('paradise_security_guzzle.middleware.log.%s', $clientName);
        $logMiddlewareDefinition = new Definition('%paradise_security_guzzle.middleware.log.class%');
        $logMiddlewareDefinition->addArgument(new Reference($loggerName));
        $logMiddlewareDefinition->addArgument(new Reference('paradise_security_guzzle.formatter'));
        $logMiddlewareDefinition->setPublic(true);
        $container->setDefinition($logMiddlewareDefinitionName, $logMiddlewareDefinition);

        $logExpression = new Expression(sprintf("service('%s').log()", $logMiddlewareDefinitionName));
        $handler->addMethodCall('push', [$logExpression, 'log']);
    }

    /**
     * Define Profile Middleware for client
     */
    protected function defineProfileMiddleware(ContainerBuilder $container, Definition $handler, string $clientName): void
    {
        $profileMiddlewareDefinitionName = sprintf('paradise_security_guzzle.middleware.profile.%s', $clientName);
        $profileMiddlewareDefinition = new Definition('%paradise_security_guzzle.middleware.profile.class%');
        $profileMiddlewareDefinition->addArgument(new Reference('debug.stopwatch'));
        $profileMiddlewareDefinition->setPublic(true);
        $container->setDefinition($profileMiddlewareDefinitionName, $profileMiddlewareDefinition);

        $profileExpression = new Expression(sprintf("service('%s').profile()", $profileMiddlewareDefinitionName));
        $handler->addMethodCall('push', [$profileExpression, 'profile']);
    }

    protected function attachSymfonyLogMiddlewareToHandler(Definition $handler): void
    {
        $logExpression = new Expression(sprintf("service('%s')", 'paradise_security_guzzle.middleware.symfony_log'));
        $handler->addMethodCall('push', [$logExpression, 'symfony_log']);
    }

    /**
     * Create Middleware For dispatching events
     */
    protected function createEventMiddleware(string $name): Definition
    {
        $eventMiddleWare = new Definition('%paradise_security_guzzle.middleware.event_dispatcher.class%');
        $eventMiddleWare->addArgument(new Reference('event_dispatcher'));
        $eventMiddleWare->addArgument($name);
        $eventMiddleWare->setPublic(true);

        return $eventMiddleWare;
    }

    protected function defineSymfonyLogFormatter(ContainerBuilder $container): void
    {
        $formatterDefinition = new Definition('%paradise_security_guzzle.symfony_log_formatter.class%');
        $formatterDefinition->setArguments(['%paradise_security_guzzle.symfony_log_formatter.pattern%']);
        $formatterDefinition->setPublic(true);
        $container->setDefinition('paradise_security_guzzle.symfony_log_formatter', $formatterDefinition);
    }

    protected function defineSymfonyLogMiddleware(ContainerBuilder $container): void
    {
        $logMiddlewareDefinition = new Definition('%paradise_security_guzzle.middleware.symfony_log.class%');
        $logMiddlewareDefinition->addArgument(new Reference('logger'));
        $logMiddlewareDefinition->addArgument(new Reference('paradise_security_guzzle.symfony_log_formatter'));
        $logMiddlewareDefinition->setPublic(true);
        $logMiddlewareDefinition->addTag('monolog.logger', ['channel' => 'paradise_security_guzzle']);
        $container->setDefinition('paradise_security_guzzle.middleware.symfony_log', $logMiddlewareDefinition);
    }

    public function getAlias(): string
    {
        return 'paradise_security_guzzle';
    }
}

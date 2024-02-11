<?php

namespace ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\CompilerPass;

use GuzzleHttp\HandlerStack;
use ParadiseSecurity\Bundle\GuzzleBundle\MiddlewareTags;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class MiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $middleware = $this->findAvailableMiddleware($container);

        $this->registerMiddleware($container, $middleware);
    }

    private function findAvailableMiddleware(ContainerBuilder $container): array
    {
        $middleware = [];

        foreach ($container->findTaggedServiceIds(MiddlewareTags::MIDDLEWARE_TAG) as $id => $attributes) {
            $middleware = $middleware + $this->processAvailableMiddleware($id, $attributes);
        }

        if (empty($middleware)) {
            return [];
        }

        krsort($middleware);

        return call_user_func_array('array_merge', $middleware);
    }

    private function processAvailableMiddleware(string $id, array $attributes): array
    {
        $middleware = [];

        foreach ($attributes as $attribute) {
            if (!isset($attribute['alias'])) {
                throw new \InvalidArgumentException('Tagged middleware needs to have `alias` attributes.');
            }

            $alias = $attribute['alias'];
            $priority = isset($attribute['priority']) ? $attribute['priority'] : 0;

            $middleware[$priority][] = [
                'alias' => $alias,
                'id' => $id,
            ];
        }

        return $middleware;
    }

    /**
     * Sets up handlers and registers middleware for each tagged client.
     *
     * @param ContainerBuilder $container
     * @param array            $middlewareBag
     */
    private function registerMiddleware(ContainerBuilder $container, array $middlewareBag)
    {
        if (empty($middlewareBag)) {
            return;
        }

        $clients = $container->findTaggedServiceIds(MiddlewareTags::CLIENT_TAG);

        foreach ($clients as $clientId => $tags) {
            if (count($tags) > 1) {
                throw new \LogicException(sprintf('Clients should use a single \'%s\' tag', self::CLIENT_TAG));
            }

            $clientMiddleware = $this->filterClientMiddleware($middlewareBag, $tags);

            if (empty($clientMiddleware)) {
                continue;
            }

            $clientDefinition = $container->findDefinition($clientId);

            $arguments = $clientDefinition->getArguments();

            $options = [];

            if (!empty($arguments)) {
                $options = array_shift($arguments);
            }

            if (isset($options['handler'])) {
                $handlerStack = $this->wrapHandlerInHandlerStack($options['handler'], $container);

                $this->addMiddlewareToHandlerStack($handlerStack, $clientMiddleware);

                $options['handler'] = $handlerStack;
            }

            array_unshift($arguments, $options);
            $clientDefinition->setArguments($arguments);
        }
    }

    /**
     * @param Reference|Definition|callable $handler   The configured Guzzle handler
     * @param ContainerBuilder              $container The container builder
     *
     * @return Definition
     */
    private function wrapHandlerInHandlerStack($handler, ContainerBuilder $container)
    {
        if ($handler instanceof Reference) {
            $handler = $container->getDefinition((string) $handler);
        }

        if ($handler instanceof Definition && HandlerStack::class === $handler->getClass()) {
            // no need to wrap the Guzzle handler if it already resolves to a HandlerStack
            return $handler;
        }

        $handlerDefinition = new Definition(HandlerStack::class);
        $handlerDefinition->setArguments([$handler]);
        $handlerDefinition->setPublic(false);

        return $handlerDefinition;
    }

    private function addMiddlewareToHandlerStack(Definition $handlerStack, array $middlewareBag)
    {
        foreach ($middlewareBag as $middleware) {
            $handlerStack->addMethodCall('push', [new Reference($middleware['id']), $middleware['alias']]);
        }
    }

    /**
     * @param array $middlewareBag The list of availables middleware
     * @param array $tags          The tags containing middleware configuration
     *
     * @return array The list of middleware to enable for the client
     *
     * @throws LogicException When middleware configuration is invalid
     */
    private function filterClientMiddleware(array $middlewareBag, array $tags)
    {
        if (!isset($tags[0]['middleware'])) {
            return $middlewareBag;
        }

        $clientMiddlewareList = explode(' ', $tags[0]['middleware']);

        $whiteList = [];
        $blackList = [];
        foreach ($clientMiddlewareList as $middleware) {
            if ('!' === $middleware[0]) {
                $blackList[] = substr($middleware, 1);
            } else {
                $whiteList[] = $middleware;
            }
        }

        if ($whiteList && $blackList) {
            throw new LogicException('You cannot mix whitelisting and blacklisting of middleware at the same time.');
        }

        if ($whiteList) {
            return array_filter($middlewareBag, function ($value) use ($whiteList) {
                return in_array($value['alias'], $whiteList, true);
            });
        } else {
            return array_filter($middlewareBag, function ($value) use ($blackList) {
                return !in_array($value['alias'], $blackList, true);
            });
        }
    }
}

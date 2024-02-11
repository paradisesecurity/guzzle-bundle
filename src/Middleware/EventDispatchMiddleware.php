<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use ParadiseSecurity\Bundle\GuzzleBundle\Event\GuzzleEvent;
use ParadiseSecurity\Bundle\GuzzleBundle\Event\PostTransactionEvent;
use ParadiseSecurity\Bundle\GuzzleBundle\Event\PreTransactionEvent;
use ParadiseSecurity\Bundle\GuzzleBundle\GuzzleEvents;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Throwable;

/**
 * Dispatches an Event using the Symfony Event Dispatcher.
 * Dispatches a PRE_TRANSACTION event, before the transaction is sent
 * Dispatches a POST_TRANSACTION event, when the remote hosts responds.
 */
class EventDispatchMiddleware
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private string $serviceName
    ) {
    }

    public function dispatchEvent(): \Closure
    {
        return function (callable $handler) {
            return function (
                RequestInterface $request,
                array $options
            ) use ($handler) {
                // Create the Pre Transaction event.
                $preTransactionEvent = new PreTransactionEvent($this->serviceName, $request);

                // Dispatch it through the symfony Dispatcher.
                $this->doDispatch($preTransactionEvent, GuzzleEvents::PRE_TRANSACTION);
                $this->doDispatch(
                    $preTransactionEvent,
                    GuzzleEvent::preTransactionFor($this->serviceName)
                );

                // Continue the handler chain.
                $promise = $handler($preTransactionEvent->getTransaction(), $options);

                // Handle the response form the server.
                return $promise->then(
                    function (ResponseInterface $response) {
                        // Create the Post Transaction event.
                        $postTransactionEvent = new PostTransactionEvent(
                            $this->serviceName,
                            $response
                        );

                        // Dispatch the event on the symfony event dispatcher.
                        $this->doDispatch($postTransactionEvent, GuzzleEvents::POST_TRANSACTION);
                        $this->doDispatch(
                            $postTransactionEvent,
                            GuzzleEvent::postTransactionFor($this->serviceName)
                        );

                        // Continue down the chain.
                        return $postTransactionEvent->getTransaction();
                    },
                    function (Throwable $reason) {
                        // Get the response. The response in a RequestException can be null too.
                        $response = $reason instanceof RequestException ? $reason->getResponse() : null;

                        // Create the Post Transaction event.
                        $postTransactionEvent = new PostTransactionEvent(
                            $this->serviceName,
                            $response
                        );

                        // Dispatch the event on the symfony event dispatcher.
                        $this->doDispatch($postTransactionEvent, GuzzleEvents::POST_TRANSACTION);
                        $this->doDispatch(
                            $postTransactionEvent,
                            GuzzleEvent::postTransactionFor($this->serviceName)
                        );

                        // Continue down the chain.
                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }

    private function doDispatch(GuzzleEvent $event, string $name): void
    {
        if ($this->eventDispatcher instanceof SymfonyEventDispatcherContract) {
            $this->eventDispatcher->dispatch($event, $name);

            return;
        }

        // BC compatibility
        $this->eventDispatcher->dispatch($name, $event);
    }
}

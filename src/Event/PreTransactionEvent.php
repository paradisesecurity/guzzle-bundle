<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Event;

use Psr\Http\Message\RequestInterface;

class PreTransactionEvent extends GuzzleEvent
{
    public function __construct(
        string $serviceName,
        protected RequestInterface $requestTransaction
    ) {
        parent::__construct($serviceName);
    }

    /**
     * Access the transaction from the Guzzle HTTP request
     *
     * This returns the actual Request Object from the Guzzle HTTP Request.
     * This object will be modified by the event listener.
     */
    public function getTransaction(): RequestInterface
    {
        return $this->requestTransaction;
    }

    /**
     * Replaces the transaction with the modified one.
     *
     * Guzzle's transaction returns a modified request object.
     * Once it has been modified, attach it back to the event.
     */
    public function setTransaction(RequestInterface $requestTransaction): void
    {
        $this->requestTransaction = $requestTransaction;
    }
}

<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Event;

use Psr\Http\Message\ResponseInterface;

class PostTransactionEvent extends GuzzleEvent
{
    public function __construct(
        string $serviceName,
        protected ?ResponseInterface $response = null
    ) {
        parent::__construct($serviceName);
    }

    /**
     * Get the transaction from the event.
     *
     * This returns the transaction we are working with.
     */
    public function getTransaction(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Sets the transaction inline with the event.
     */
    public function setTransaction(?ResponseInterface $response): void
    {
        $this->response = $response;
    }
}

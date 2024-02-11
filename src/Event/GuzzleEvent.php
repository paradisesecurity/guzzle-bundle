<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Event;

use ParadiseSecurity\Bundle\GuzzleBundle\GuzzleEvents;
use Symfony\Contracts\EventDispatcher\Event;

use function sprintf;

class GuzzleEvent extends Event
{
    public function __construct(protected string $serviceName)
    {
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public static function preTransactionFor(string $serviceName): string
    {
        return sprintf('%s.%s', GuzzleEvents::PRE_TRANSACTION, $serviceName);
    }

    public static function postTransactionFor(string $serviceName): string
    {
        return sprintf('%s.%s', GuzzleEvents::POST_TRANSACTION, $serviceName);
    }
}

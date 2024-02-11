<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

use Psr\Log\LoggerTrait;

class DevNullLogger implements LoggerInterface
{
    use LoggerTrait;

    public function log($level, string|\Stringable $message, array $context = []): void
    {
    }

    public function clear(): void
    {
    }

    public function hasMessages(): bool
    {
        return false;
    }

    public function getMessages(): array
    {
        return [];
    }
}

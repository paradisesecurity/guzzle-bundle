<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

interface LoggerInterface extends PsrLoggerInterface
{
    public function clear(): void;

    public function hasMessages(): bool;

    public function getMessages(): array;
}

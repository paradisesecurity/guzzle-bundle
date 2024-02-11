<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

use Namshi\Cuzzle\Formatter\CurlFormatter;
use Psr\Log\LoggerTrait;

class Logger implements LoggerInterface
{
    use LoggerTrait;
    public const LOG_MODE_NONE = 0;
    public const LOG_MODE_REQUEST = 1;
    public const LOG_MODE_REQUEST_AND_RESPONSE_HEADERS = 2;
    public const LOG_MODE_REQUEST_AND_RESPONSE = 3;

    /** @var LogMessage[] */
    private array $messages = [];

    public function __construct(private int $logMode = self::LOG_MODE_REQUEST_AND_RESPONSE)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $requestId = isset($context['requestId']) ? $context['requestId'] : uniqid('paradise_security_guzzle_');

        if (array_key_exists($requestId, $this->messages)) {
            $logMessage = $this->messages[$requestId];
        } else {
            $logMessage = new LogMessage($message);
        }

        $logMessage->setLevel($level);

        if (!empty($context)) {
            if (!empty($context['request']) && $this->logMode > self::LOG_MODE_NONE) {
                $logMessage->setRequest(new LogRequest($context['request']));

                if (class_exists(CurlFormatter::class)) {
                    $logMessage
                        ->setCurlCommand((new CurlFormatter())
                        ->format($context['request']));
                }
            }

            if (!empty($context['response']) && $this->logMode > self::LOG_MODE_REQUEST) {
                $logMessage->setResponse(new LogResponse(
                    $context['response'],
                    $this->logMode > self::LOG_MODE_REQUEST_AND_RESPONSE_HEADERS
                ));
            }
        }

        $this->messages[$requestId] = $logMessage;
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    public function hasMessages(): bool
    {
        return $this->getMessages() ? true : false;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addTransferTimeByRequestId(?string $requestId, float $transferTime): void
    {
        if (array_key_exists($requestId, $this->messages)) {
            $this->messages[$requestId]->setTransferTime($transferTime);
        }
    }
}

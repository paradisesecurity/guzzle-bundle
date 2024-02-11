<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\DataCollector;

use ParadiseSecurity\Bundle\GuzzleBundle\Log\LogGroup;
use ParadiseSecurity\Bundle\GuzzleBundle\Log\LogMessage;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class HttpDataCollector extends DataCollector
{
    use DataCollectorSymfonyCompatibilityTrait;

    public function __construct(protected array $loggers, private float $slowResponseTime)
    {
        $this->reset();
    }

    protected function doCollect(Request $request, Response $response, \Throwable $exception = null)
    {
        $messages = [];
        foreach ($this->loggers as $logger) {
            $messages = array_merge($messages, $logger->getMessages());
        }

        if ($this->slowResponseTime > 0) {
            foreach ($messages as $message) {
                if (!$message instanceof LogMessage) {
                    continue;
                }

                if ($message->getTransferTime() >= $this->slowResponseTime) {
                    $this->data['hasSlowResponse'] = true;
                    break;
                }
            }
        }

        $requestId = $request->getUri();

        // clear log to have only messages related to Symfony request context
        foreach ($this->loggers as $logger) {
            $logger->clear();
        }

        $logGroup = $this->getLogGroup($requestId);
        $logGroup->setRequestName($request->getPathInfo());
        $logGroup->addMessages($messages);
    }

    public function getName(): string
    {
        return 'paradise_security_guzzle';
    }

    public function reset(): void
    {
        $this->data = [
            'logs' => [],
            'callCount' => 0,
            'totalTime' => 0,
            'hasSlowResponse' => false,
        ];
    }

    public function getLogs(): array
    {
        return array_key_exists('logs', $this->data) ? $this->data['logs'] : [];
    }

    public function getMessages(): array
    {
        $messages = [];

        foreach ($this->getLogs() as $log) {
            foreach ($log->getMessages() as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    public function getCallCount(): int
    {
        return count($this->getMessages());
    }

    public function getErrorCount(): int
    {
        return count($this->getErrorsByType(LogLevel::ERROR));
    }

    public function getErrorsByType(string $type): array
    {
        return array_filter(
            $this->getMessages(),
            function (LogMessage $message) use ($type) {
                return $message->getLevel() === $type;
            }
        );
    }

    public function getTotalTime(): float
    {
        return $this->data['totalTime'];
    }

    public function hasSlowResponses(): bool
    {
        return $this->data['hasSlowResponse'];
    }

    public function addTotalTime(float $time): void
    {
        $this->data['totalTime'] += $time;
    }

    protected function getLogGroup(string $id): LogGroup
    {
        if (!isset($this->data['logs'][$id])) {
            $this->data['logs'][$id] = new LogGroup();
        }

        return $this->data['logs'][$id];
    }
}

<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Middleware;

use GuzzleHttp\TransferStats;
use ParadiseSecurity\Bundle\GuzzleBundle\DataCollector\HttpDataCollector;
use ParadiseSecurity\Bundle\GuzzleBundle\Log\Logger;
use ParadiseSecurity\Bundle\GuzzleBundle\Log\LoggerInterface;
use Psr\Http\Message\RequestInterface;

use function call_user_func;
use function is_callable;

class RequestTimeMiddleware
{
    public function __construct(
        protected LoggerInterface $logger,
        private HttpDataCollector $dataCollector
    ) {
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $options['on_stats'] = $this->getOnStatsCallback(
                isset($options['on_stats']) ? $options['on_stats'] : null,
                isset($options['request_id']) ? $options['request_id'] : null
            );

            // Continue the handler chain.
            return $handler($request, $options);
        };
    }

    /**
     * Create callback for on_stats options.
     * If request has on_stats option, it will be called inside of this callback.
     */
    protected function getOnStatsCallback(?callable $initialOnStats, ?string $requestId): \Closure
    {
        return function (TransferStats $stats) use ($initialOnStats, $requestId) {
            if (is_callable($initialOnStats)) {
                call_user_func($initialOnStats, $stats);
            }

            $this->dataCollector->addTotalTime((float) $stats->getTransferTime());

            if (($this->logger instanceof Logger) && $requestId) {
                $this->logger->addTransferTimeByRequestId($requestId, (float) $stats->getTransferTime());
            }
        };
    }
}

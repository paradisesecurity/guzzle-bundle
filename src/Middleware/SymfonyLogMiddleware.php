<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Promise\Create;
use Psr\Log\LoggerInterface;

class SymfonyLogMiddleware
{
    public function __construct(
        protected LoggerInterface $logger,
        protected MessageFormatter $formatter
    ) {
    }

    public function __invoke(callable $handler): \Closure
    {
        $logger = $this->logger;
        $formatter = $this->formatter;

        return function ($request, array $options) use ($handler, $logger, $formatter) {
            return $handler($request, $options)->then(
                function ($response) use ($logger, $request, $formatter) {
                    $message = $formatter->format($request, $response);

                    $logger->info($message);

                    return $response;
                },
                function ($reason) use ($logger, $request, $formatter) {
                    $response = $reason instanceof RequestException ? $reason->getResponse() : null;
                    $message  = $formatter->format($request, $response, $reason);

                    $logger->notice($message);

                    return Create::rejectionFor($reason);
                }
            );
        };
    }
}

<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Middleware;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Promise\Create;
use ParadiseSecurity\Bundle\GuzzleBundle\Log\LoggerInterface;

use function compact;
use function uniqid;

class LogMiddleware
{
    public function __construct(
        protected LoggerInterface $logger,
        protected MessageFormatter $formatter
    ) {
    }

    public function log(): \Closure
    {
        $logger = $this->logger;
        $formatter = $this->formatter;

        return function (callable $handler) use ($logger, $formatter) {
            return function ($request, array $options) use ($handler, $logger, $formatter) {
                // generate id that will be used to supplement the log with information
                $requestId = uniqid('paradise_security_guzzle_');

                // initial registration of log
                $logger->info('', compact('request', 'requestId'));

                // this id will be used by RequestTimeMiddleware
                $options['request_id'] = $requestId;

                return $handler($request, $options)->then(
                    function ($response) use ($logger, $request, $formatter, $requestId) {
                        $message = $formatter->format($request, $response);
                        $context = compact('request', 'response', 'requestId');

                        $logger->info($message, $context);

                        return $response;
                    },
                    function ($reason) use ($logger, $request, $formatter, $requestId) {
                        $response = $reason instanceof RequestException ? $reason->getResponse() : null;
                        $message = $formatter->format($request, $response, $reason);
                        $context = compact('request', 'response', 'requestId');

                        $logger->notice($message, $context);

                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }
}

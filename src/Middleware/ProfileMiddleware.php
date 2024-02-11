<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Middleware;

use GuzzleHttp\Promise\Create;
use Symfony\Component\Stopwatch\Stopwatch;

use function sprintf;

/**
 * This Middleware is used to render request time slot on "Performance" tab in "Symfony Profiler".
 */
class ProfileMiddleware
{
    public function __construct(private Stopwatch $stopwatch)
    {
    }

    public function profile(): \Closure
    {
        $stopwatch = $this->stopwatch;

        return function (callable $handler) use ($stopwatch) {
            return function ($request, array $options) use ($handler, $stopwatch) {
                $event = $stopwatch->start(
                    sprintf('%s %s', $request->getMethod(), $request->getUri()),
                    'paradise_security_guzzle'
                );

                return $handler($request, $options)->then(
                    function ($response) use ($event) {
                        $event->stop();

                        return $response;
                    },
                    function ($reason) use ($event) {
                        $event->stop();

                        return Create::rejectionFor($reason);
                    }
                );
            };
        };
    }
}

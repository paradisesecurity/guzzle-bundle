<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @TODO remove this trait with dropping of support Symfony < 4.4
 */
trait DataCollectorSymfonyCompatibilityTrait
{
    abstract protected function doCollect(Request $request, Response $response, \Throwable $exception = null);

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->doCollect($request, $response, $exception);
    }
}

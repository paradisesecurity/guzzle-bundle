<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle;

use ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\CompilerPass\LoaderPass;
use ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\CompilerPass\MiddlewarePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ParadiseSecurityGuzzleBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new MiddlewarePass());
        $container->addCompilerPass(new LoaderPass());
    }
}

<?php

namespace ParadiseSecurity\Bundle\GuzzleBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $ids = $container->findTaggedServiceIds('csa_guzzle.description_loader');

        if (!count($ids)) {
            return;
        }

        $resolverDefinition = $container->findDefinition('csa_guzzle.description_loader.resolver');

        $loaders = [];

        foreach ($ids as $id => $options) {
            $loaders[] = new Reference($id);
        }

        $resolverDefinition->setArguments([$loaders]);
    }
}

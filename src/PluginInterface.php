<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

interface PluginInterface
{
    public function getPluginName(): string;

    public function addConfiguration(ArrayNodeDefinition $pluginNode): void;

    public function load(array $configs, ContainerBuilder $container): void;

    public function loadForClient(array $config, ContainerBuilder $container, string $clientName, Definition $handler): void;

    public function build(ContainerBuilder $container);

    public function boot();
}

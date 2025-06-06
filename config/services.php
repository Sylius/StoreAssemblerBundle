<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\DXBundle\Command\Plugin\PluginPrepare;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services
        ->set('sylius_dx.command.plugin_prepare', PluginPrepare::class)
        ->tag('console.command')
    ;
};

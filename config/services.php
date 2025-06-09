<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\DXBundle\Command\Fixture\FixtureLoadCommand;
use Sylius\DXBundle\Command\Fixture\FixturePrepareCommand;
use Sylius\DXBundle\Command\Plugin\PluginInstallCommand;
use Sylius\DXBundle\Command\Plugin\PluginPrepare;
use Sylius\DXBundle\Command\ThemePrepareCommand;
use Sylius\DXBundle\Configurator\YamlNodeConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services
        ->set('sylius_dx.command.plugin_prepare', PluginPrepare::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_dx.command.plugin_install', PluginInstallCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_dx.command.fixture_prepare', FixturePrepareCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_dx.command.fixture_load', FixtureLoadCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_dx.command.theme_prepare', ThemePrepareCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_dx.configurator.yaml_node', YamlNodeConfigurator::class)
    ;
};

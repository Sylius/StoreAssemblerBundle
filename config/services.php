<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\StoreAssemblerBundle\Command\FixtureLoadCommand;
use Sylius\StoreAssemblerBundle\Command\FixturePrepareCommand;
use Sylius\StoreAssemblerBundle\Command\PluginInstallCommand;
use Sylius\StoreAssemblerBundle\Command\PluginPrepareCommand;
use Sylius\StoreAssemblerBundle\Command\ThemePrepareCommand;
use Sylius\StoreAssemblerBundle\Configurator\YamlNodeConfigurator;
use Sylius\StoreAssemblerBundle\Controller\StoreAssemblerController;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->set('sylius_store_assembler.command.plugin_prepare', PluginPrepareCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_store_assembler.command.plugin_install', PluginInstallCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_store_assembler.command.fixture_prepare', FixturePrepareCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_store_assembler.command.fixture_load', FixtureLoadCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_store_assembler.command.theme_prepare', ThemePrepareCommand::class)
        ->args([
            '%kernel.project_dir%',
        ])
        ->tag('console.command')
    ;

    $services
        ->set('sylius_store_assembler.configurator.yaml_node', YamlNodeConfigurator::class)
    ;

    $services
        ->set('sylius_store_assembler.controller', StoreAssemblerController::class)
        ->tag('controller.service_arguments')
    ;
};

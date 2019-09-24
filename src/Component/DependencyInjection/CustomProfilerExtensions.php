<?php

namespace Frosh\DevelopmentHelper\Component\DependencyInjection;

use Frosh\DevelopmentHelper\Component\Profiler\TwigDataCollector;
use Frosh\DevelopmentHelper\Component\Profiler\TwigDecorator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomProfilerExtensions implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('data_collector.twig')) {
            $definition = $container->getDefinition('data_collector.twig');
            $definition->setClass(TwigDataCollector::class);

            $parameter = $container->getParameter('data_collector.templates');
            $parameter['data_collector.twig'][1] = '@FroshDevelopmentHelper/Collector/twig.html.twig';
            $container->setParameter('data_collector.templates', $parameter);
        }

        if ($container->hasDefinition('twig')) {
            $container->getDefinition('twig')->setClass(TwigDecorator::class);
        }
    }
}

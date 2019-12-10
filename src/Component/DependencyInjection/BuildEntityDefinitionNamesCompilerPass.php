<?php

namespace Frosh\DevelopmentHelper\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BuildEntityDefinitionNamesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $names = [];

        foreach ($container->findTaggedServiceIds('shopware.entity.definition') as $id => $options) {
            if ($container->hasAlias($id)) {
                continue;
            }

            $names[] = $container->getDefinition($id)->getClass();
        }

        $container->setParameter('frosh_development_helper.names', array_unique($names));
    }
}

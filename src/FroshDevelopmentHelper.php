<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper;

use Frosh\DevelopmentHelper\Component\DependencyInjection\CustomProfilerExtensions;
use Frosh\DevelopmentHelper\Component\DependencyInjection\DisableTwigCacheCompilerPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FroshDevelopmentHelper extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DisableTwigCacheCompilerPass());
        $container->addCompilerPass(new CustomProfilerExtensions());
        parent::build($container);
    }
}

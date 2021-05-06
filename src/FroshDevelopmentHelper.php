<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper;

use Composer\Autoload\ClassLoader;
use Frosh\DevelopmentHelper\Component\DependencyInjection\BuildEntityDefinitionNamesCompilerPass;
use Frosh\DevelopmentHelper\Component\DependencyInjection\CustomProfilerExtensions;
use Frosh\DevelopmentHelper\Component\DependencyInjection\DisableTwigCacheCompilerPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/** @var ClassLoader $c */
global $classLoader;
isset($classLoader) && $classLoader->addClassMap(['Doctrine\DBAL\Schema\AbstractAsset' => __DIR__ .'/Patches/Doctrine/AbstractAsset.php']);

class FroshDevelopmentHelper extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DisableTwigCacheCompilerPass());
        $container->addCompilerPass(new CustomProfilerExtensions());
        $container->addCompilerPass(new BuildEntityDefinitionNamesCompilerPass());
        parent::build($container);
    }
}

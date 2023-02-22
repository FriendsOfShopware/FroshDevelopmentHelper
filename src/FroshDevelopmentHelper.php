<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper;

use Frosh\DevelopmentHelper\Component\DependencyInjection\BuildEntityDefinitionNamesCompilerPass;
use Frosh\DevelopmentHelper\Component\DependencyInjection\CustomProfilerExtensions;
use Frosh\DevelopmentHelper\Component\DependencyInjection\DisableTwigCacheCompilerPass;
use Frosh\DevelopmentHelper\Component\DependencyInjection\FroshDevelopmentHelperExtension;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FroshDevelopmentHelper extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DisableTwigCacheCompilerPass());
        $container->addCompilerPass(new CustomProfilerExtensions());
        $container->addCompilerPass(new BuildEntityDefinitionNamesCompilerPass());

        $this->buildConfig($container);

        parent::build($container);
    }

    public function createContainerExtension(): FroshDevelopmentHelperExtension
    {
        return new FroshDevelopmentHelperExtension();
    }

    private function buildConfig(ContainerBuilder $container): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
    }

    public function executeComposerCommands(): bool
    {
        return true;
    }
}

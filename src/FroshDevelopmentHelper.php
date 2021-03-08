<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper;

use Frosh\DevelopmentHelper\Component\DependencyInjection\BuildEntityDefinitionNamesCompilerPass;
use Frosh\DevelopmentHelper\Component\DependencyInjection\CustomProfilerExtensions;
use Frosh\DevelopmentHelper\Component\DependencyInjection\DisableTwigCacheCompilerPass;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class FroshDevelopmentHelper extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DisableTwigCacheCompilerPass());
        $container->addCompilerPass(new CustomProfilerExtensions());
        $container->addCompilerPass(new BuildEntityDefinitionNamesCompilerPass());
        parent::build($container);
    }

    public function install(InstallContext $installContext): void
    {
        $this->installDependencies();
        parent::install($installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->installDependencies();
        parent::update($updateContext);
    }

    private function installDependencies()
    {
        require __DIR__ . '/../vendor-builder.php';
    }
}

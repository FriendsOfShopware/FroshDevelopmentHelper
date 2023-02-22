<?php

namespace Frosh\DevelopmentHelper\Component\Generator;

use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use PhpCsFixer\Config;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Runner;
use PhpCsFixer\ToolInfo;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;

class FixCodeStyle
{
    public function fix(Definition $loaderResult): void
    {
        $config = new Config();
        $config->setRules([
            '@PSR2' => true,
            '@Symfony' => true,
            'declare_strict_types' => true,
            'fully_qualified_strict_types' => true,
            'method_argument_space' => [
                'on_multiline' => 'ensure_fully_multiline'
            ],
            'ordered_class_elements' => true,
        ]);
        $config->setRiskyAllowed(true);

        $resolver = new ConfigurationResolver(
            $config,
            [
                'dry-run' => false,
                'stop-on-violation' => false,
            ],
            getcwd(),
            new ToolInfo()
        );

        $finder = new Finder();
        $finder = $finder->in($loaderResult->folder)
            ->name('*' . $loaderResult->name . '*')
            ->depth(0)
            ->files();

        $runner = new Runner(
            $finder,
            $resolver->getFixers(),
            $resolver->getDiffer(),
            new EventDispatcher(),
            new ErrorsManager(),
            $resolver->getLinter(),
            $resolver->isDryRun(),
            $resolver->getCacheManager(),
            $resolver->getDirectory(),
            $resolver->shouldStopOnViolation()
        );

        $runner->fix();
    }
}

<?php

namespace Frosh\DevelopmentHelper\Component\Generator;

use Frosh\DevelopmentHelper\Component\Generator\Definition\LoaderResult;
use PhpCsFixer\Config;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Error\ErrorsManager;
use PhpCsFixer\Runner\Runner;
use PhpCsFixer\ToolInfo;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;

class FixCodeStyle
{
    public function fix(LoaderResult $loaderResult): void
    {
        $config = new Config();
        $config->setRules([
            '@PSR2' => true,
            '@Symfony' => true,
            'fully_qualified_strict_types' => true,
            'method_argument_space' => [
                'on_multiline' => 'ensure_fully_multiline'
            ]
        ]);

        $resolver = new ConfigurationResolver(
            $config,
            [],
            getcwd(),
            new ToolInfo()
        );

        $finder = new Finder();
        $finder = $finder->in($loaderResult->folder)
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

        $foo = $runner->fix();
    }
}

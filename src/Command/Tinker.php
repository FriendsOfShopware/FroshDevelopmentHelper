<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Command;

use Psy\Configuration;
use Psy\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Tinker extends Command
{
    protected static $defaultName = 'tinker';

    protected function configure()
    {
        $this->addArgument('include', InputArgument::IS_ARRAY, 'Include file(s) before starting tinker');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getApplication()->setCatchExceptions(false);

        $config = new Configuration([
            'updateCheck' => 'never',
        ]);

        $shell = new Shell($config);
        $shell->setIncludes($input->getArgument('include'));

        return $shell->run();
    }

}

<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Psy\Configuration;
use Psy\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('tinker', description: 'Starts a tinker session')]
class Tinker extends Command
{
    protected function configure(): void
    {
        $this->addArgument('include', InputArgument::IS_ARRAY, 'Include file(s) before starting tinker');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

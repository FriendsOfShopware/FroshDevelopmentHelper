<?php

namespace Frosh\DevelopmentHelper\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Frosh\DevelopmentHelper\Component\Generator\Definition\CollectionGenerator;
use Frosh\DevelopmentHelper\Component\Generator\Definition\EntityConsoleQuestion;
use Frosh\DevelopmentHelper\Component\Generator\Definition\DefinitionGenerator;
use Frosh\DevelopmentHelper\Component\Generator\Definition\EntityGenerator;
use Frosh\DevelopmentHelper\Component\Generator\Definition\EntityLoader;
use Frosh\DevelopmentHelper\Component\Generator\FixCodeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('frosh:make:definition', description: 'Generates an entity')]
class MakeDefinition extends Command
{
    public function __construct(
        private readonly EntityLoader $entityLoader,
        private readonly EntityConsoleQuestion $entityConsoleQuestion,
        private readonly DefinitionGenerator $definitionGenerator,
        private readonly EntityGenerator $entityGenerator,
        private readonly CollectionGenerator $collectionGenerator,
        private readonly FixCodeStyle $fixCodeStyle
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace (FroshTest\\Content\\Store)');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->entityLoader->load($input->getArgument('namespace'), $io);

        $this->entityConsoleQuestion->question($io, $result);

        $this->definitionGenerator->generate($result);
        $this->entityGenerator->generate($result);
        $this->collectionGenerator->generate($result);
        $this->fixCodeStyle->fix($result);

        if ($result->translation) {
            $this->definitionGenerator->generate($result->translation);
            $this->entityGenerator->generate($result->translation);
            $this->collectionGenerator->generate($result->translation);
            $this->fixCodeStyle->fix($result->translation);
        }

        $io->warning('Don\'t forget to add this Definition to your services.xml');

        return 0;
    }
}

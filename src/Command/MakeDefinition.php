<?php

namespace Frosh\DevelopmentHelper\Command;

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

class MakeDefinition extends Command
{
    public static $defaultName = 'frosh:make:definition';

    /**
     * @var EntityLoader
     */
    private $entityLoader;

    /**
     * @var EntityConsoleQuestion
     */
    private $entityConsoleQuestion;

    /**
     * @var DefinitionGenerator
     */
    private $definitionGenerator;

    /**
     * @var EntityGenerator
     */
    private $entityGenerator;

    /**
     * @var CollectionGenerator
     */
    private $collectionGenerator;

    /**
     * @var FixCodeStyle
     */
    private $fixCodeStyle;

    public function __construct(
        EntityLoader $entityLoader,
        EntityConsoleQuestion $entityConsoleQuestion,
        DefinitionGenerator $definitionGenerator,
        EntityGenerator $entityGenerator,
        CollectionGenerator $collectionGenerator,
        FixCodeStyle $fixCodeStyle
    ) {
        parent::__construct();
        $this->entityLoader = $entityLoader;
        $this->entityConsoleQuestion = $entityConsoleQuestion;
        $this->definitionGenerator = $definitionGenerator;
        $this->entityGenerator = $entityGenerator;
        $this->collectionGenerator = $collectionGenerator;
        $this->fixCodeStyle = $fixCodeStyle;
    }

    protected function configure(): void
    {
        $this->setDescription('Generates an entity')
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace (FroshTest\\Content\\Store)');
    }

    public function execute(InputInterface $input, OutputInterface $output)
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
    }
}

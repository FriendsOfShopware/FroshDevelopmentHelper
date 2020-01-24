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
            ->addArgument('plugin', InputArgument::REQUIRED, 'Plugin')
            ->addArgument('entityName', InputArgument::REQUIRED, 'EntityName');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $result = $this->entityLoader->load($input->getArgument('plugin'), $input->getArgument('entityName'));

        $result->fields = $this->entityConsoleQuestion->question($input, $output, $result->fields);

        $this->definitionGenerator->generate($result);
        $this->entityGenerator->generate($result);
        $this->collectionGenerator->generate($result);
        $this->fixCodeStyle->fix($result);
    }
}

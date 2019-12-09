<?php

namespace Frosh\DevelopmentHelper\Command;

use Frosh\DevelopmentHelper\Component\Generator\Entity\EntityConsoleQuestion;
use Frosh\DevelopmentHelper\Component\Generator\Entity\EntityGenerator;
use Frosh\DevelopmentHelper\Component\Generator\Entity\EntityLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeEntityCommand extends Command
{
    public static $defaultName = 'frosh:generate:entity';

    /**
     * @var EntityLoader
     */
    private $entityLoader;

    /**
     * @var EntityConsoleQuestion
     */
    private $entityConsoleQuestion;

    /**
     * @var EntityGenerator
     */
    private $entityGenerator;

    public function __construct(EntityLoader $entityLoader, EntityConsoleQuestion $entityConsoleQuestion, EntityGenerator $entityGenerator)
    {
        parent::__construct();
        $this->entityLoader = $entityLoader;
        $this->entityConsoleQuestion = $entityConsoleQuestion;
        $this->entityGenerator = $entityGenerator;
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

        $this->entityGenerator->generate($result);
    }


}

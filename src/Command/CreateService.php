<?php

namespace Frosh\DevelopmentHelper\Command;

use Frosh\DevelopmentHelper\Component\Twig\BlockCollector;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class CreateService extends Command
{
    public static $defaultName = 'frosh:create:service';
    private BlockCollector $blockCollector;
    private array $pluginInfos;
    private CacheClearer $cacheClearer;

    public function __construct(BlockCollector $blockCollector, array $pluginInfos, CacheClearer $cacheClearer)
    {
        parent::__construct();
        $this->blockCollector = $blockCollector;
        $this->pluginInfos = $pluginInfos;
        $this->cacheClearer = $cacheClearer;
    }

    protected function configure()
    {
        $this
            ->setDescription('Generates a service for you')
            ->addArgument('pluginName', InputArgument::REQUIRED, 'Plugin Name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginPath = $this->determinePluginPath($input->getArgument('pluginName'));

        $io = new SymfonyStyle($input, $output);
        $question = new Question('Service Name');
        $question->setAutocompleterValues(array($input->getArgument('pluginName')));
        $chosenServiceName = $io->askQuestion($question);

        if ($chosenServiceName === null) {
            throw new \RuntimeException('Service Name is required');
        }

        $fs = new Filesystem();

        $serviceFolderPath = $pluginPath . '/Service/';
        $servicePath = $serviceFolderPath . $chosenServiceName . '.php';

        if (!file_exists($serviceFolderPath)) {
            $fs->mkdir($serviceFolderPath);
        }

        if (!file_exists(dirname($servicePath))) {
            $fs->mkdir(dirname($servicePath));
        }
        if (!file_exists($servicePath)) {
            $tpl = <<<TPL
<?php declare(strict_types=1);

namespace ###PLUGINNAME###\\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ###SERVICENAME### implements EventSubscriberInterface
{

    public function __construct(){}


    public static function getSubscribedEvents(): array
    {
        return [
            EVENTNAME::class => 'eventFunction',
        ];
    }
    public static function eventFunction(): array
    {
        return array();
    }


}

TPL;
            $content = str_replace(
                [
                    '###PLUGINNAME###',
                    '###SERVICENAME###'
                ],
                [
                    $input->getArgument('pluginName'),
                    $chosenServiceName
                ],
                $tpl
            );

            $fs->dumpFile($servicePath, $content);

            $io->success(sprintf('Created file at "%s"', $servicePath));
        } 

        $this->cacheClearer->clear();
        $io->info('Cleared cache');


        return 0;
    }

    private function determinePluginPath(string $name): string
    {
        foreach ($this->pluginInfos as $pluginInfo) {
            if ($pluginInfo['name'] !== $name) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($pluginInfo['baseClass']);

            return dirname($reflectionClass->getFileName());
        }

        throw new \RuntimeException(sprintf('Cannot find plugin by name "%s"', $name));
    }
}

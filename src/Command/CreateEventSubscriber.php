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

class CreateEventSubscriber extends Command
{
    public static $defaultName = 'frosh:create:eventSubscriber';
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
            ->setDescription('Generates a event subscriber for you')
            ->addArgument('pluginName', InputArgument::REQUIRED, 'Plugin Name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginPath = $this->determinePluginPath($input->getArgument('pluginName'));

        $io = new SymfonyStyle($input, $output);
        $question = new Question('Event subscriber name');
        $question->setAutocompleterValues(array($input->getArgument('pluginName')));
        $chosenEventSubscriberName = $io->askQuestion($question);

        if ($chosenEventSubscriberName === null) {
            throw new \RuntimeException('Event subscriber name is required');
        }

        $fs = new Filesystem();

        $subscriberFolderPath = $pluginPath . '/Service/';
        $subscriberPath = $subscriberFolderPath . $chosenEventSubscriberName . '.php';

        if (!file_exists($subscriberFolderPath)) {
            $fs->mkdir($subscriberFolderPath);
        }

        if (!file_exists(dirname($subscriberPath))) {
            $fs->mkdir(dirname($subscriberPath));
        }
        if (!file_exists($subscriberPath)) {
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
                    $chosenEventSubscriberName
                ],
                $tpl
            );

            $fs->dumpFile($subscriberPath, $content);

            $io->success(sprintf('Created file at "%s"', $subscriberPath));
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

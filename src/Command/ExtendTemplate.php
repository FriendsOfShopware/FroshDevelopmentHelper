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

class ExtendTemplate extends Command
{
    public static $defaultName = 'frosh:extend:template';
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
            ->setDescription('Generates the template extension for you')
            ->addArgument('pluginName', InputArgument::REQUIRED, 'Plugin Name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pluginPath = $this->determinePluginPath($input->getArgument('pluginName'));
        $blocks = $this->blockCollector->getBlocks();

        $io = new SymfonyStyle($input, $output);
        $question = new Question('Block');
        $question->setAutocompleterValues(array_keys($blocks));

        $chosenBlock = $io->askQuestion($question);

        if ($chosenBlock === null || !isset($blocks[$chosenBlock])) {
            throw new \RuntimeException('Block is wrong');
        }

        if (count($blocks[$chosenBlock]) > 1) {
            $question = new ChoiceQuestion('Which template file should be used', array_keys($blocks[$chosenBlock]));
            $chosenFile = $io->askQuestion($question);

            if ($chosenFile === null) {
                throw new \RuntimeException('Invalid chosen option');
            }
        } else {
            $chosenFile = array_keys($blocks[$chosenBlock])[0];
        }

        $fs = new Filesystem();

        $templateFolderPath = $pluginPath . '/Resources/views/';
        $templatePath = $templateFolderPath . $chosenFile;

        if (!file_exists($templateFolderPath)) {
            $fs->mkdir($templateFolderPath);
        }

        if (!file_exists(dirname($templatePath))) {
            $fs->mkdir(dirname($templatePath));
        }

        if (!file_exists($templatePath)) {
            $tpl = <<<TPL
{% sw_extends "@Storefront/###PATH###" %}

{% block ###BLOCK### %}
    {{ parent() }}
{% endblock %}

TPL;
            $content = str_replace(
                [
                    '###PATH###',
                    '###BLOCK###'
                ],
                [
                    $chosenFile,
                    $chosenBlock
                ],
                $tpl
            );

            $fs->dumpFile($templatePath, $content);

            $io->success(sprintf('Created file at "%s"', $templatePath));
        } else {
            $tpl = <<<TPL
{% block ###BLOCK### %}
    {{ parent() }}
{% endblock %}
TPL;


            $fs->appendToFile($templatePath, str_replace('###BLOCK###', $chosenBlock, $tpl));

            $io->success(sprintf('Updated file at "%s"', $templatePath));
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

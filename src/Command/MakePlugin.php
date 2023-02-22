<?php

namespace Frosh\DevelopmentHelper\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand('frosh:make:plugin', description: 'Generates a plugin')]
class MakePlugin extends Command
{
    private readonly string $pluginFolderDir;

    public function __construct(string $kernelRootDir)
    {
        parent::__construct();
        $this->pluginFolderDir = $kernelRootDir . '/custom/plugins/';
    }

    public function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Plugin Name')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Start namespace of the plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = new Filesystem();

        $pluginPath = $this->pluginFolderDir . '/' . $input->getArgument('name');
        if ($fs->exists($pluginPath)) {
            throw new \RuntimeException(sprintf('Plugin with name "%s" already exists', $input->getArgument('name')));
        }

        $namespace = $input->getOption('namespace') ?? $input->getArgument('name');

        $fs->mkdir([
            $pluginPath,
            $pluginPath . '/src/',
            $pluginPath . '/src/Resources',
            $pluginPath . '/src/Resources/config',
        ]);

        $io = new SymfonyStyle($input, $output);

        $this->makeComposerJson($fs, $pluginPath, $input->getArgument('name'), $namespace, $io);
        $this->makeBootstrap($fs, $pluginPath, $input->getArgument('name'), $namespace);
        $this->makeChangelogFiles($fs, $pluginPath);
        $this->makeDefaultServicesXml($fs, $pluginPath);

        $io->warning('To pass the Extension Store guidelines you need to provide an plugin.png in src/Resources/config/plugin.png');

        return 0;
    }

    private function makeComposerJson(Filesystem $fs, string $pluginPath, string $pluginName, string $namespace, SymfonyStyle $io): void
    {
        $composerJson = [
            'name' => $io->ask('Composer Package name (vendor/package-name)', 'acme/example'),
            'version' => '1.0.0',
            'description' => $io->ask('Package description', ''),
            'type' => 'shopware-platform-plugin',
            'license' => $io->ask('Package license', 'MIT'),
            'autoload' => [
                'psr-4' => [
                    $namespace . '\\' => 'src/'
                ]
            ],
            'extra' => [
                'shopware-plugin-class' => $namespace . '\\' . $pluginName,
                'label' => [
                    'de-DE' => $io->ask('Plugin Label [DE]') ?? $pluginName,
                    'en-GB' => $io->ask('Plugin Label [EN]') ?? $pluginName,
                ],
                'description' => [
                    'de-DE' => $io->ask('Plugin Description [DE]') ?? $pluginName,
                    'en-GB' => $io->ask('Plugin Description [EN]') ?? $pluginName,
                ],
                'manufacturerLink' => [
                    'de-DE' => $manufacturerLink = $io->ask('Plugin Manufacturer Link') ?? 'https://example.com',
                    'en-GB' => $manufacturerLink,
                ],
                'supportLink' => [
                    'de-DE' => $supportLink = $io->ask('Plugin Support Link') ?? 'https://example.com/support',
                    'en-GB' => $supportLink,
                ]
            ]
        ];

        $fs->dumpFile($pluginPath . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    }

    private function makeBootstrap(Filesystem $fs, string $pluginPath, string $pluginName, string $namespace): void
    {
        $tpl = <<<EOL
<?php declare(strict_types=1);

namespace #namespace#;

use Shopware\Core\Framework\Plugin;

class #class# extends Plugin
{
}
EOL;

        $fs->dumpFile($pluginPath . '/src/' . $pluginName . '.php', str_replace(['#namespace#', '#class#'], [$namespace, $pluginName], $tpl));
    }

    private function makeChangelogFiles(Filesystem $fs, string $pluginPath): void
    {
        $enChangelog = <<<EOL
# 1.0.0

- Initial publication
EOL;
        $fs->dumpFile($pluginPath . '/CHANGELOG.md', $enChangelog);
    }

    private function makeDefaultServicesXml(Filesystem $fs, string $pluginPath): void
    {
        $xml = <<<XML
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>

    </services>
</container>
XML;

        $fs->dumpFile($pluginPath . '/src/Resources/config/services.xml', $xml);
    }
}

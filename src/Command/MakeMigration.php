<?php

namespace Frosh\DevelopmentHelper\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Frosh\DevelopmentHelper\Component\Generator\Migration\MigrationSchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class MakeMigration extends Command
{
    public static $defaultName = 'frosh:make:migration';

    private string $projectDir;
    private DefinitionInstanceRegistry $definitionInstanceRegistry;
    private MigrationSchemaBuilder $migrationSchemaBuilder;
    private Connection $connection;
    private array $pluginInfos;

    public function __construct(
        string $kernelRootDir,
        array $pluginInfos,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        MigrationSchemaBuilder $migrationSchemaBuilder,
        Connection $connection
    ) {
        parent::__construct();
        $this->projectDir = $kernelRootDir;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->migrationSchemaBuilder = $migrationSchemaBuilder;
        $this->pluginInfos = $pluginInfos;
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('plugin', InputArgument::REQUIRED, 'Plugin Name')
            ->addArgument('definition', InputArgument::IS_ARRAY, 'Definition name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $plugin = $this->projectDir . '/custom/plugins/' . $input->getArgument('plugin');

        $toSchema = new Schema();
        foreach ($input->getArgument('definition') as $name) {
            $definition = $this->definitionInstanceRegistry->getByEntityName($name);
            $this->migrationSchemaBuilder->buildSchemaOfDefinition($toSchema, $definition);
        }

        $fromSchema = $this->connection->getSchemaManager()->createSchema();

        $comparator = new Comparator();
        $updateQueries = $comparator->compare($fromSchema, $toSchema)->toSaveSql($this->connection->getDatabasePlatform());

        if (\count($updateQueries) === 0) {
            $io->success('Schema is already up to date');

            return 0;
        }

        [$namespace, $migrationPath, $className, $timestamp] = $this->determineMigrationPath($input->getArgument('plugin'));

        $migration = <<<PHP
<?php declare(strict_types=1);

namespace #NAMESPACE#;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class #CLASSNAME# extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return #TIMESTAMP#;
    }

    public function update(Connection \$connection): void
    {
#STMS#
    }

    public function updateDestructive(Connection \$connection): void
    {
        // implement update destructive
    }
}

PHP;

        $dbalExec = '';

        foreach ($updateQueries as $sql) {
            $dbalExec .= sprintf('        $connection->executeUpdate(\'%s\');' . PHP_EOL, addslashes($sql));
        }

        $content = str_replace(
            [
                '#NAMESPACE#',
                '#CLASSNAME#',
                '#TIMESTAMP#',
                '#STMS#'
            ],
            [
                $namespace,
                $className,
                $timestamp,
                $dbalExec
            ],
            $migration
        );

        \file_put_contents($migrationPath, $content);

        $io->success(\sprintf('Generated migration file at "%s"', $migrationPath));

        return 0;
    }

    private function determineMigrationPath(string $plugin): array
    {
        foreach ($this->pluginInfos as $pluginInfo) {
            if ($pluginInfo['name'] !== $plugin) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($pluginInfo['baseClass']);

            $timeStamp = time();
            $path = dirname($reflectionClass->getFileName());
            $migrationPath = $path . '/Migration';

            $fs = new Filesystem();

            if (!$fs->exists($migrationPath)) {
                $fs->mkdir($migrationPath);
            }

            return [
                $reflectionClass->getNamespaceName() . '\\Migration', // namespace
                $path . '/Migration/Migration' . $timeStamp . '.php', // file name
                'Migration' . $timeStamp, // class name
                $timeStamp
            ];
        }

        throw new \RuntimeException(sprintf('Cannot find plugin by name "%s"', $plugin));

    }
}

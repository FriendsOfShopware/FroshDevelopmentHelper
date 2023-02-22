<?php

namespace Frosh\DevelopmentHelper\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Frosh\DevelopmentHelper\Component\Generator\Migration\MigrationSchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('frosh:schema:diff')]
class SchemaDiff extends Command
{
    private readonly Connection $connection;

    public function __construct(private readonly MigrationSchemaBuilder $builder, Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    public function configure(): void
    {
        $this->setDescription('Diffs the database structure with the known definitions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $localSchema = $this->builder->build();
        $remoteSchema = $this->connection->createSchemaManager()->introspectSchema();

        $comparator = new Comparator();
        $sqls = $comparator->compareSchemas($remoteSchema, $localSchema)->toSaveSql($this->connection->getDatabasePlatform());

        $output->writeln($sqls);

        return 0;
    }
}

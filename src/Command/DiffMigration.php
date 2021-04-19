<?php

namespace Frosh\DevelopmentHelper\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Frosh\DevelopmentHelper\Component\Generator\Migration\MigrationSchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiffMigration extends Command
{
    private MigrationSchemaBuilder $builder;
    private Connection $connection;

    public function __construct(MigrationSchemaBuilder $builder, Connection $connection)
    {
        parent::__construct();
        $this->builder = $builder;
        $this->connection = $connection;
    }

    protected static $defaultName = 'frosh:diff:migration';

    public function configure(): void
    {
        $this->setDescription('Diffs the database structure with the known definitions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $localSchema = $this->builder->build();
        $remoteSchema = $this->connection->getSchemaManager()->createSchema();

        $comparator = new Comparator();
        $sqls = $comparator->compare($remoteSchema, $localSchema)->toSaveSql($this->connection->getDatabasePlatform());

        $output->writeln($sqls);

        return 0;
    }
}

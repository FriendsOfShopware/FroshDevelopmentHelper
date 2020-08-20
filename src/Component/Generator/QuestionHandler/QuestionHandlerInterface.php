<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\QuestionHandler;

use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Symfony\Component\Console\Style\SymfonyStyle;

interface QuestionHandlerInterface
{
    public function supports(string $field): bool;

    public function handle(Definition $definition, SymfonyStyle $io, string $name, string $type): ?Field;
}

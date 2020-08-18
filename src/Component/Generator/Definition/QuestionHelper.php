<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Symfony\Component\Console\Style\SymfonyStyle;

class QuestionHelper
{
    public static function handleFlags(SymfonyStyle $io, string $fieldName, string $type, array $args): array
    {
        $flags = [];

        $isNullable = $io->confirm(sprintf(
            'Is the <comment>%s</comment> property allowed to be null (nullable)?',
            $fieldName
        ));

        if (!$isNullable) {
            $flags[] = Required::class;
        }

        $allowHtml = $io->confirm(sprintf(
            'Can <comment>%s</comment> contain html?',
            $fieldName
        ));

        if ($allowHtml) {
            $flags[] = AllowHtml::class;
        }

        return $flags;
    }
}

<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Frosh\DevelopmentHelper\Component\Generator\Definition\CustomFlags\Translateable;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Symfony\Component\Console\Style\SymfonyStyle;

class QuestionHelper
{
    private const HTML_RELEVANT_FIELDS = [
        StringField::class,
        LongTextField::class
    ];

    public static function handleFlags(SymfonyStyle $io, string $fieldName, string $type, array $args): array
    {
        $flags = [];

        $isNullable = $io->confirm(sprintf(
            'Is the <comment>%s</comment> property allowed to be null (nullable)?',
            $fieldName
        ));

        if (!$isNullable) {
            $flags[] = new Flag(Required::class);
        }

        if (in_array($type, self::HTML_RELEVANT_FIELDS, true)) {
            $allowHtml = $io->confirm(sprintf(
                'Can <comment>%s</comment> contain html?',
                $fieldName
            ), false);

            if ($allowHtml) {
                $flags[] = new Flag(AllowHtml::class);
            }
        }

        return $flags;
    }

    public static function handleTranslationQuestion(SymfonyStyle $io): bool
    {
        return $io->confirm('Field can be translated?', false);
    }
}

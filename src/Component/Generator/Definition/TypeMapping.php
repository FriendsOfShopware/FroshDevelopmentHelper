<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Definition;


use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class TypeMapping
{
    private const TYPES = [
        StringField::class => 'string',
        IntField::class => 'int',
        BoolField::class => 'bool',
        BlobField::class => 'string',
        DateField::class => '\DateTime',
        DateTimeField::class  => '\DateTime',
        EmailField::class => 'string',
        FkField::class => 'string',
        FloatField::class => 'float',
        LongTextField::class => 'string',
        LongTextWithHtmlField::class => 'string',
        PasswordField::class => 'string'
    ];

    public static function getCompletionTypes(): array
    {
        $types = [];
        foreach (array_keys(self::TYPES) as $fieldsType) {
            $types[] = str_replace('Shopware\Core\Framework\DataAbstractionLayer\Field\\', '', $fieldsType);
        }

        return $types;
    }

    public static function mapToPhpType(string $name): string
    {
        return self::TYPES[$name] ?? 'string';
    }
}

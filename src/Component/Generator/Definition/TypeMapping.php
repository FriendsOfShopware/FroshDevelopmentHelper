<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WhitelistRuleField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

class TypeMapping
{
    private const TYPES = [
        BlobField::class => 'string',
        BoolField::class => 'bool',
        CalculatedPriceField::class => 'array',
        CartPriceField::class => 'array',
        ChildCountField::class => 'int',
        ChildrenAssociationField::class => '',
        CreatedAtField::class => '\DateTime',
        CustomFields::class => 'array',
        DateField::class => '\DateTime',
        DateTimeField::class  => '\DateTime',
        EmailField::class => 'string',
        FkField::class => 'string',
        FloatField::class => 'float',
        IntField::class => 'int',
        JsonField::class => 'array',
        ListField::class => 'array',
        LockedField::class => 'bool',
        LongTextField::class => 'string',
        ObjectField::class => 'array',
        PriceDefinitionField::class => PriceDefinitionInterface::class,
        PriceField::class => PriceCollection::class,
        RemoteAddressField::class => 'array',
        StateMachineStateField::class => 'string',
        StringField::class => 'string',
        TranslatedField::class => null,
        TreeBreadcrumbField::class => 'array',
        TreeLevelField::class => 'int',
        TreePathField::class => 'string',
        UpdatedAtField::class => '\DateTime',
        VersionField::class => 'string',
        PasswordField::class => 'string',
        NumberRangeField::class => 'string',
        ManyToManyIdField::class => 'array',
        TranslationsAssociationField::class => 'translationCollection',

        ManyToOneAssociationField::class => 'associationField',
        OneToOneAssociationField::class => 'associationField',
        OneToManyAssociationField::class => 'associationField',
        ManyToManyAssociationField::class => 'associationField',
    ];

    public static function getCompletionTypes(): array
    {
        $types = [];
        foreach (array_keys(self::TYPES) as $fieldsType) {
            $types[] = str_replace('Shopware\Core\Framework\DataAbstractionLayer\Field\\', '', $fieldsType);
        }

        return $types;
    }

    public static function mapToPhpType(Field $field, bool $respectNull, Definition $definition): string
    {
        $type = self::TYPES[$field->name] ?? null;

        if ($type === 'associationField') {
            $type = substr($field->getReferenceClass(), 0, -7);

            if (str_contains($type, '\\')) {
                $type = '\\' . $type;
            }
        }

        if ($type === 'translationCollection') {
            return '\\' . $definition->translation->getCollectionClass();
        }

        if ($respectNull && $field->isNullable()) {
            $type .= '|null';
        }

        if ($field->name === TranslatedField::class) {
            return self::getTranslatedType($field, $respectNull, $definition);
        }

        return $type;
    }

    private static function getTranslatedType(Field $field, bool $respectNull, Definition $definition): string
    {
        foreach ($definition->translation->fields as $translatedField) {
            if ($translatedField->getPropertyName() === $field->getPropertyName()) {
                return self::mapToPhpType($translatedField, $respectNull, $definition);
            }
        }

        throw new \RuntimeException(sprintf('Field %s does not exists in translation', $field->getPropertyName()));
    }
}

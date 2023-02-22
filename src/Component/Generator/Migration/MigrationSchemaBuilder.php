<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;

class MigrationSchemaBuilder
{
    private readonly DefinitionInstanceRegistry $instanceRegistry;

    public function __construct(DefinitionInstanceRegistry $instanceRegistry)
    {
        $this->instanceRegistry = $instanceRegistry;
    }

    public function build(): Schema
    {
        $schema = new Schema();

        foreach ($this->instanceRegistry->getDefinitions() as $definition) {
            $this->buildSchemaOfDefinition($schema, $definition);
        }

        return $schema;
    }

    public function buildSchemaOfDefinition(Schema $schema, EntityDefinition $definition): void
    {
        $table = $schema->createTable($definition->getEntityName());

        foreach ($definition->getFields() as $field) {
            if ($field->is(Runtime::class)) {
                continue;
            }

            if ($field instanceof AssociationField) {
                continue;
            }

            if (!$field instanceof StorageAware) {
                continue;
            }

            if ($field instanceof TranslatedField) {
                continue;
            }

            [$type, $options] = $this->getFieldDefinition($field);

            $table->addColumn($field->getStorageName(), $type, $options);
        }

        $table->setPrimaryKey(array_map(fn(StorageAware  $field) => $field->getStorageName(), $definition->getPrimaryKeys()->getElements()));
        $this->addForeignKeys($table, $definition);
    }

    private function getFieldDefinition(Field $field): array
    {
        $options = [
            'notnull' => false,
        ];

        if ($field->is(Required::class) && !$field instanceof UpdatedAtField) {
            $options['notnull'] = true;
        }

        switch (true) {
            case $field instanceof VersionField:
            case $field instanceof ReferenceVersionField:
            case $field instanceof ParentFkField:
            case $field instanceof IdField:
            case $field instanceof FkField:
                $type = Types::BINARY;
                $options['length'] = 16;
                $options['fixed'] = true;

                break;

            case $field instanceof UpdatedAtField:
            case $field instanceof CreatedAtField:
            case $field instanceof DateTimeField:
                $type = Types::DATETIME_MUTABLE;

                break;

            case $field instanceof DateField:
                $type = Types::DATE_MUTABLE;

                break;

            case $field instanceof CartPriceField:
            case $field instanceof CalculatedPriceField:
            case $field instanceof PriceDefinitionField:
            case $field instanceof PriceField:
            case $field instanceof ListField:
            case $field instanceof JsonField:
                $type = Types::JSON;

                break;

            case $field instanceof ChildCountField:
            case $field instanceof IntField:
                $type = Types::INTEGER;

                break;

            case $field instanceof TreePathField:
            case $field instanceof LongTextField:
                $type = Types::TEXT;

                break;

            case $field instanceof TreeLevelField:
                $type = Types::INTEGER;

                break;

            case $field instanceof RemoteAddressField:
                $type = Types::STRING;

                break;

            case $field instanceof PasswordField:
                $type = Types::STRING;

                break;

            case $field instanceof FloatField:
                $type = Types::FLOAT;

                break;

            case $field instanceof StringField:
                $type = Types::STRING;
                $options['length'] = $field->getMaxLength();

                break;

            case $field instanceof BoolField:
                $type = Types::BOOLEAN;
                $options['default'] = 0;

                break;

            case $field instanceof BlobField:
                $type = Types::BLOB;

                break;

            default:
                throw new \RuntimeException(sprintf('Unknown field %s', $field::class));
        }

        return [
            $type,
            $options
        ];
    }

    private function addForeignKeys(Table $table, EntityDefinition $definition): void
    {
        $fields = $definition->getFields()->filter(
            function (Field $field) {
                if ($field instanceof ManyToOneAssociationField || ($field instanceof OneToOneAssociationField && $field->getStorageName() !== 'id')) {
                    return true;
                }

                return false;
            }
        );

        $referenceVersionFields = $definition->getFields()->filterInstance(ReferenceVersionField::class);

        /** @var ManyToOneAssociationField $field */
        foreach ($fields as $field) {
            $reference = $field->getReferenceDefinition();

            $hasOneToMany = $definition->getFields()->filter(function (Field $field) use ($reference) {
                    if (!$field instanceof OneToManyAssociationField) {
                        return false;
                    }
                    if ($field instanceof ChildrenAssociationField) {
                        return false;
                    }

                    return $field->getReferenceDefinition() === $reference;
                })->count() > 0;

            $columns = [
                $field->getStorageName(),
            ];

            $referenceColumns = [
                $field->getReferenceField(),
            ];

            if ($reference->isVersionAware()) {
                $versionField = null;

                /** @var ReferenceVersionField $referenceVersionField */
                foreach ($referenceVersionFields as $referenceVersionField) {
                    if ($referenceVersionField->getVersionReferenceDefinition() === $reference) {
                        $versionField = $referenceVersionField;

                        break;
                    }
                }

                if ($field instanceof ParentAssociationField) {
                    $columns[] = 'version_id';
                } else {
                    $columns[] = $versionField->getStorageName();
                }

                $referenceColumns[] = 'version_id';
            }

            $update = 'CASCADE';

            if ($field->is(CascadeDelete::class)) {
                $delete = 'CASCADE';
            } elseif ($field->is(RestrictDelete::class)) {
                $delete = 'RESTRICT';
            } else {
                $delete = 'SET NULL';
            }

            // skip foreign key to prevent bi-directional foreign key
            if ($hasOneToMany) {
                continue;
            }

            $table->addForeignKeyConstraint(
                $reference->getEntityName(),
                $columns,
                $referenceColumns,
                [
                    'onUpdate' => $update,
                    'onDelete' => $delete
                ],
                sprintf('fk.%s.%s', $definition->getEntityName(), $field->getStorageName())
            );
        }
    }
}

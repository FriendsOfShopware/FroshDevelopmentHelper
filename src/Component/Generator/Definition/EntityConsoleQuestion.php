<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Frosh\DevelopmentHelper\Component\Generator\QuestionHandler\QuestionHandlerInterface;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class EntityConsoleQuestion
{
    /**
     * @param QuestionHandlerInterface[] $questionHelpers
     */
    public function __construct(private readonly iterable $questionHelpers)
    {
        $this->fieldsTypes = TypeMapping::getCompletionTypes();
    }

    /**
     * @param Field[] $fieldCollection
     */
    public function question(SymfonyStyle $io, Definition $definition): void
    {
        if (!$this->hasField($definition->fields, IdField::class)) {
            $definition->fields[] = new Field(IdField::class, ['id', 'id'], [new Flag(Required::class), new Flag(PrimaryKey::class)]);
        }

        $currentFields = $this->getCurrentFields($definition->fields);

        while (true) {
            $field = $this->askForNextField($io, $currentFields, $definition);

            if ($field === null) {
                break;
            }

            $currentFields[] = $field->getPropertyName();

            if ($field->translateable) {
                $definition->fields[] = new Field(TranslatedField::class, [$field->getPropertyName()]);
                $definition->translation->fields[] = $field;
            } else {
                $definition->fields[] = $field;
            }
        }

        $definition->fields = $this->addMissingFkFields($definition->fields);

        if ($definition->translation && !$this->hasField($definition->fields, TranslationsAssociationField::class)) {
            $definition->fields[] = new Field(
                TranslationsAssociationField::class,
                [
                    $definition->translation->getDefinitionClass() . '::class',
                    $definition->getDefinitionName() . '_id'
                ]
            );
        }
    }

    private function askForNextField(SymfonyStyle $io, array $currentFields, Definition $definition): ?Field
    {
        $fieldName = $io->ask('New property name (press <return> to stop adding fields)', null, function ($name) use ($currentFields) {
            // allow it to be empty
            if (!$name) {
                return $name;
            }
            if (\in_array($name, $currentFields, true)) {
                throw new \InvalidArgumentException(sprintf('The "%s" property already exists.', $name));
            }
            return $name;
        });

        if (!$fieldName) {
            return null;
        }


        $type = null;

        while ($type === null) {
            $question = new Question('Field type', 'StringField');
            $question->setAutocompleterValues($this->fieldsTypes);
            $type = $io->askQuestion($question);

            if (!\in_array($type, $this->fieldsTypes, true)) {
                $io->error(sprintf('Invalid type "%s".', $type));
                $io->writeln('');
                $type = null;
            }
        }

        foreach ($this->questionHelpers as $helper) {
            if ($helper->supports($type)) {
                return $helper->handle($definition, $io, $fieldName, $type);
            }
        }
    }

    private function hasField(array $fieldCollection, string $field): bool
    {
        foreach ($fieldCollection as $element) {
            if ($element->name === $field) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentFields(array $fieldCollection): array
    {
        return array_map(static fn(Field $field) => $field->getPropertyName(), $fieldCollection);
    }

    private function addMissingFkFields(array $fieldCollection): array
    {
        /** @var Field $field */
        foreach ($fieldCollection as $field) {
            if ($field->name === ManyToOneAssociationField::class || $field->name === OneToOneAssociationField::class) {
                $haveFkField = false;
                $associationStorageName = $field->getStorageName();

                /** @var Field $nField */
                foreach ($fieldCollection as $nField) {
                    if (!$nField->isStorageAware()) {
                        continue;
                    }

                    if ($nField->name !== $field->name && $nField->getStorageName() === $associationStorageName) {
                        $haveFkField = true;
                    }
                }

                if ($haveFkField) {
                    continue;
                }

                $fieldCollection[] = new Field(FkField::class, [$associationStorageName, $associationStorageName, $field->getReferenceClass()], $field->flags);
            }
        }

        return $fieldCollection;
    }
}

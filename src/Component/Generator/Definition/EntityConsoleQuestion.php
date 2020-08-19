<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

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
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class EntityConsoleQuestion
{
    /**
     * @var array
     */
    private $fieldsTypes;

    /**
     * @var array
     */
    private $entityDefinitions;

    public function __construct(array $entityDefinitions)
    {
        $this->fieldsTypes = TypeMapping::getCompletionTypes();
        $this->entityDefinitions = $entityDefinitions;
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

        $type = 'Shopware\Core\Framework\DataAbstractionLayer\Field\\' . $type;

        $ref = new \ReflectionClass($type);
        $parameters = $ref->getConstructor()->getParameters();

        $args = [];
        foreach ($parameters as $parameter) {
            if ($parameter->name === 'propertyName') {
                $args[] = $fieldName;
                continue;
            }

            if ($parameter->name === 'referenceClass') {
                $question = new Question('Reference Class');
                $question->setAutocompleterValues($this->entityDefinitions);
                $question->setValidator(function ($value) {
                    if (!in_array($value, $this->entityDefinitions, true)) {
                        throw new \InvalidArgumentException(sprintf('%s is an invalid reference class', $value));
                    }

                    return $value;
                });
                $value = $io->askQuestion($question);

                $args[] = $value . '::class';
                continue;
            }

            $default = null;
            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
            }

            if ($parameter->name === 'storageName' && $default === null) {
                $default = (new CamelCaseToSnakeCaseNameConverter())->normalize($fieldName);
            }
            if (is_bool($default)) {
                $default = $default ? 'true' : 'false';
            }

            $question = new Question('Parameter ' . $parameter->name, $default);
            $answer = $io->askQuestion($question);
            if ($answer === 'true') {
                $answer = true;
            } else if($answer === 'false') {
                $answer = false;
            } else if(strlen($answer) && $parameter->hasType()) {
                $parameterType = (string) $parameter->getType();

                if ($parameterType === 'int') {
                    $answer = (int) $answer;
                } else if($parameterType === 'float') {
                    $answer = (float) $answer;
                }
            }


            $args[] = $answer;
        }

        return new Field(
            $type,
            $args,
            QuestionHelper::handleFlags($io, $fieldName, $type, $args),
            $definition->translation !== null && QuestionHelper::handleTranslationQuestion($io)
        );
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
        return array_map(static function (Field $field) {
            return $field->getPropertyName();
        }, $fieldCollection);
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

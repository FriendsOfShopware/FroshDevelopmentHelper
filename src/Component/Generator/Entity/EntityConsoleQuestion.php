<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class EntityConsoleQuestion
{
    private $fieldsTypes = [
        StringField::class,
        IntField::class,
        BoolField::class,
        BlobField::class,
        DateField::class,
        DateTimeField::class,
        EmailField::class,
        FkField::class,
        FloatField::class,
        LongTextField::class,
        LongTextWithHtmlField::class,
        PasswordField::class
    ];

    public function __construct()
    {
        foreach ($this->fieldsTypes as &$fieldsType) {
            $fieldsType = str_replace('Shopware\Core\Framework\DataAbstractionLayer\Field\\', '', $fieldsType);
        }
    }

    /**
     * @param Field[] $fieldCollection
     */
    public function question(InputInterface $input, OutputInterface $output, array $fieldCollection): array
    {
        if (!$this->hasIdField($fieldCollection)) {
            $fieldCollection[] = new Field(IdField::class, ['id', 'id'], [Required::class, PrimaryKey::class]);
        }

        $currentFields = $this->getCurrentFields($fieldCollection);
        $io = new SymfonyStyle($input, $output);

        while (true) {
            $field = $this->askForNextField($io, $currentFields);

            if ($field === null) {
                break;
            }

            $currentFields[] = $field->name;
            $fieldCollection[] = $field;
        }

        return $fieldCollection;
    }

    private function askForNextField(SymfonyStyle $io, array $currentFields): ?Field
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

            $default = null;
            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
            }

            if ($parameter->name === 'storageName' && $default === null) {
                $default = $fieldName;
            }

            $question = new Question('Parameter ' . $parameter->name, $default);
            $answer = $io->askQuestion($question);
            if ($answer === 'true') {
                $answer = true;
            } else if($answer === 'false') {
                $answer = false;
            }

            $args[] = $answer;
        }

        $isNullable = $io->confirm(sprintf(
            'Is the <comment>%s</comment> property allowed to be null (nullable)?',
            $fieldName
        ));

        return new Field($type, $args, $isNullable ? [] : [PrimaryKey::class]);
    }

    private function hasIdField(array $fieldCollection): bool
    {
        foreach ($fieldCollection as $element) {
            if ($element instanceof IdField) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentFields(array $fieldCollection): array
    {
        return array_map(static function (Field $field) {
            return $field->name;
        }, $fieldCollection);
    }
}

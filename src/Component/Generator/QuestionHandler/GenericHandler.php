<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\QuestionHandler;

use Frosh\DevelopmentHelper\Component\Generator\Definition\EntityLoader;
use Frosh\DevelopmentHelper\Component\Generator\Definition\ExtensionGenerator;
use Frosh\DevelopmentHelper\Component\Generator\Definition\QuestionHelper;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class GenericHandler implements QuestionHandlerInterface
{
    public function __construct(private readonly array $entityDefinitions, private readonly ExtensionGenerator $extensionGenerator, private readonly EntityLoader $entityLoader)
    {
    }

    public function supports(string $field): bool
    {
        return true;
    }

    public function handle(Definition $definition, SymfonyStyle $io, string $name, string $type): ?Field
    {
        $type = 'Shopware\Core\Framework\DataAbstractionLayer\Field\\' . $type;

        $ref = new \ReflectionClass($type);
        $parameters = $ref->getConstructor()->getParameters();

        $args = [];
        foreach ($parameters as $parameter) {
            if ($parameter->name === 'propertyName') {
                $args[] = $name;
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
                $default = (new CamelCaseToSnakeCaseNameConverter())->normalize($name);
            }

            if (is_bool($default)) {
                $default = $default ? 'true' : 'false';
            }

            // array is currently not supported
            if (is_array($default)) {
                $args[] = $default;
                continue;
            }

            $question = new Question('Parameter ' . $parameter->name, $default);
            $answer = $io->askQuestion($question);
            if ($answer === 'true') {
                $answer = true;
            } else if($answer === 'false') {
                $answer = false;
            } else if(strlen((string) $answer) && $parameter->hasType()) {
                $parameterType = (string) ($parameter->getType() ? $parameter->getType()->getName() : null);

                if ($parameterType === 'int' || $parameterType === '?int') {
                    $answer = (int) $answer;
                } else if($parameterType === 'float' || $parameterType === '?float') {
                    $answer = (float) $answer;
                }
            }

            $args[] = $answer;
        }

        $field = new Field(
            $type,
            $args,
            QuestionHelper::handleFlags($io, $name, $type, $args),
            $definition->translation !== null && QuestionHelper::handleTranslationQuestion($io)
        );

        if (in_array($field->getName(), [OneToOneAssociationField::class, OneToManyAssociationField::class, ManyToOneAssociationField::class])) {
            if ($io->confirm('Create an own entity extensions?')) {
                $this->buildEntityExtension($field, $definition);
            }
        }

        return $field;
    }

    private function buildEntityExtension(Field $field, Definition $localDefinition): void
    {
        $refClass = str_replace('::class', '', $field->getReferenceClass());
        $targetDefinition = $this->entityLoader->load($refClass, new SymfonyStyle(new ArgvInput(), new NullOutput()));

        $extensionField = null;

        if ($field->getName() === OneToOneAssociationField::class) {
            $extensionField = new Field(
                OneToOneAssociationField::class,
                [
                    $this->normalize($localDefinition->getDefinitionName()),
                    $field->getArg(2), // Obtain reference field from local
                    $field->getArg(1), // Obtain local field
                    $localDefinition->getDefinitionClass(). '::class'
                ]
            );
        } else if($field->getName() === OneToManyAssociationField::class) {
            $extensionField = new Field(
                ManyToOneAssociationField::class,
                [
                    $this->normalize($localDefinition->getDefinitionName()),
                    $field->getArg(2), // Obtain reference field from local
                    $localDefinition->getDefinitionClass(). '::class', // Reference class
                    $field->getArg(3) ?? 'id', // Obtain local field
                ]
            );
        } else if($field->getName() === ManyToOneAssociationField::class) {
            $extensionField = new Field(
                OneToManyAssociationField::class,
                [
                    $this->normalize($localDefinition->getDefinitionName()),
                    $localDefinition->getDefinitionClass(). '::class', // Reference class
                    $field->getArg(1), // Obtain reference field from local
                    $field->getArg(3) ?? 'id', // Obtain local field
                ]
            );
        }

        $this->extensionGenerator->generate(
            $localDefinition,
            $targetDefinition,
            $extensionField
        );
    }

    private function normalize(string $name): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($name);
    }
}

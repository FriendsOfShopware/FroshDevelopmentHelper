<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\QuestionHandler;

use Frosh\DevelopmentHelper\Component\Generator\Definition\DefinitionGenerator;
use Frosh\DevelopmentHelper\Component\Generator\Definition\EntityLoader;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Flag;
use Frosh\DevelopmentHelper\Component\Generator\Struct\MappingDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class ManyToManyHandler implements QuestionHandlerInterface
{
    /**
     * @var array
     */
    private $entityDefinitions;

    /**
     * @var EntityLoader
     */
    private $loader;

    /**
     * @var DefinitionGenerator
     */
    private $definitionGenerator;

    public function __construct(array $entityDefinitions, EntityLoader $loader, DefinitionGenerator $definitionGenerator)
    {
        $this->entityDefinitions = $entityDefinitions;
        $this->loader = $loader;
        $this->definitionGenerator = $definitionGenerator;
    }

    public function supports(string $field): bool
    {
        return $field === 'ManyToManyAssociationField';
    }

    public function handle(Definition $definition, SymfonyStyle $io, string $name, string $type): ?Field
    {
        $field = new Field(ManyToManyAssociationField::class);

        $field->args[] = $name;

        $question = new Question('Reference Class');
        $question->setAutocompleterValues($this->entityDefinitions);
        $question->setValidator(function ($value) {
            if (!in_array($value, $this->entityDefinitions, true)) {
                throw new \InvalidArgumentException(sprintf('%s is an invalid reference class', $value));
            }

            return $value;
        });

        $referenceClass = $io->askQuestion($question);
        $field->args[] = $referenceClass . '::class';

        $referenceDefinition = $this->loader->load($referenceClass, $io);
        $mappingDefinition = $this->createMappingDefinition($definition, $referenceDefinition);

        $this->definitionGenerator->generate($mappingDefinition);

        $field->args[] = $mappingDefinition->getDefinitionClass();
        $field->args[] = $mappingDefinition->getStorageNameByReference($definition->getDefinitionClass());
        $field->args[] = $mappingDefinition->getStorageNameByReference($referenceDefinition->getDefinitionClass());

        $this->updateReference($referenceDefinition, $definition, $mappingDefinition);

        return $field;
    }

    private function createMappingDefinition(Definition $definition, Definition $reference): MappingDefinition
    {
        $mapping = new MappingDefinition();
        $mapping->name = $definition->name . $reference->name;
        $mapping->namespace = $definition->namespace . '\\Aggregate\\' . $mapping->name;
        $mapping->folder = $definition->folder . 'Aggregate/' . $mapping->name. '/';

        $mapping->fields[] = new Field(
            FkField::class,
            [
                $this->normalize($definition->name) . '_id',
                lcfirst($definition->name) . 'Id',
                $definition->getDefinitionClass() . '::class'
            ],
            [
                new Flag(PrimaryKey::class),
                new Flag(Required::class)
            ]
        );

        $mapping->fields[] = new Field(ManyToOneAssociationField::class,
            [
                lcfirst($definition->name),
                $this->normalize($definition->name) . '_id',
                $definition->getDefinitionClass(). '::class',
                'id',
                false
            ]
        );

        $mapping->fields[] = new Field(
            FkField::class,
            [
                $this->normalize($reference->name) . '_id',
                lcfirst($reference->name) . 'Id',
                $reference->getDefinitionClass(). '::class'
            ],
            [
                new Flag(PrimaryKey::class),
                new Flag(Required::class)
            ]
        );

        $mapping->fields[] = new Field(ManyToOneAssociationField::class,
            [
                lcfirst($reference->name),
                $this->normalize($reference->name) . '_id',
                $reference->getDefinitionClass(). '::class',
                'id',
                false
            ]
        );

        $mapping->fields[] = new Field(CreatedAtField::class);

        return $mapping;
    }

    private function normalize(string $name): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($name);
    }

    private function updateReference(Definition $referenceDefinition, Definition $definition, MappingDefinition $mapping): void
    {
        $referenceDefinition->fields[] = new Field(
            ManyToManyAssociationField::class,
            [
                lcfirst($definition->name) . 's',
                $definition->getDefinitionClass() . '::class',
                $mapping->getDefinitionClass() . '::class',
                $mapping->getStorageNameByReference($referenceDefinition->getDefinitionClass()),
                $mapping->getStorageNameByReference($definition->getDefinitionClass()),
            ]
        );

        $this->definitionGenerator->generate($referenceDefinition);
    }
}

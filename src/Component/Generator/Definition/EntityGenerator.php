<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Definition;


use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\Struct\TranslationDefinition;
use Frosh\DevelopmentHelper\Component\Generator\UseHelper;
use PhpParser\BuilderFactory;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class EntityGenerator
{
    public function generate(Definition $definition): void
    {
        if (!file_exists($definition->folder) && !mkdir($concurrentDirectory = $definition->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $builder = new BuilderFactory();
        $nodeFinder = new NodeFinder();
        $useHelper = new UseHelper();

        if (file_exists($definition->getEntityFilePath())) {
            $namespace = $this->buildNamespaceFromExistingFile($definition->getEntityFilePath(), $useHelper);
        } else {
            $namespace = $this->buildNewNamespace($definition, $useHelper);
        }

        $namespace->stmts = array_merge($useHelper->getStms(), $namespace->stmts);

        /** @var Class_ $class */
        $class = $nodeFinder->findFirstInstanceOf([$namespace], Class_::class);

        $classProperties = $nodeFinder->findInstanceOf([$class], PropertyProperty::class);
        $classMethods = $nodeFinder->findInstanceOf([$class], ClassMethod::class);

        /** @var Field $field */
        foreach ($definition->fields as $field) {
            if ($field->name === IdField::class) {
                continue;
            }

            if ($this->hasProperty($classProperties, $field->getPropertyName())) {
                continue;
            }

            $type = TypeMapping::mapToPhpType($field, false, $definition);
            if ($field->isNullable()) {
                $type = '?' . $type;
            }

            $node = $builder
                ->property($field->getPropertyName())
                ->setType($type)
                ->makePublic();

            if ($field->isNullable()) {
                $node = $node->setDefault(null);
            }

            $class->stmts[] = $node
                ->getNode();
        }

        $printer = new Standard();

        file_put_contents($definition->getEntityFilePath(), $printer->prettyPrintFile([$namespace]));
    }

    private function buildNewNamespace(Definition $loaderResult, UseHelper $useHelper): Namespace_
    {
        $factory = new BuilderFactory();

        $namespace = new Namespace_(new Name($loaderResult->namespace));

        $class = $factory->class($loaderResult->name . 'Entity');

        if ($loaderResult instanceof TranslationDefinition) {
            $useHelper->addUse(TranslationEntity::class);
            $class
                ->extend('TranslationEntity')
                ->addStmt($factory->useTrait('EntityIdTrait'));
        } else {
            $useHelper->addUse(Entity::class);
            $class
                ->extend('Entity')
                ->addStmt($factory->useTrait('EntityIdTrait'));
        }

        $namespace->stmts[] = $class->getNode();

        $useHelper->addUse(EntityIdTrait::class);

        return $namespace;
    }

    private function hasProperty(array $properties, string $name): bool
    {
        /** @var PropertyProperty $property */
        foreach ($properties as $property) {
            if ((string) $property->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasMethod(array $methods, string $name): bool
    {
        /** @var ClassMethod $method */
        foreach ($methods as $method) {
            if ((string) $method->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function buildNamespaceFromExistingFile(string $entityFile, UseHelper $useHelper): Namespace_
    {
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $ast = $parser->parse(file_get_contents($entityFile));

        $nodeFinder = new NodeFinder();

        $useFlags = $nodeFinder->findInstanceOf($ast, UseUse::class);
        /** @var UseUse $useFlag */
        foreach ($useFlags as $useFlag) {
            $useHelper->addUsage((string) $useFlag->name);
        }

        return $nodeFinder->findFirstInstanceOf($ast, Namespace_::class);
    }
}

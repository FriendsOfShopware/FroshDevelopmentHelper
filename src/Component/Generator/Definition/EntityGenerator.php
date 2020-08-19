<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Definition;


use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
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
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class EntityGenerator
{
    public function generate(Definition $loaderResult): void
    {
        if (!file_exists($loaderResult->folder) && !mkdir($concurrentDirectory = $loaderResult->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $builder = new BuilderFactory();
        $nodeFinder = new NodeFinder();
        $useHelper = new UseHelper();

        $namespace = $this->buildNewNamespace($loaderResult,  $useHelper);

        $namespace->stmts = array_merge($useHelper->getStms(), $namespace->stmts);

        /** @var Class_ $class */
        $class = $nodeFinder->findFirstInstanceOf([$namespace], Class_::class);

        /** @var Field $field */
        foreach ($loaderResult->fields as $field) {
            if ($field->name === IdField::class) {
                continue;
            }

            $type = TypeMapping::mapToPhpType($field, true, $loaderResult);

            $class->stmts[] = $builder->property($field->getPropertyName())->makeProtected()->setDocComment('/** @var ' . $type . ' */')->getNode();
        }

        /** @var Field $field */
        foreach ($loaderResult->fields as $field) {
            if ($field->name === IdField::class) {
                continue;
            }

            $type = TypeMapping::mapToPhpType($field, false, $loaderResult);

            if ($field->isNullable()) {
                $type = '?' . $type;
            }

            $method = new ClassMethod(new Identifier('set' . ucfirst($field->getPropertyName())));
            $method->flags = Class_::MODIFIER_PUBLIC;
            $method->returnType = new Identifier('void');
            $method->params = [new Param(new Name('$value'), null, new Name($type))];

            $var = new PropertyFetch(new Variable('this'), new Name($field->getPropertyName()));
            $method->stmts[] = new Expression(new Assign($var, new Variable('value')));

            $class->stmts[] = $method;

            $method = new ClassMethod(new Identifier('get' . ucfirst($field->getPropertyName())));
            $method->flags = Class_::MODIFIER_PUBLIC;
            $method->returnType = new Identifier($type);

            $method->stmts[] = new Return_($var);

            $class->stmts[] = $method;
        }

        $printer = new Standard();

        file_put_contents($loaderResult->getEntityFilePath(), $printer->prettyPrintFile([$namespace]));
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
}

<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Definition;


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
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;

class EntityGenerator
{
    public function generate(LoaderResult $loaderResult): void
    {
        if (!file_exists($loaderResult->folder) && !mkdir($concurrentDirectory = $loaderResult->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $builder = new BuilderFactory();
        $nodeFinder = new NodeFinder();

        $file = $loaderResult->folder . $loaderResult->entityName . 'Entity.php';

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

            $type = TypeMapping::mapToPhpType($field->name);
            if ($field->isNullable()) {
                $type .= '|null';
            }

            $class->stmts[] = $builder->property($field->getPropertyName())->makeProtected()->setDocComment('/** @var ' . $type . ' */')->getNode();
        }

        /** @var Field $field */
        foreach ($loaderResult->fields as $field) {
            if ($field->name === IdField::class) {
                continue;
            }

            $type = TypeMapping::mapToPhpType($field->name);

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

//            $type = TypeMapping::mapToPhpType($field->name);
//            if (!in_array(Required::class, $field->flags, true)) {
//                $type .= '|null';
//            }
//
//            $class->stmts[] = $builder->property($field->args[1])->makeProtected()->setDocComment('/** @var ' . $type . ' */')->getNode();
        }

        $printer = new Standard();

        file_put_contents($file, $printer->prettyPrintFile([$namespace]));
    }

    private function buildNewNamespace(LoaderResult $loaderResult, UseHelper $useHelper): Namespace_
    {
        $factory = new BuilderFactory();

        $namespace = new Namespace_(new Name($loaderResult->namespace));

        $class = $factory->class($loaderResult->entityName . 'Entity')
            ->extend('Entity')
            ->addStmt($factory->useTrait('EntityIdTrait'));

        $namespace->stmts[] = $class->getNode();

        $useHelper->addUse(EntityIdTrait::class);
        $useHelper->addUse(Entity::class);

        return $namespace;
    }
}

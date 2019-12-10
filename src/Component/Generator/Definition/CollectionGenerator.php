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
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v1\BundleEntity;

class CollectionGenerator
{
    public function generate(LoaderResult $loaderResult): void
    {
        if (!file_exists($loaderResult->folder) && !mkdir($concurrentDirectory = $loaderResult->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $builder = new BuilderFactory();

        $file = $loaderResult->folder . $loaderResult->entityName . 'Collection.php';

        if (file_exists($file)) {
            return;
        }

        $phpDoc = '/**
 * @method void              add(%className% $entity)
 * @method void              set(string $key, %className% $entity)
 * @method %className%[]    getIterator()
 * @method %className%[]    getElements()
 * @method %className%|null get(string $key)
 * @method %className%|null first()
 * @method %className%|null last()
 */';



        $node = $builder
            ->namespace($loaderResult->namespace)
            ->addStmt($builder->use(EntityCollection::class))
            ->addStmt(
                $builder->class($loaderResult->entityName . 'Collection')
                ->setDocComment(str_replace('%className%', $loaderResult->entityName . 'Entity', $phpDoc))
                ->extend('EntityCollection')
                ->addStmt(
                    $builder->method('getExpectedClass')
                        ->makePublic()
                        ->setReturnType('string')
                        ->addStmt(new Return_($builder->classConstFetch($loaderResult->entityName . 'Entity', 'class')))
                )
            )
            ->getNode();

        $printer = new Standard();

        file_put_contents($file, $printer->prettyPrintFile([$node]));
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

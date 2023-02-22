<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt\Return_;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CollectionGenerator
{
    public function generate(Definition $loaderResult): void
    {
        if (!file_exists($loaderResult->folder) && !mkdir($concurrentDirectory = $loaderResult->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $builder = new BuilderFactory();

        if (file_exists($loaderResult->getCollectionFilePath())) {
            return;
        }

        $phpDoc = '/**
 * @extends EntityCollection<%className%>
 */';

        $node = $builder
            ->namespace($loaderResult->namespace)
            ->addStmt($builder->use(EntityCollection::class))
            ->addStmt(
                $builder->class($loaderResult->getCollectionClassName())
                ->setDocComment(str_replace('%className%', $loaderResult->name . 'Entity', $phpDoc))
                ->extend('EntityCollection')
                ->addStmt(
                    $builder->method('getExpectedClass')
                        ->makePublic()
                        ->setReturnType('string')
                        ->addStmt(new Return_($builder->classConstFetch($loaderResult->name . 'Entity', 'class')))
                )
            )
            ->getNode();

        $printer = new Standard();

        file_put_contents($loaderResult->getCollectionFilePath(), $printer->prettyPrintFile([$node]));
    }
}

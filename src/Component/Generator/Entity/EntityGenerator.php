<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Entity;

use Frosh\DevelopmentHelper\Component\Generator\UseHelper;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class EntityGenerator
{
    public function generate(LoaderResult $loaderResult): void
    {
        if (!file_exists($loaderResult->folder) && !mkdir($concurrentDirectory = $loaderResult->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $file = $loaderResult->folder . $loaderResult->entityName . 'Definition.php';

        $useHelper = new UseHelper();

        if (file_exists($file)) {
            $namespace = $this->buildNamespaceFromExistingFile($file, $loaderResult, $useHelper);
        } else {
            $namespace = $this->buildNewNamespace($loaderResult, $useHelper);
        }

        $nodeFinder = new NodeFinder();

        /** @var ClassMethod $method */
        $method = $nodeFinder->findFirst([$namespace], function (Node $node) {
            return $node instanceof ClassMethod && $node->name->name === 'defineFields';
        });

        $method->stmts = [new Return_(new New_(
            new Name('FieldCollection'),
            [
                new Arg($this->buildFieldCollection($loaderResult->fields, $useHelper))
            ]
        ))];

        $printer = new Standard();

        file_put_contents($loaderResult->folder . $loaderResult->entityName . 'Definition.php', $printer->prettyPrintFile([$namespace]));
    }

    private function buildNewNamespace(LoaderResult $loaderResult, UseHelper $useHelper): Namespace_
    {
        $namespace = new Namespace_(new Name($loaderResult->namespace));

        $class = new Class_(new Identifier($loaderResult->entityName . 'Definition'));

        $entityName = new ClassMethod(new Identifier('getEntityName'));
        $entityName->returnType = new Name('string');
        $entityName->flags = Class_::MODIFIER_PUBLIC;
        $entityName->stmts[] = new Return_(new String_($loaderResult->entityName));

        $defineFields = new ClassMethod(new Identifier('defineFields'));
        $defineFields->returnType = new Identifier('FieldCollection');
        $defineFields->flags = Class_::MODIFIER_PROTECTED;

        $class->stmts[] = $entityName;
        $class->stmts[] = $defineFields;

        $namespace->stmts[] = $class;

        $useHelper->addUse(FieldCollection::class);

        return $namespace;
    }

    private function buildNamespaceFromExistingFile(string $entityFile, LoaderResult $loaderResult, UseHelper $useHelper): Namespace_
    {
        // Prepare useHelper

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

    /**
     * @param Field[] $fieldCollection
     */
    private function buildFieldCollection(array $fieldCollection, UseHelper $useHelper): Array_
    {
        $array = new Array_();

        foreach ($fieldCollection as $element) {
            $useHelper->addUse($element->name);

            $args = [];

            foreach ($element->args as $arg) {
                switch (gettype($arg)) {
                    case 'string':
                        $args[] = new Arg(new String_($arg));
                        break;
                    case 'integer':
                        $args[] = new Arg(new LNumber($arg));
                        break;
                    case 'NULL':
                        $args[] = new Arg(new ConstFetch(new Name('null')));
                        break;
                    case 'boolean':
                        $args[] = new Arg(new ConstFetch(new Name(strtolower((string) $arg))));
                        break;
                    default:
                        throw new \RuntimeException('Invalid type ' . gettype($arg));
                }
            }

            $field = new New_(new Name($useHelper->getShortName($element->name)), $args);

            if (!empty($element->flags)) {
                $args = [];

                foreach ($element->flags as $flag) {
                    $useHelper->addUse($flag);
                    $args[] = new Arg(new New_(new Name($useHelper->getShortName($flag))));
                }

                $field = new MethodCall($field, 'setFlags', $args);
            }

            $array->items[] = $field;
        }

        return $array;
    }
}

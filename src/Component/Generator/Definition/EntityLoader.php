<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\HttpKernel\KernelInterface;

class EntityLoader
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Node\Stmt\Use_[]
     */
    private $useFlags;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function load(string $bundle, string $entityName): LoaderResult
    {
        $bundle = $this->kernel->getBundle($bundle);

        $refClass = new \ReflectionClass(get_class($bundle));
        $dir = pathinfo($refClass->getFileName(), PATHINFO_DIRNAME) . '/Content/' . $entityName . '/';
        $entityFile = $dir . $entityName . 'Definition.php';

        $result = new LoaderResult();
        $result->entityName = $entityName;
        $result->namespace = $refClass->getNamespaceName() . '\\Content\\' . $entityName;
        $result->folder = $dir;

        if (!file_exists($entityFile)) {
            $result->fields = [];

            return $result;
        }

        $result->fields = $this->parseFile($entityFile);
        return $result;
    }

    private function parseFile(string $entityFile): array
    {
        $fields = [];
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $ast = $parser->parse(file_get_contents($entityFile));

        $nodeFinder = new NodeFinder();

        $this->useFlags = $nodeFinder->findInstanceOf($ast, Node\Stmt\Use_::class);

        $method = $nodeFinder->findFirst($ast, function (Node $node) {
            return $node instanceof ClassMethod && $node->name->name === 'defineFields';
        });

        if (! $method instanceof ClassMethod) {
            throw new \RuntimeException('Class does not implement defineFields');
        }

        /** @var Node\Expr\New_ $fieldCollection */
        $fieldCollection = $nodeFinder->findFirst([$method], function (Node $node) {
            return $node instanceof Node\Expr\New_ && (string) $node->class === 'FieldCollection';
        });

        /** @var Node\Expr\Array_ $array */
        $array = $fieldCollection->args[0]->value;

        /** @var Node\Expr\ArrayItem $item */
        foreach ($array->items as $item) {
            $flags = [];
            /** @var Node\Expr\New_ $exprNew */
            $exprNew = null;
            if ($item->value instanceof Node\Expr\MethodCall) {
                foreach ($item->value->args as $arg) {
                    $flags[] = $this->getFQCN((string) $arg->value->class);
                }
                $exprNew = $item->value->var;
            } else {
                $exprNew = $item->value;
            }

            $className = $this->getFQCN((string) $exprNew->class);
            $args = [];

            /** @var Node\Arg $arg */
            foreach ($exprNew->args as $arg) {
                switch (true) {
                    case $arg->value instanceof Node\Scalar\String_:
                        $args[] = (string) $arg->value->value;
                        break;
                    case $arg->value instanceof Node\Scalar\LNumber:
                        $args[] = (int) $arg->value->value;
                        break;
                    case $arg->value instanceof Node\Expr\ConstFetch:
                        $value = (string) $arg->value->name;
                        if ($value === 'null') {
                            $value = null;
                        } elseif ($value === 'false') {
                            $value = false;
                        } elseif ($value === 'true') {
                            $value = true;
                        }

                        $args[] = $value;
                        break;
                    default:
                        throw new \RuntimeException('Type not supported: ' . get_class($arg->value));
                }
            }

            $fields[] = new Field($className, $args, $flags);
        }

        return $fields;
    }

    public function getFQCN(string $name): string
    {
        foreach ($this->useFlags as $useFlag) {
            /** @var Node\Stmt\UseUse $item */
            foreach ($useFlag->uses as $item) {
                if (strpos((string) $item->name, '\\' . $name) !== false) {
                    return $item->name;
                }
            }
        }

        return $name;
    }
}

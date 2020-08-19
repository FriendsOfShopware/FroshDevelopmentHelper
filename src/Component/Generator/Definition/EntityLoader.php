<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Filesystem\Filesystem;

class EntityLoader
{
    /**
     * @var Node\Stmt\Use_[]
     */
    private $useFlags;

    public function load(string $class): DefinitionBuild
    {
        $result = new DefinitionBuild();
        $result->name = $this->normalizeName($class);
        $result->namespace = $this->normalizeNamespace($class);
        $result->fields = [];

        if (class_exists($class)) {
            $refClass = new \ReflectionClass($class);
            $folder = pathinfo($refClass->getFileName(), PATHINFO_DIRNAME);

            $result->folder = $folder . '/';
            $result->fields = $this->parseFile($refClass->getFileName());

            return $result;
        }

        $result->folder = $this->getNewEntityFolder($class);

        (new Filesystem())->mkdir($result->folder);

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
                    case $arg->value instanceof Node\Expr\ClassConstFetch:
                        $args[] = $arg->value->class . '::' . $arg->value->name;
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

    private function normalizeNamespace(string $class): string
    {
        $splitt = explode('\\', $class);
        unset($splitt[count($splitt) - 1]);

        $class = implode('\\', $splitt);

        if ($class[0] === '\\') {
            $class = substr($class, 1);
        }

        return $class;
    }

    private function normalizeName(string $class): string
    {
        $splitt = explode('\\', $class);
        $name = $splitt[count($splitt) - 1];
        return str_replace('Definition', '', $name);
    }

    private function getNewEntityFolder(string $namespace): string
    {
        global $classLoader;
        $prefixes = $classLoader->getPrefixesPsr4();

        $namespaceSplit = explode('\\', $namespace);
        unset($namespaceSplit[count($namespaceSplit)  -1 ]);
        $namespaceCount = count($namespaceSplit);
        $suffix = '';

        for ($i = 0; $i < $namespaceCount; $i++) {
            $loopNamespace = implode('\\', $namespaceSplit) . '\\';

            if (isset($prefixes[$loopNamespace])) {
                return $prefixes[$loopNamespace][0] . $suffix;
            }

            $index = count($namespaceSplit)  -1;
            $suffix .= $namespaceSplit[$index] . '/';

            unset($namespaceSplit[$index]);
        }

        throw new \RuntimeException(sprintf('Namespace "%s" doees not fit in all known namespaces', $namespace));
    }
}

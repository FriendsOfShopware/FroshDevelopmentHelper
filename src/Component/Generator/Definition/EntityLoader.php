<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Flag;
use Frosh\DevelopmentHelper\Component\Generator\Struct\TranslationDefinition;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class EntityLoader
{
    /**
     * @var Node\Stmt\Use_[]
     */
    private ?array $useFlags = null;

    public function __construct(private readonly DefinitionInstanceRegistry $instanceRegistry, private readonly Kernel $kernel)
    {
    }

    public function load(string $class, SymfonyStyle $io): Definition
    {
        $result = new Definition();
        $result->name = $this->normalizeName($class);
        $result->namespace = $this->normalizeNamespace($class);
        $result->fields = [];

        if (class_exists($result->getDefinitionClass())) {
            $refClass = new \ReflectionClass($result->getDefinitionClass());
            $folder = pathinfo($refClass->getFileName(), PATHINFO_DIRNAME);

            $result->folder = $folder . '/';
            $result->fields = $this->parseFile($refClass->getFileName());

            $translationDefinition = $result->getDefinitionName() . '_translation';

            // Find by dal registration
            if ($this->instanceRegistry->has($translationDefinition)) {
                $result->translation = TranslationDefinition::createFrom($this->load($this->instanceRegistry->getRepository($translationDefinition)->getDefinition()->getClass(), $io));
                $result->translation->parent = $result;
            } else {
                // Find by default name / folder
                $translation = $this->createDefaultTranslation($result);
                if (class_exists($translation->getDefinitionClass())) {
                    $result->translation = TranslationDefinition::createFrom($this->load($translation->getDefinitionClass(), $io));
                    $result->translation->parent = $result;
                }
            }

            return $result;
        }

        $result->folder = $this->getNewEntityFolder($class);

        (new Filesystem())->mkdir($result->folder);

        if ($io->confirm('This entity will need translations?')) {
            $result->translation = $this->createDefaultTranslation($result);
        }

        return $result;
    }

    private function parseFile(string $entityFile): array
    {
        $fields = [];
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $ast = $parser->parse(file_get_contents($entityFile));

        $nodeFinder = new NodeFinder();

        $this->useFlags = $nodeFinder->findInstanceOf($ast, Use_::class);

        $method = $nodeFinder->findFirst($ast, fn(Node $node) => $node instanceof ClassMethod && $node->name->name === 'defineFields');

        if (! $method instanceof ClassMethod) {
            throw new \RuntimeException('Class does not implement defineFields');
        }

        /** @var Node\Expr\New_ $fieldCollection */
        $fieldCollection = $nodeFinder->findFirst([$method], fn(Node $node) => $node instanceof New_ && (string) $node->class === 'FieldCollection');

        /** @var Node\Expr\Array_ $array */
        $array = $fieldCollection->args[0]->value;

        /** @var Node\Expr\ArrayItem $item */
        foreach ($array->items as $item) {
            $flags = [];
            /** @var Node\Expr\New_ $exprNew */
            $exprNew = null;
            if ($item->value instanceof MethodCall) {
                // Ignore remove flags
                if ((string) $item->value->name !== 'addFlags') {
                    continue;
                }

                foreach ($item->value->args as $arg) {
                    $flags[] = new Flag($this->getFQCN((string) $arg->value->class), $this->parserArgumentsToPhp($arg->value->args));
                }
                $exprNew = $item->value->var;
            } else {
                $exprNew = $item->value;
            }

            $className = $this->getFQCN((string) $exprNew->class);

            $fields[] = new Field($className, $this->parserArgumentsToPhp($exprNew->args), $flags);
        }

        return $fields;
    }

    private function parserArgumentsToPhp($element): array
    {
        $args = [];

        /** @var Node\Arg $arg */
        foreach ($element as $arg) {
            switch (true) {
                case $arg->value instanceof String_:
                    $args[] = (string) $arg->value->value;
                    break;
                case $arg->value instanceof LNumber:
                    $args[] = (int) $arg->value->value;
                    break;
                case $arg->value instanceof ClassConstFetch:
                    $args[] = $this->getFQCN($arg->value->class) . '::' . $arg->value->name;
                    break;
                case $arg->value instanceof ConstFetch:
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
                case $arg->value instanceof Array_:
                    $args[] = $this->parserArgumentsToPhp($arg->value->items);;
                    break;
                default:
                    throw new \RuntimeException('Type not supported: ' . $arg->value::class);
            }
        }

        return $args;
    }

    public function getFQCN(string $name): string
    {
        foreach ($this->useFlags as $useFlag) {
            /** @var Node\Stmt\UseUse $item */
            foreach ($useFlag->uses as $item) {
                if (str_contains((string) $item->name, '\\' . $name)) {
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
        $prefixes = $this->kernel->getPluginLoader()->getClassLoader()->getPrefixesPsr4();

        $namespaceSplit = explode('\\', $namespace);
        unset($namespaceSplit[count($namespaceSplit)  -1 ]);
        $namespaceCount = count($namespaceSplit);
        $suffix = [];

        for ($i = 0; $i < $namespaceCount; $i++) {
            $loopNamespace = implode('\\', $namespaceSplit) . '\\';

            if (isset($prefixes[$loopNamespace])) {
                return rtrim(rtrim((string) $prefixes[$loopNamespace][0],'/').'/' . implode('/', array_reverse($suffix)), '/') . '/';
            }

            $index = count($namespaceSplit)  -1;
            $suffix[] = $namespaceSplit[$index];

            unset($namespaceSplit[$index]);
        }

        throw new \RuntimeException(sprintf('Namespace "%s" doees not fit in all known namespaces', $namespace));
    }

    private function createDefaultTranslation(Definition $result): TranslationDefinition
    {
        $translation = new TranslationDefinition();
        $translation->name = $result->name . 'Translation';
        $translation->folder = $result->folder . 'Aggregate/' . $result->name . 'Translation/';
        $translation->namespace = $result->namespace . '\\Aggregate\\' . $result->name . 'Translation';
        $translation->fields = [];
        $translation->parent = $result;

        return $translation;
    }
}

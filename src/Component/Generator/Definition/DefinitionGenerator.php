<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\Struct\MappingDefinition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\TranslationDefinition;
use Frosh\DevelopmentHelper\Component\Generator\UseHelper;
use PhpParser\BuilderFactory;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class DefinitionGenerator
{
    use ParserBuilderTrait;

    public function generate(Definition $definition): void
    {
        if (!file_exists($definition->folder) && !mkdir($concurrentDirectory = $definition->folder, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        $useHelper = new UseHelper();

        if (file_exists($definition->getDefinitionFilePath())) {
            $namespace = $this->buildNamespaceFromExistingFile($definition->getDefinitionFilePath(), $useHelper);
        } else {
            $namespace = $this->buildNewNamespace($definition, $useHelper);
        }

        $nodeFinder = new NodeFinder();

        /** @var ClassMethod $method */
        $method = $nodeFinder->findFirst([$namespace], static fn(Node $node) => $node instanceof ClassMethod && $node->name->name === 'defineFields');

        $method->stmts = [new Return_(new New_(
            new Name('FieldCollection'),
            [
                new Arg($this->buildFieldCollection($definition->fields, $useHelper))
            ]
        ))];

        $namespace->stmts = array_merge($useHelper->getStms(), $namespace->stmts);

        $printer = new Standard();

        file_put_contents($definition->getDefinitionFilePath(), $printer->prettyPrintFile([$namespace]));
    }

    private function buildNewNamespace(Definition $definition, UseHelper $useHelper): Namespace_
    {
        $builder = new BuilderFactory();
        $namespace = new Namespace_(new Name($definition->namespace));

        $class = new Class_(new Identifier($definition->getDefinitionClassName()));
        $namespace->stmts[] = $class;

        if ($definition instanceof TranslationDefinition) {
            $class->extends = new Name('EntityTranslationDefinition');
            $useHelper->addUse(EntityTranslationDefinition::class);
        } elseif ($definition instanceof MappingDefinition) {
            $class->extends = new Name('MappingEntityDefinition');
            $useHelper->addUse(MappingEntityDefinition::class);
        } else {
            $class->extends = new Name('EntityDefinition');
            $useHelper->addUse(EntityDefinition::class);
        }

        $entityName = new ClassMethod(new Identifier('getEntityName'));
        $entityName->returnType = new Name('string');
        $entityName->flags = Class_::MODIFIER_PUBLIC;
        $entityName->stmts[] = new Return_(new String_($definition->getDefinitionName()));

        $defineFields = new ClassMethod(new Identifier('defineFields'));
        $defineFields->returnType = new Identifier('FieldCollection');
        $defineFields->flags = Class_::MODIFIER_PROTECTED;
        $useHelper->addUse(FieldCollection::class);

        $class->stmts[] = $entityName;
        $class->stmts[] = $defineFields;

        if ($definition instanceof MappingDefinition) {
            return $namespace;
        }

        $class->stmts[] = $builder->method('getEntityClass')
            ->makePublic()
            ->setReturnType(new Name('string'))
            ->addStmt(new Return_($builder->classConstFetch($definition->getEntityClassName(), 'class')))
            ->getNode();

        $class->stmts[] = $builder->method('getCollectionClass')
            ->makePublic()
            ->setReturnType(new Name('string'))
            ->addStmt(new Return_($builder->classConstFetch($definition->getCollectionClassName(), 'class')))
            ->getNode();

        if ($definition instanceof TranslationDefinition) {
            $class->stmts[] = $builder->method('getParentDefinitionClass')
                ->makePublic()
                ->setReturnType(new Name('string'))
                ->addStmt(new Return_($builder->classConstFetch('\\' . $definition->parent->getDefinitionClass(), 'class')))
                ->getNode();
        }

        return $namespace;
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

    /**
     * @param Field[] $fieldCollection
     */
    private function buildFieldCollection(array $fieldCollection, UseHelper $useHelper): Array_
    {
        $array = new Array_();

        foreach ($fieldCollection as $element) {
            $useHelper->addUse($element->name);

            $array->items[] = $this->buildField($element, $useHelper);
        }

        return $array;
    }
}

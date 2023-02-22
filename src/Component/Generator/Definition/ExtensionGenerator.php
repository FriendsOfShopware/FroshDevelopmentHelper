<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use PhpParser\Node\Stmt\Expression;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Definition;
use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\UseHelper;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\Filesystem\Filesystem;

class ExtensionGenerator
{
    use ParserBuilderTrait;

    public function generate(Definition $definition, Definition $reference, Field $field): void
    {
        $name = ucfirst($reference->getDefinitionName()) . 'Extension';
        $namespace = $definition->namespace . '\\Extensions';
        $folder = $definition->folder . '/Extensions/';
        $filePath = $folder . $name . '.php';

        (new Filesystem())->mkdir($folder);

        $useHelper = new UseHelper();

        if (file_exists($filePath)) {
            $namespace = $this->buildNamespaceFromExistingFile($filePath, $useHelper);
        } else {
            $namespace = $this->buildNewNamespace($name, $namespace, $reference->getDefinitionClass(), $useHelper);
        }

        $nodeFinder = new NodeFinder();
        $builder = new BuilderFactory();

        /** @var ClassMethod $method */
        $method = $nodeFinder->findFirst([$namespace], static fn(Node $node) => $node instanceof ClassMethod && $node->name->name === 'extendFields');

        $useHelper->addUse($field->name);
        $method->stmts[] = new Expression($builder->methodCall($builder->var('collection'), new Identifier('add'), [$this->buildField($field, $useHelper)]));

        $namespace->stmts = array_merge($useHelper->getStms(), $namespace->stmts);

        $printer = new Standard();

        file_put_contents($filePath, $printer->prettyPrintFile([$namespace]));
    }

    private function buildNewNamespace(string $name, string $namespace, string $referenceClass, UseHelper $useHelper): Namespace_
    {
        $useHelper->addUse(EntityExtension::class);
        $useHelper->addUse(FieldCollection::class);
        $useHelper->addUse($referenceClass);

        $builder = new BuilderFactory();
        $namespace = new Namespace_(new Name($namespace));

        $class = new Class_(new Identifier($name));
        $namespace->stmts[] = $class;

        $class->extends = new Name('EntityExtension');

        $class->stmts[] = $builder->method('getDefinitionClass')
            ->makePublic()
            ->setReturnType(new Name('string'))
            ->addStmt(new Return_($builder->classConstFetch($useHelper->getShortName($referenceClass), 'class')))
            ->getNode();

        $class->stmts[] = $builder->method('extendFields')
            ->makePublic()
            ->setReturnType(new Name('void'))
            ->addParam($builder->param('collection')->setType(new Name('FieldCollection')))
            ->getNode();

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
}

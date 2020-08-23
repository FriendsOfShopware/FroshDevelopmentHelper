<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\Definition;

use Frosh\DevelopmentHelper\Component\Generator\Struct\Field;
use Frosh\DevelopmentHelper\Component\Generator\UseHelper;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;

trait ParserBuilderTrait
{
    protected function buildField(Field $field, UseHelper $useHelper): Expr
    {
        $parserField = new New_(new Name($useHelper->getShortName($field->name)), $this->buildArgsForParser($field->args));

        if (!empty($field->flags)) {
            $args = [];

            foreach ($field->flags as $flag) {
                $useHelper->addUse($flag->name);
                $args[] = new Arg(new New_(new Name($useHelper->getShortName($flag->name)), $this->buildArgsForParser($flag->args)));
            }

            $parserField = new MethodCall($parserField, 'addFlags', $args);
        }

        return $parserField;
    }

    private function buildArgsForParser(array $elementArgs): array
    {
        $args = [];

        foreach ($elementArgs as $arg) {
            switch (gettype($arg)) {
                case 'string':
                    if (strpos($arg, '::class') !== false) {
                        $args[] = new Arg(new ClassConstFetch(new Name('\\' . substr($arg, 0, -7)), new Identifier('class')));
                    } elseif (strpos($arg, '::') !== false) {
                        [$firstPart, $secondPart] = explode('::', $arg, 2);
                        $args[] = new Arg(new ClassConstFetch(new Name('\\' . $firstPart), new Identifier($secondPart)));
                    } else {
                        $args[] = new Arg(new String_($arg));
                    }

                    break;
                case 'integer':
                    $args[] = new Arg(new LNumber($arg));
                    break;
                case 'NULL':
                    $args[] = new Arg(new ConstFetch(new Name('null')));
                    break;
                case 'boolean':
                    $args[] = new Arg(new ConstFetch(new Name($arg ? 'true' : 'false')));
                    break;
                default:
                    throw new \RuntimeException('Invalid type ' . gettype($arg));
            }
        }

        return $args;
    }
}

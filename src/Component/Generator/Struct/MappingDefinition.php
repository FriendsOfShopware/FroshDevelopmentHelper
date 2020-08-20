<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;

class MappingDefinition extends Definition
{
    public function getStorageNameByReference(string $class): string
    {
        $class .= '::class';

        foreach ($this->fields as $field) {
            if ($field->name === FkField::class && $field->args[2] === $class) {
                return $field->getStorageName();
            }
        }

        throw new \RuntimeException(sprintf('Cannot find FkField for class "%s"', $class));
    }
}

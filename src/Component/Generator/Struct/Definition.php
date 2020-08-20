<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Struct;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class Definition extends Struct
{
    /**
     * @var Field[]
     */
    public $fields = [];

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $namespace;

    /**
     * @var string
     */
    public $folder;

    /**
     * @var Definition|null
     */
    public $translation;

    public function getDefinitionName(): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->name);
    }

    public function getEntityClassName(): string
    {
        return $this->name . 'Entity';
    }

    public function getCollectionClassName(): string
    {
        return $this->name . 'Collection';
    }

    public function getCollectionClass(): string
    {
        return $this->namespace . '\\' . $this->getCollectionClassName();
    }

    public function getDefinitionClassName(): string
    {
        return $this->name . 'Definition';
    }

    public function getDefinitionClass(): string
    {
        return $this->namespace . '\\' . $this->getDefinitionClassName();
    }

    public function getEntityFilePath(): string
    {
        return $this->folder . $this->getEntityClassName() . '.php';
    }

    public function getCollectionFilePath(): string
    {
        return $this->folder . $this->getCollectionClassName() . '.php';
    }

    public function getDefinitionFilePath(): string
    {
        return $this->folder . $this->getDefinitionClassName() . '.php';
    }
}

<?php

namespace Frosh\DevelopmentHelper\Component\Generator\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;

class Field
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $args;

    /**
     * @var Flag[]
     */
    public $flags;

    /**
     * @var string|null
     */
    private $propertyName;

    /**
     * @var string|null
     */
    private $storageName;

    /**
     * @var string|null
     */
    private $referenceClass;

    /**
     * @var bool
     */
    public $translateable;

    public function __construct(string $name, array $args = [], array $flags = [], bool $translateable = false)
    {
        $this->name = $name;
        $this->args = $args;
        $this->flags = $flags;
        $this->translateable = $translateable;
    }

    public function isNullable(): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag->name === Required::class) {
                return false;
            }
        }

        return true;
    }

    public function getPropertyName(): string
    {
        if ($this->propertyName) {
            return $this->propertyName;
        }

        if ($this->name === CustomFields::class) {
            return 'customFields';
        }

        if ($this->name === TranslationsAssociationField::class) {
            return 'translations';
        }

        $ref = new \ReflectionClass($this->name);
        foreach ($ref->getConstructor()->getParameters() as $i => $parameter) {
            if ($parameter->name === 'propertyName') {
                return $this->propertyName = $this->args[$i];
            }
        }

        throw new \RuntimeException('Cannot find propertyName');
    }

    public function getStorageName(): ?string
    {
        if ($this->storageName) {
            return $this->storageName;
        }

        if (in_array($this->name, [OneToManyAssociationField::class, ManyToManyAssociationField::class])) {
            return $this->getReferenceClass();
        }

        if ($this->name === CustomFields::class) {
            return 'customFields';
        }

        $ref = new \ReflectionClass($this->name);
        foreach ($ref->getConstructor()->getParameters() as $i => $parameter) {
            if ($parameter->name === 'storageName') {
                return $this->storageName = $this->args[$i];
            }
        }

        throw new \RuntimeException('Cannot find storageName');
    }

    public function getReferenceClass(): string
    {
        if ($this->referenceClass) {
            return $this->referenceClass;
        }

        if ($this->name === CustomFields::class) {
            return 'custom_fields';
        }

        $ref = new \ReflectionClass($this->name);
        foreach ($ref->getConstructor()->getParameters() as $i => $parameter) {
            if ($parameter->name === 'referenceClass' || $parameter->name === 'referenceDefinition' || $parameter->name === 'toManyDefinitionClass') {
                return $this->referenceClass = $this->args[$i];
            }
        }

        throw new \RuntimeException('Cannot find referenceClass');
    }

    public function isStorageAware(): bool
    {
        return is_a($this->name, StorageAware::class, true);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArg(int $index)
    {
        return $this->args[$index] ?? null;
    }
}

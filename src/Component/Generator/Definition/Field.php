<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Definition;


use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;

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
     * @var array
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

    public function __construct(string $name, array $args = [], array $flags = [])
    {
        $this->name = $name;
        $this->args = $args;
        $this->flags = $flags;
    }

    public function isNullable(): bool
    {
        return !in_array(Required::class, $this->flags, true);
    }

    public function getPropertyName(): string
    {
        if ($this->propertyName) {
            return $this->propertyName;
        }

        $ref = new \ReflectionClass($this->name);
        foreach ($ref->getConstructor()->getParameters() as $i => $parameter) {
            if ($parameter->name === 'propertyName') {
                return $this->propertyName = $this->args[$i];
            }
        }

        throw new \RuntimeException('Cannot find propertyName');
    }

    public function getStorageName(): string
    {
        if ($this->storageName) {
            return $this->storageName;
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

        $ref = new \ReflectionClass($this->name);
        foreach ($ref->getConstructor()->getParameters() as $i => $parameter) {
            if ($parameter->name === 'referenceClass') {
                return $this->referenceClass = $this->args[$i];
            }
        }

        throw new \RuntimeException('Cannot find referenceClass');
    }


}

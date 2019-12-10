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
            if ($parameter->name === 'storageName') {
                return $this->propertyName = $this->args[$i];
            }
        }

        throw new \RuntimeException('Cannot find storageName');
    }


}

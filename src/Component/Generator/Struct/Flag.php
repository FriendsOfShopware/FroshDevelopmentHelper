<?php declare(strict_types=1);

namespace Frosh\DevelopmentHelper\Component\Generator\Struct;

class Flag
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $args;

    public function __construct(string $name, array $args = [])
    {
        $this->name = $name;
        $this->args = $args;
    }
}

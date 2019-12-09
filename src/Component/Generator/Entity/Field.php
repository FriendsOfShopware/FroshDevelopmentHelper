<?php


namespace Frosh\DevelopmentHelper\Component\Generator\Entity;


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

    public function __construct(string $name, array $args = [], array $flags = [])
    {
        $this->name = $name;
        $this->args = $args;
        $this->flags = $flags;
    }
}

<?php

namespace Frosh\DevelopmentHelper\Component\Generator;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

class UseHelper
{
    private array $alreadyAddedUses = [];
    private array $printUses = [];

    public function addUsage(string $use): void
    {
        $this->alreadyAddedUses[] = $use;
    }

    public function addUse(string $use): void
    {
        if (in_array($use, $this->alreadyAddedUses, true)) {
            return;
        }

        $this->alreadyAddedUses[] = $use;
        $this->printUses[] = $use;
    }

    /**
     * @return Use_[]
     */
    public function getStms(): array
    {
        return array_map(static fn(string $use) => new Use_([new UseUse(new Name($use))]), $this->printUses);
    }

    public function getShortName(string $name): string
    {
        $array = explode('\\', $name);

        return end($array);
    }
}

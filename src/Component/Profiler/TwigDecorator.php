<?php

namespace Frosh\DevelopmentHelper\Component\Profiler;

use Twig\Environment;

class TwigDecorator extends Environment
{
    private $renders = [];

    public function render(string $name, array $context = [])
    {
        if (strpos($name, 'WebProfiler') === false) {
            $this->renders[$name] = $context;
        }

        return parent::render($name, $context);
    }

    public function getTemplateData(): array
    {
        return $this->renders;
    }
}

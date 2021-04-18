<?php

namespace Frosh\DevelopmentHelper\Component\Profiler;

use Twig\Environment;

class TwigDecorator extends Environment
{
    private $renders = [];

    public function render($name, array $context = []): string
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

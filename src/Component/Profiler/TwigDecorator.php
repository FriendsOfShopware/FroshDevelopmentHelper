<?php

namespace Frosh\DevelopmentHelper\Component\Profiler;

use Twig\Environment;
use Twig\TemplateWrapper;

class TwigDecorator extends Environment
{
    private $renders = [];

    public function render($name, array $context = []): string
    {
        $template = $name;

        if ($name instanceof TemplateWrapper) {
            $name = $name->getTemplateName();
        }

        if (strpos($name, 'WebProfiler') === false) {
            $this->renders[$name] = $context;
        }

        return parent::render($template, $context);
    }

    public function getTemplateData(): array
    {
        return $this->renders;
    }
}

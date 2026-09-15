<?php

namespace Frosh\DevelopmentHelper\Component\Profiler;

use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\TemplateWrapper;

class TwigDecorator extends TwigEnvironment
{
    private array $renders = [];

    public function render($name, array $context = []): string
    {
        $template = $name;

        if ($name instanceof TemplateWrapper) {
            $name = $name->getTemplateName();
        }

        if (!str_contains((string) $name, 'WebProfiler')) {
            $this->renders[$name] = $context;
        }

        return parent::render($template, $context);
    }

    public function getTemplateData(): array
    {
        return $this->renders;
    }
}

<?php

namespace Frosh\DevelopmentHelper\Component\Profiler;

use Symfony\Bridge\Twig\DataCollector\TwigDataCollector as BaseTwigDataCollector;
use Twig\Environment;
use Twig\Profiler\Profile;

class TwigDataCollector extends BaseTwigDataCollector
{
    public function __construct(Profile $profile, private readonly Environment $twig)
    {
        parent::__construct($profile, $twig);
    }

    public function getTemplateData(): array
    {
        return $this->data['renders'];
    }

    public function lateCollect(): void
    {
        $this->data['renders'] = json_decode(json_encode($this->twig->getTemplateData()), true);

        parent::lateCollect();
    }
}

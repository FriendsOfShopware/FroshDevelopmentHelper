<?php

namespace Frosh\DevelopmentHelper\Component\Twig;

use Shopware\Storefront\Storefront;
use Symfony\Component\Finder\Finder;

class BlockCollector
{
    public function getBlocks(): array
    {
        $path = (new Storefront())->getPath() . '/Resources/views/';

        $finder = (new Finder())
            ->in($path)
            ->files()
            ->name('*.html.twig');

        $collectedBlocks = [];
        $regex = '/{%\s* block\s*([\w_]+)\s*%}/m';

        foreach ($finder->getIterator() as $file) {
            $fileContent =  file_get_contents($file->getPathname());
            preg_match_all($regex, $fileContent, $matches, PREG_SET_ORDER, 0);

            foreach ($matches as $match) {
                $collectedBlocks[$match[1]][str_replace($path, '', $file->getPathname())] = 1;
            }
        }

        return $collectedBlocks;
    }
}

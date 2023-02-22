<?php


namespace Frosh\DevelopmentHelper\Component\Twig\Extension;


use Frosh\DevelopmentHelper\Component\Twig\NodeVisitor\BlogCommentNodeVisitor;
use Twig\Extension\AbstractExtension;

class BlockCommentExtension extends AbstractExtension
{
    public function __construct(private readonly string $kernelRootDir, private readonly array $twigExcludeKeywords)
    {
    }

    /**
     * @return BlogCommentNodeVisitor[]
     */
    public function getNodeVisitors(): array
    {
        return [new BlogCommentNodeVisitor($this->kernelRootDir, $this->twigExcludeKeywords)];
    }
}

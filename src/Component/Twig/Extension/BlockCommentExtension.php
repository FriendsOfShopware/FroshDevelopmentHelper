<?php


namespace Frosh\DevelopmentHelper\Component\Twig\Extension;


use Frosh\DevelopmentHelper\Component\Twig\NodeVisitor\BlogCommentNodeVisitor;
use Twig\Extension\AbstractExtension;

class BlockCommentExtension extends AbstractExtension
{
    /**
     * @var string
     */
    private $kernelRootDir;

    private array $twigExcludeKeywords;

    public function __construct(string $kernelRootDir, array $twigExcludeKeywords)
    {
        $this->kernelRootDir = $kernelRootDir;
        $this->twigExcludeKeywords = $twigExcludeKeywords;
    }

    public function getNodeVisitors()
    {
        return [new BlogCommentNodeVisitor($this->kernelRootDir, $this->twigExcludeKeywords)];
    }
}

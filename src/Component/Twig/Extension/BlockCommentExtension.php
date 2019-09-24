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

    public function __construct(string $kernelRootDir)
    {
        $this->kernelRootDir = $kernelRootDir;
    }

    public function getNodeVisitors()
    {
        return [new BlogCommentNodeVisitor($this->kernelRootDir)];
    }
}

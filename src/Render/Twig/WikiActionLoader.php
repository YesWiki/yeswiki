<?php

namespace YesWiki\Render\Twig;

use Twig\Loader\LoaderInterface;
use Twig\Source;

/** Hands Twig each template with its wiki action calls compiled by WikiActionSyntax and its markdown, when marked as such, converted by MarkdownTemplate. */
final class WikiActionLoader implements LoaderInterface
{
    private const CACHE_PREFIX = 'wiki-actions-1:';

    /** @param \Closure(string): string $markdownToHtml */
    public function __construct(private readonly LoaderInterface $loader, private readonly \Closure $markdownToHtml)
    {
    }

    /** The Twig source that $code stands for. */
    public function compile(string $code): string
    {
        $rewritten = WikiActionSyntax::rewrite($code);

        return MarkdownTemplate::isMarked($rewritten) ? MarkdownTemplate::compile($rewritten, $this->markdownToHtml) : $rewritten;
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->loader->getSourceContext($name);

        return new Source($this->compile($source->getCode()), $source->getName(), $source->getPath());
    }

    public function getCacheKey(string $name): string
    {
        return self::CACHE_PREFIX . $this->loader->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->loader->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->loader->exists($name);
    }
}

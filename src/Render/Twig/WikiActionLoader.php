<?php

namespace YesWiki\Render\Twig;

use Twig\Loader\LoaderInterface;
use Twig\Source;

/** Hands Twig each template with its wiki action calls already compiled by WikiActionSyntax. */
final class WikiActionLoader implements LoaderInterface
{
    private const CACHE_PREFIX = 'wiki-actions-1:';

    public function __construct(private readonly LoaderInterface $loader)
    {
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->loader->getSourceContext($name);

        return new Source(WikiActionSyntax::rewrite($source->getCode()), $source->getName(), $source->getPath());
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

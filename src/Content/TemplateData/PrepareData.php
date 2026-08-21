<?php

namespace YesWiki\Content\TemplateData;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Performable\FormatsArguments;
use YesWiki\Kernel\Service\CurrentRequest;

/**
 * Arguments in, transformed arguments out, for one list template (ticket 49).
 *
 * `{{entrylist}}` parses everything every template shares. What is left is the handful of
 * templates that need more: a map turns marker settings into leaflet's vocabulary, a table
 * has to know the form's columns. That used to be a separate action per template, which
 * `{{entrylist}}` called and which called `{{entrylist}}` back with a class name in the
 * arguments to stop the loop. A preparer is the same work with the recursion removed, and it
 * composes: `map-and-table` runs the map's and the table's, in that order, over one array.
 *
 * Which template a preparer is for comes from its class name (`PrepareDataMap` prepares
 * `map`), plus `#[PreparesTemplate([...])]` for names a class name cannot hold. An extension
 * ships one for its own template by putting it in `<extension>/templatedata/`.
 */
abstract class PrepareData
{
    use FormatsArguments;

    public function __construct(protected ContainerInterface $services)
    {
    }

    /**
     * What this template needs on top of the shared arguments.
     *
     * @param array<string, mixed> $arguments everything `{{entrylist}}` has parsed so far,
     *                                        raw page arguments included
     *
     * @return array<string, mixed> the keys to add or replace, and nothing else: a preparer
     *                              that returns the whole array would have to know about
     *                              every key it does not care about
     */
    abstract public function prepare(array $arguments): array;

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T
     */
    protected function getService(string $className)
    {
        return $this->services->get($className);
    }

    protected function getRequest(): Request
    {
        return $this->services->get(CurrentRequest::class)->get();
    }

    /** A `baz_*` configuration default, which is what most of these arguments fall back to. */
    protected function config(string $key): mixed
    {
        return $this->services->get(ParameterBagInterface::class)->get($key);
    }
}

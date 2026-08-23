<?php

namespace YesWiki\Content\TemplateData;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Performable\FormatsArguments;
use YesWiki\Kernel\Service\CurrentRequest;

/** Arguments in, transformed arguments out, for one list template (ticket 49). */
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

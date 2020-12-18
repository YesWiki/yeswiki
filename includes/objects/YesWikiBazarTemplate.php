<?php

namespace YesWiki\Core;

abstract class YesWikiBazarTemplate extends YesWikiPerformable
{
    protected $twigTemplate;

    public function __construct(&$wiki, &$arguments, $twigTemplate)
    {
        parent::__construct($wiki);
        $this->arguments = &$arguments;
        $this->twigTemplate = $twigTemplate;
    }

    abstract public function prepare();

    public function run()
    {
        $this->prepare();

        return $this->render($this->twigTemplate, $this->arguments);
    }
}
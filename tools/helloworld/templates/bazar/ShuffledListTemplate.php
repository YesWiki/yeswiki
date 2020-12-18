<?php
use YesWiki\Core\YesWikiBazarTemplate;

class ShuffledListTemplate extends YesWikiBazarTemplate
{
    public function prepare()
    {
        // you can prepare your data model here by using services

        shuffle($this->arguments['fiches']);
    }
}
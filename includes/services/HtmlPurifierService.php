<?php

namespace YesWiki\Core\Service;

use HTMLPurifier;
use HTMLPurifier_Config;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Wiki;

class HtmlPurifierService
{
    protected $params;
    protected $wiki;
    private $purifier;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->wiki = $wiki;
        $this->purifier = null;
    }

    /**
     * load a HTMLpurifier if needed
     * configure it
     *  then use it to clean HTML
     */
    public function cleanHTML(string $dirty_html): string
    {
        if (!$this->params->get('htmlPurifierActivated')) {
            return $dirty_html;
        }
        if (is_null($this->purifier)) {
            $this->load();
        }

        return $this->purifier->purify($dirty_html);
    }

    private function load()
    {
        $config = HTMLPurifier_Config::createDefault();
        $this->purifier = new HTMLPurifier($config);
    }
}

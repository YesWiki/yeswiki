<?php

namespace YesWiki\Aceditor;

use Symfony\Component\Yaml\Yaml;
use YesWiki\Aceditor\Service\ActionsBuilderService;
use YesWiki\Core\YesWikiAction;

class AceditorAction extends YesWikiAction
{
    public function run()
    {
        $this->wiki->AddJavascriptFile('tools/aceditor/presentation/javascripts/aceditor.js', false, true);
        $this->wiki->AddCSSFile('tools/aceditor/presentation/styles/aceditor.css');

        $data = $this->getService(ActionsBuilderService::class)->getData();

        return $this->render('@aceditor/actions-builder.twig', ['data' => $data]);
    }
}
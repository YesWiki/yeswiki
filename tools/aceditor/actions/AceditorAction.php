<?php

namespace YesWiki\Aceditor;

use Symfony\Component\Yaml\Yaml;
use YesWiki\Aceditor\Service\ActionsBuilderService;
use YesWiki\Core\YesWikiAction;

class AceditorAction extends YesWikiAction
{
    public function formatArguments($args)
    {
        return [
            'name' => $args['name'],
            'value' => $args['value'],
            'saveButton' => $this->formatBoolean($args['saveButton'], false)
        ];
    }

    public function run()
    {
        $data = $this->getService(ActionsBuilderService::class)->getData();

        return $this->render('@aceditor/aceditor.twig', ['actionsBuilderData' => $data]);
    }
}
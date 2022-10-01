<?php

namespace YesWiki\Aceditor;

use Symfony\Component\Yaml\Yaml;
use YesWiki\Aceditor\Service\ActionsBuilderService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\PageManager;

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
        $pageTags = $this->getService(PageManager::class)->getReadablePageTags();

        return $this->render('@aceditor/aceditor.twig', [
            'actionsBuilderData' => $data,
            'pageTags' => $pageTags
        ]);
    }
}
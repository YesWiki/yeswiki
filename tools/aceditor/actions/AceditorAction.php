<?php

namespace YesWiki\Aceditor;

use YesWiki\Aceditor\Service\ActionsBuilderService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Security\Controller\SecurityController;

class AceditorAction extends YesWikiAction
{
    public function formatArguments($args)
    {
        return [
            'name' => $args['name'] ?? 'aceditor',
            'value' => $args['value'] ?? '',
            'placeholder' => $args['placeholder'] ?? '',
            'rows' => $args['rows'] ?? 3,
            'tempTag' => $args['tempTag'] ?? null, // used in new entry form
            'saveButton' => $this->formatBoolean($args['saveButton'] ?? null, false),
        ];
    }

    public function run()
    {
        $data = $this->getService(ActionsBuilderService::class)->getData();
        $pageTags = $this->getService(PageManager::class)->getReadablePageTags();

        return $this->render('@aceditor/aceditor.twig', [
            'actionsBuilderData' => $data,
            'pageTags' => $pageTags,
            'saveValue' => SecurityController::EDIT_PAGE_SUBMIT_VALUE,
        ]);
    }
}

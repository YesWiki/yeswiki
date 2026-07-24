<?php

use YesWiki\Core\Controller\SecurityController;
use YesWiki\Core\Service\ActionsBuilderService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiAction;

class AceditorAction extends YesWikiAction
{
    public function formatArguments($args): array
    {
        return [
            'name' => $args['name'] ?? 'aceditor',
            'value' => $args['value'] ?? '',
            'required' => $args['required'] ?? null,
            'placeholder' => $args['placeholder'] ?? '',
            'rows' => $args['rows'] ?? 3,
            'maxChars' => $args['maxChars'] ?? null,
            'tempTag' => $args['tempTag'] ?? null, // used in new entry form
            'saveButton' => $this->formatBoolean($args['saveButton'] ?? null, false),
        ];
    }

    public function run(): string
    {
        $data = $this->getService(ActionsBuilderService::class)->getData();
        $pageTags = $this->getService(PageManager::class)->getReadablePageTags();

        return $this->render('@core/aceditor.twig', [
            'actionsBuilderData' => $data,
            'pageTags' => $pageTags,
            'saveValue' => SecurityController::EDIT_PAGE_SUBMIT_VALUE,
        ]);
    }
}

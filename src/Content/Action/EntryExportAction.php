<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\CSVManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Search\Service\SearchManager;

class EntryExportAction extends YesWikiAction implements RegisteredAction
{
    /** `{{entryexport}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entryexport';
    }

    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $get = $request->query;
        $vIDs = $request->get('form_id') ?? $request->get('id') ?? $arg['id'] ?? $arg['id'] ?? '';

        $vIDs = $this->getService(BazarListService::class)->getIDs($vIDs);

        return [
            'id' => $vIDs,
            'keywords' => $this->getService(SearchManager::class)->aggregateKeywords($get->get('q'), $get->get('keywords')),
            'query' => $get->get('query'),
            'bazar-export-option-keys-instead-of-values' => $this->formatBoolean($get->all() + $request->request->all(), false, 'bazar-export-option-keys-instead-of-values'),
            'params' => array_merge(
                [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_EXPORT],
                $get->has('debug') ? ['debug' => 'yes'] : []
            ),
        ];
    }

    /** @return string */
    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL(false))) {
            return $aclMessage;
        }

        $csvManager = $this->getService(CSVManager::class);

        $vForms = $this->getService(FormManager::class)->getAll();

        $vTheID = $this->getService(BazarListService::class)->getTheID($this->arguments['id'], false);

        if ($vTheID) {
            $vID = $vTheID['id'];

            $vRefresh = $this->arguments['refresh'] ?? $this->getRequest()->query->get('refresh', 'false');
            $vRefresh = ($vRefresh == 'true' || $vRefresh == '1') ? true : false;

            $vSelectedForm = $vForms[$vID];

            $csv_raw = $csvManager->getCSVfromFormId(
                $vID,
                [
                    'query' => $this->arguments['query'],
                    'keywords' => $this->arguments['keywords'],
                ],
                [
                    'fakeMode' => false,
                    'keysInsteadOfValues' => $this->arguments['bazar-export-option-keys-instead-of-values'],
                ]
            );

            $vFilename = $csvManager->buildExportFilename($vTheID);
        } else {
            $vSelectedForm = null;
        }

        return $this->render('@core/bazar-export.twig', [
            'id' => $vID ?? null,
            'forms' => $vForms,
            'params' => $this->arguments['params'],
            'filename' => $vFilename ?? null,
            'selectedForm' => $vSelectedForm,
            'csv' => !empty($csv_raw) ? $csvManager->arrayToCSVToDisplay($csv_raw) : null,
            'nbEntries' => !empty($csv_raw) ? count($csv_raw) - 1 : 0,
            'optionKeysInsteadOfValuesChecked' => $this->arguments['bazar-export-option-keys-instead-of-values'],
        ]);
    }
}

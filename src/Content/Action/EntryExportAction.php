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

    private $CSVManager;
    private $formManager;
    private $bazarListService;

    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $get = $request->query;
        $vIDs = $request->get('form_id') ?? $request->get('id') ?? $arg['id'] ?? $arg['id'] ?? '';

        // get services
        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vIDs = $this->bazarListService->getIDs($vIDs);

        return [
            'id' => $vIDs ?? null,
            'keywords' => $this->getService(SearchManager::class)->aggregateKeywords($get->get('q'), $get->get('keywords')),
            'query' => $get->get('query'),
            'bazar-export-option-keys-instead-of-values' => $this->formatBoolean($get->all() + $request->request->all(), false, 'bazar-export-option-keys-instead-of-values'),
            'params' => array_merge(
                [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_EXPORT],
                $get->has('debug') ? ['debug' => 'yes'] : []
            ),
        ];
    }

    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL(false))) {
            return $aclMessage;
        }

        // get services
        $this->CSVManager = $this->getService(CSVManager::class);
        $this->formManager = $this->getService(FormManager::class);
        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vForms = $this->formManager->getAll();

        // get CSV

        $csvraw = null;

        $vTheID = $this->bazarListService->getTheID($this->arguments['id'], false);

        if ($vTheID) {
            $vID = $vTheID['id'];

            $vRefresh = $this->arguments['refresh'] ?? $this->getRequest()->query->get('refresh', 'false');
            $vRefresh = ($vRefresh == 'true' || $vRefresh == '1') ? true : false;

            $vSelectedForm = $vForms[$vID];

            $csv_raw = $this->CSVManager->getCSVfromFormId(
                $vID,
                [
                    'query' => $this->arguments['query'],
                    'keywords' => $this->arguments['keywords'],
                ],
                [
                    'fakeMode' => false, // No fake CSV
                    'keysInsteadOfValues' => $this->arguments['bazar-export-option-keys-instead-of-values'],
                ]
            );

            $vFilename = $this->CSVManager->buildExportFilename($vTheID);
        } else {
            // get Forms
            $vSelectedForm = null;
        }

        return $this->render('@core/bazar-export.twig', [
            'id' => $vID ?? null,
            'forms' => $vForms,
            'params' => $this->arguments['params'],
            'filename' => $vFilename ?? null,
            'selectedForm' => $vSelectedForm,
            'csv' => !empty($csv_raw) ? $this->CSVManager->arrayToCSVToDisplay($csv_raw) : null,
            'nbEntries' => !empty($csv_raw) ? count($csv_raw) - 1 : 0,
            'optionKeysInsteadOfValuesChecked' => $this->arguments['bazar-export-option-keys-instead-of-values'],
        ]);
    }
}

<?php

use YesWiki\Admin\Controller\WebhooksController;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiAction;

/**
 * After-callback on {{bazar}} (ticket 20, formerly the webhooks extension's
 * chained action): shows the outgoing-webhooks admin form under the forms list,
 * and records incoming test payloads on the test-webhook view.
 */
class BazarAction__ extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
            BazarAction::VARIABLE_ACTION => $_GET[BazarAction::VARIABLE_ACTION] ?? $arg[BazarAction::VARIABLE_ACTION] ?? null,
            BazarAction::VARIABLE_VOIR => $_GET[BazarAction::VARIABLE_VOIR] ?? $arg[BazarAction::VARIABLE_VOIR] ?? BazarAction::VOIR_DEFAUT,
            'redirecturl' => $arg['redirecturl'] ?? '',
        ];
    }

    public function run()
    {
        $tripleStore = $this->getService(TripleStore::class);
        $webhooksController = $this->getService(WebhooksController::class);

        $view = $this->arguments[BazarAction::VARIABLE_VOIR];

        switch ($view) {
            // Display webhooks form after the forms list
            case BazarAction::VOIR_FORMULAIRE:
                if (!isset($_GET['action'])) {
                    return $webhooksController->viewWebhooksForm();
                }
                break;

                // Incoming webhook for tests
            case WebhooksController::VUE_TEST:
                $tripleStore->create(
                    $this->wiki->GetPageTag(),
                    WebhooksController::VOCABULARY_TEST,
                    file_get_contents('php://input'),
                    '',
                    ''
                );
                break;
        }
    }
}

<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;

class QrscanAction extends YesWikiAction implements RegisteredAction
{
    /** `{{qrscan}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'qrscan';
    }

    /** @return string */
    public function run()
    {
        $entryManager = $this->getService(EntryManager::class);

        $relation = $this->getService(PerformableArguments::class)->get('relation');
        if (empty($relation)) {
            $relation = $this->getService(RuntimeConfig::class)['qrcode_config']['default_relation_type'];
        }
        $entityType = $this->getService(PerformableArguments::class)->get('entity');
        if (empty($entityType)) {
            $entityType = $this->getService(RuntimeConfig::class)['qrcode_config']['default_entity_type'];
        }
        $entityForm = $this->getService(PerformableArguments::class)->get('entityform');
        if (empty($entityForm)) {
            $entityForm = $this->getService(RuntimeConfig::class)['qrcode_config']['default_entity_form'];
        }
        $speak = $this->getService(PerformableArguments::class)->get('speak');
        if ($speak == '0' or $speak == 'false' or $speak == 'no') {
            $speak = 'false';
        } else {
            $speak = 'true';
        }

        $entity = null;
        $output = '';
        if (!empty($_REQUEST[$entityType])) {
            $entity = $entryManager->getOne($_REQUEST[$entityType]);
            if (!isset($entity['form_id']) || $entity['form_id'] !== $entityForm) {
                $entity = null;
                $output .= $this->render('@core/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('QRSCAN_NOT_GOOD_FORM_ID') . ' (' . $entityType . ' - ' . $entityForm . ').',
                ]);
            }
        }

        $output .= $this->render('@core/qrscan.twig', [
            'speak' => $speak,
            'relation' => $relation,
            'entity' => $entity,
        ]);

        return $output;
    }
}

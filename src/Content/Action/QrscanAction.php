<?php

namespace YesWiki\Content\Action;
/**
 * Qrscan action for yeswiki, for scanning a qrcode pair and save their relation in a bazar entry.
 *
 * @category Wiki
 *
 * @author   2018-2021 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU AFFERO GENERAL PUBLIC LICENSE version 3
 *
 * @see     https://yeswiki.net
 */

use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class QrscanAction extends YesWikiAction implements RegisteredAction
{
    /** `{{qrscan}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'qrscan';
    }

    public function run()
    {
        // Services init
        $entryManager = $this->wiki->services->get(EntryManager::class);

        // Parameters init
        $relation = $this->wiki->getParameter('relation');
        if (empty($relation)) {
            $relation = $this->wiki->config['qrcode_config']['default_relation_type'];
        }
        $entityType = $this->wiki->getParameter('entity');
        if (empty($entityType)) {
            $entityType = $this->wiki->config['qrcode_config']['default_entity_type'];
        }
        $entityForm = $this->wiki->getParameter('entityform');
        if (empty($entityForm)) {
            $entityForm = $this->wiki->config['qrcode_config']['default_entity_form'];
        }
        $speak = $this->wiki->getParameter('speak');
        if ($speak == '0' or $speak == 'false' or $speak == 'no') {
            $speak = 'false';
        } else {
            $speak = 'true';
        }

        $entity = null;
        $output = '';
        if (!empty($_REQUEST[$entityType])) {
            // check if value exist in database
            $entity = $entryManager->getOne($_REQUEST[$entityType]);
            if (!isset($entity['form_id']) || $entity['form_id'] !== $entityForm) {
                // if an entry is found that is not from the good type, we empty it
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

<?php

/**
 * Qrscan action for yeswiki, for scanning a qrcode pair and save their relation in a bazar entry
 *
 * @category Wiki
 * @package  YesWikiQrcode
 * @author   2018-2021 Florian Schmitt <mrflos@lilo.org>
 * @license  GNU AFFERO GENERAL PUBLIC LICENSE version 3
 * @link     https://yeswiki.net
 */

use YesWiki\Core\YesWikiAction;
use YesWiki\Bazar\Service\EntryManager;

class QrscanAction extends YesWikiAction
{
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
            if (!isset($entity['id_typeannonce']) || $entity['id_typeannonce'] !== $entityForm) {
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
            'entity' => $entity
        ]);
        return $output;
    }
}

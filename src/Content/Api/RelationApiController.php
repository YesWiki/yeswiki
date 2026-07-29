<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\UrlFormatter;

class RelationApiController extends YesWikiController
{
    /**
     * List qrcode relations (ticket 14, formerly yeswiki-extension-qrcode's own ApiController) --
     * pairs of Bazar entries linked via {{qrscan}}'s paired QR-code scanning flow.
     */
    #[Route('/api/relations/{type}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllRelations(string $type = 'contact')
    {
        $entryCache = [];
        $options = [
            'formsIds' => $this->wiki->config['qrcode_config']['relation_form_id'],
        ];
        $query = $this->getService(EntryController::class)
                ->formatQuery(empty($type) ? [] : ['query' => ['bf_relation' => $type]], $_GET);
        if (!empty($query)) {
            $options['queries'] = $query;
        }
        $entries = $this->getService(EntryManager::class)->search($options, true, true);
        foreach ($entries as $k => $e) {
            $entryCache[$e['bf_fiche1']] = isset($entryCache[$e['bf_fiche1']]) ?
                $entryCache[$e['bf_fiche1']] :
                $this->getService(EntryManager::class)->getOne($e['bf_fiche1']);
            $entryCache[$e['bf_fiche2']] = isset($entryCache[$e['bf_fiche2']]) ?
                $entryCache[$e['bf_fiche2']] :
                $this->getService(EntryManager::class)->getOne($e['bf_fiche2']);
            $entries[$k]['entry1'] = $entryCache[$e['bf_fiche1']];
            $entries[$k]['entry2'] = $entryCache[$e['bf_fiche2']];
        }

        return new ApiResponse(empty($entries) ? null : $entries);
    }

    /**
     * Create a qrcode relation entry linking two scanned Bazar entries (ticket 14).
     */
    #[Route('/api/relations', methods: ['POST'], options: ['acl' => ['public']])]
    public function createRelation()
    {
        $_POST['antispam'] = 1;
        $entry = $this->getService(EntryManager::class)->create(
            $this->wiki->config['qrcode_config']['relation_form_id'],
            $_POST,
            false,
            $_SERVER['HTTP_SOURCE_URL'] ?? null
        );
        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new ApiResponse(
            ['success' => $this->getService(UrlFormatter::class)->href('', $entry['tag'])],
            Response::HTTP_CREATED
        );
    }
}

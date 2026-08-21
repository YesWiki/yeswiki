<?php

namespace YesWiki\Search\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Search\Service\TagsManager;

class TagApiController extends YesWikiController
{
    #[Route('/api/tags', methods: ['GET'], options: ['acl' => ['public']])]
    public function getTags(Request $request): ApiResponse
    {
        $perpage = max(1, min((int)$request->query->get('perpage', 20), 100));
        $page = max(1, (int)$request->query->get('page', 1));

        $result = $this->getService(TagsManager::class)->search(
            (string)$request->query->get('search', ''),
            $perpage,
            ($page - 1) * $perpage
        );

        return new ApiResponse([
            'tags' => $result['tags'],
            'total' => $result['total'],
            'page' => $page,
            'perpage' => $perpage,
        ]);
    }
}

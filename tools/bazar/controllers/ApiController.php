<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Service\FicheManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/form")
     */
    public function getAllForms()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(FormManager::class)->getAll());
    }

    /**
     * @Route("/api/form/{formId}")
     */
    public function getForm($formId)
    {
        $this->denyAccessUnlessAdmin();

        $form = $this->getService(FormManager::class)->getOne($formId);
        if( !$form ) throw new NotFoundHttpException();

        return new ApiResponse($form);
    }

    /**
     * @Route("/api/fiche/{formId}")
     */
    public function getAllEntries($formId)
    {
        if( strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false) {
            return $this->getAllSemanticEntries($formId);
        }

        $entries = $this->getService(FicheManager::class)->search([ 'formsIds'=>$formId ]);

        return new ApiResponse($entries);
    }

    /**
     * @Route("/api/fiche/{formId}/json-ld")
     */
    public function getAllSemanticEntries($formId)
    {
        $entries = $this->getService(FicheManager::class)->search([ 'formsIds'=>$formId ]);

        // Put data inside LDP container
        $form = $this->getService(FormManager::class)->getOne($formId);

        return new ApiResponse([
                '@context' => (array) json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'],
                '@id' => $this->wiki->Href('fiche/' . $formId, 'api'),
                '@type' => [ 'ldp:Container', 'ldp:BasicContainer' ],
                'dcterms:title' => $form['bn_label_nature'],
                'ldp:contains' => array_map(function ($entry) {
                    $resource = $GLOBALS['wiki']->services->get(SemanticTransformer::class)->convertToSemanticData($entry['id_typeannonce'], $entry, true);
                    unset($resource['@context']);
                    return $resource;
                }, array_values($entries)),
            ],
            Response::HTTP_OK,
            ['Content-Type: application/ld+json; charset=UTF-8']
        );
    }

    /**
     * @Route("/api/fiche/url/{sourceUrl}")
     */
    public function getEntryUrl($sourceUrl)
    {
        $triples = $this->getService(TripleStore::class)->getMatching(null, 'http://outils-reseaux.org/_vocabulary/sourceUrl', urldecode($sourceUrl));
        if( !$triples ) throw new NotFoundHttpException();

        $resources = array_map(function ($triple) {
            return $this->wiki->Href('', $triple['resource']);
        }, $triples);

        return new ApiResponse($resources);
    }

    /**
     * @Route("/api/fiche/{formId}", methods={"POST"})
     */
    public function createEntry($formId)
    {
        if( strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false ) {
            $this->createSemanticEntry($formId);
        };

        $_POST['antispam'] = 1;
        $entry = $this->getService(FicheManager::class)->create($formId, $_POST, false, $_SERVER['HTTP_SOURCE_URL']);

        if (!$entry) throw new BadRequestHttpException();

        return new ApiResponse(
            ['success' => $this->wiki->Href('', $entry['id_fiche'])],
            Response::HTTP_CREATED
        );
    }

    /**
     * @Route("/api/fiche/{formId}/json-ld", methods={"POST"})
     */
    public function createSemanticEntry($formId)
    {
        $_POST['antispam'] = 1;
        $entry = $this->getService(FicheManager::class)->create($formId, $_POST, true, $_SERVER['HTTP_SOURCE_URL']);

        if (!$entry) throw new BadRequestHttpException();

        return new Response('', Response::HTTP_CREATED, [
            'Link: <http://www.w3.org/ns/ldp#Resource>; rel="type"',
            'Location: ' . $this->wiki->Href('', $entry['id_fiche'])
        ]);
    }
}

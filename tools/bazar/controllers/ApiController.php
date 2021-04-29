<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/forms")
     */
    public function getAllForms()
    {
        $this->denyAccessUnlessAdmin();

        return new ApiResponse($this->getService(FormManager::class)->getAll());
    }

    /**
     * @Route("/api/forms/{formId}")
     */
    public function getForm($formId)
    {
        $this->denyAccessUnlessAdmin();

        $form = $this->getService(FormManager::class)->getOne($formId);
        if (!$form) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse($form);
    }

    /**
     * @Route("/api/forms/{formId}/entries/{output}/{selectedEntries}", methods={"GET"},options={"acl":{"public"}})
     */
    public function getAllFormEntries($formId, $output = null, $selectedEntries = null)
    {
        $entries = $this->getService(EntryManager::class)->search([
            'formsIds' => $formId,
            'queries' => !empty($selectedEntries) ? ['id_fiche' => $selectedEntries] : [],
        ]);

        if ($output == 'json-ld' || strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false) {
            return $this->getAllSemanticEntries($formId, $entries);
        } // add entries in html format if asked
        elseif ($output == 'html') {
            foreach ($entries as $id => $entry) {
                $entries[$id]['html_output'] = $this->getService(EntryController::class)->view($entry, '', 0);
            }
        }
        return new ApiResponse($entries);
    }

    /**
     * @Route("/api/entries/{output}/{selectedEntries}", methods={"GET"})
     */
    public function getAllEntries($output = null, $selectedEntries = null)
    {
        $entries = $this->getService(EntryManager::class)->search([
            'queries' => !empty($selectedEntries) ? ['id_fiche' => $selectedEntries] : [],
        ]);

        if ($output == 'json-ld' || strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false) {
            return $this->getAllSemanticEntries($formId, $entries);
        } // add entries in html format if asked
        elseif ($output == 'html') {
            foreach ($entries as $id => $entry) {
                $entries[$id]['html_output'] = $this->getService(EntryController::class)->view($entry, '', 0);
            }
        }
        return new ApiResponse($entries);
    }

    public function getAllSemanticEntries($formId, $entries)
    {
        // Put data inside LDP container
        $form = $this->getService(FormManager::class)->getOne($formId);

        return new ApiResponse(
            [
            '@context' => (array)json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'],
            '@id' => $this->wiki->Href('fiche/' . $formId, 'api'),
            '@type' => ['ldp:Container', 'ldp:BasicContainer'],
            'dcterms:title' => $form['bn_label_nature'],
            'ldp:contains' => array_map(function ($entry) use ($form) {
                $resource = $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $entry, true);
                unset($resource['@context']);
                return $resource;
            }, array_values($entries)),
        ],
            Response::HTTP_OK,
            ['Content-Type: application/ld+json; charset=UTF-8']
        );
    }

    /**
     * @Route("/api/entry/url/{sourceUrl}")
     */
    public function getEntryUrl($sourceUrl)
    {
        $triples = $this->getService(TripleStore::class)->getMatching(
            null,
            'http://outils-reseaux.org/_vocabulary/sourceUrl',
            urldecode($sourceUrl)
        );
        if (!$triples) {
            throw new NotFoundHttpException();
        }

        $resources = array_map(function ($triple) {
            return $this->wiki->Href('', $triple['resource']);
        }, $triples);

        return new ApiResponse($resources);
    }

    /**
     * @Route("/api/entries/{formId}", methods={"POST"})
     */
    public function createEntry($formId)
    {
        if (strpos($_SERVER['CONTENT_TYPE'], 'application/ld+json') !== false) {
            $this->createSemanticEntry($formId);
        };

        $_POST['antispam'] = 1;
        $entry = $this->getService(EntryManager::class)->create($formId, $_POST, false, $_SERVER['HTTP_SOURCE_URL']);

        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new ApiResponse(
            ['success' => $this->wiki->Href('', $entry['id_fiche'])],
            Response::HTTP_CREATED
        );
    }

    /**
     * @Route("/api/entries/{formId}/json-ld", methods={"POST"})
     */
    public function createSemanticEntry($formId)
    {
        $_POST['antispam'] = 1;
        $entry = $this->getService(EntryManager::class)->create($formId, $_POST, true, $_SERVER['HTTP_SOURCE_URL']);

        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new Response('', Response::HTTP_CREATED, [
            'Link: <http://www.w3.org/ns/ldp#Resource>; rel="type"',
            'Location: ' . $this->wiki->Href('', $entry['id_fiche'])
        ]);
    }

    /**
     * Display Auth api documentation
     *
     * @return string
     */
    public function getDocumentation()
    {
        $output = '<h2>Bazar</h2>' . "\n";

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms') . '</code></b><br />
        Retourne la liste de tous les formulaires Bazar.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}') . '</code></b><br />
        Retourne les informations sur le formulaire <code>formId</code>.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', '{pageTag}') . '</code></b><br />
        Si le header <code>Accept</code> est <code>application/json</code>, retourne la fiche au format JSON.<br />
        Si le header <code>Accept</code> est <code>application/ld+json</code>, retourne la fiche au format JSON-LD.<br />
        </p>';

        $output .= '
        <p>
        <b><code>PUT ' . $this->wiki->href('', '{pageTag}') . '</code></b><br />
        Si le header <code>Content-Type</code> est <code>application/json</code>, modifie la fiche selon le JSON fourni.<br />
        Si le header <code>Content-Type</code> est <code>application/ld+json</code>, modifie la fiche selon le JSON-LD fourni.<br />
        </p>';

        $output .= '
        <p>
        <b><code>DELETE ' . $this->wiki->href('', '{pageTag}') . '</code></b><br />
        Supprime la fiche Bazar.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries') . '</code></b><br />
        Obtenir la liste des fiches de tous les formulaires Bazar.<br />
        Si le header <code>Accept</code> est <code>application/ld+json</code>, le JSON retourné sera au format sémantique (container LDP)
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code><br />
        Si le header <code>Accept</code> est <code>application/ld+json</code>, le JSON retourné sera au format sémantique (container LDP)
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/json-ld') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format sémantique (container LDP)<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/{formId}/html') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format json, avec la représentation html de la fiche dans le champ <code>html_output</code><br />
        </p>';

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/entries/{formId}') . '</code></b><br />
        Créer une nouvelle fiche en utilisant le formulaire <code>formId</code><br />
        Si le header <code>Content-Type</code> est <code>application/ld+json</code>, un JSON sémantique est attendu.
        </p>';

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/entries/{formId}/json-ld') . '</code></b><br />
        Créer une nouvelle fiche de type <code>formId</code> au format sémantique<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entry/url/{sourceUrl}') . '</code></b><br />
        Retourne l\'URL de la page Wiki synchronisée avec <code>sourceUrl</code><br />
        </p>';

        return $output;
    }
}

<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/forms", methods={"GET"},options={"acl":{"public"}})
     * @Route("/api/forms/", methods={"GET"},options={"acl":{"public"}})
     */
    public function getAllForms()
    {
        $forms = $this->getService(FormManager::class)->getAll();

        return new ApiResponse(empty($forms) ? null : $forms);
    }

    /**
     * @Route("/api/forms/{formId}", methods={"GET"},options={"acl":{"public"}})
     * @Route("/api/forms/{formId}/", methods={"GET"},options={"acl":{"public"}})
     */
    public function getForm($formId)
    {
        $form = $this->getService(FormManager::class)->getOne($formId);
        if (!$form || !isset($form['bn_id_nature'])) {
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
            'queries' => $this->getService(EntryController::class)
                ->formatQuery(!empty($selectedEntries) ? ['query' => ['id_fiche' => $selectedEntries]] : [], $_GET),
            'minDate' => $_GET['dateMin'] ?? '',
        ], true, true);

        if ($output == 'json-ld' || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/ld+json') !== false)) {
            return $this->getAllSemanticEntries($formId, $entries);
        } // add entries in html format if asked
        elseif ($output == 'html') {
            foreach ($entries as $id => $entry) {
                $entries[$id]['html_output'] = $this->getService(EntryController::class)->view($entry, '', 0);
            }
        } elseif ($output == 'geojson') {
            $entries = $this->getService(GeoJSONFormatter::class)->formatToGeoJSON($entries);
        } elseif ($output == 'ical') {
            return $this->getService(IcalFormatter::class)->apiResponse($entries, $formId, $_GET);
        } elseif (isset($_GET['fields'])) {
            $fields = explode(',', $_GET['fields']);
            $lightEntries = [];
            if (!empty($entries) && !empty($fields)) {
                foreach ($entries as $id => $entry) {
                    $lightEntry = [];
                    foreach ($fields as $field_name) {
                        if (isset($entry[$field_name])) {
                            $lightEntry[$field_name] = $entry[$field_name];
                        }
                    }
                    if (!empty($lightEntry)) {
                        $lightEntries[$id] = $lightEntry;
                    }
                }
            }

            return new ApiResponse(empty($lightEntries) ? null : $lightEntries);
        }

        return new ApiResponse(empty($entries) ? null : $entries);
    }

    /**
     * @Route("/api/entries/{output}/{selectedEntries}", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getAllEntries($output = null, $selectedEntries = null)
    {
        // fast access for one entry
        if ($this->isEntryViewFastAccess($output, $selectedEntries, $_GET)) {
            $entryId = explode(',', $selectedEntries)[0];
            if ($this->getService(AclService::class)->hasAccess('read', $entryId)) {
                $html = $this->getService(EntryController::class)->view($entryId, '', 1);
            } else {
                $html = $this->render('@templates/alert-message.twig', [
                    'type' => 'info',
                    'message' => _t('ERROR_NO_ACCESS'),
                ]);
            }

            return new ApiResponse(empty($html) ? null : [$entryId => ['html_output' => $html]]);
        }

        return $this->getAllFormEntries([], $output, $selectedEntries);
    }

    /**
     * helper to check if EntryView fast access.
     *
     * @param string|null $output
     * @param string|null $selectedEntries
     * @param array|null  $get
     * @param bool
     */
    private function isEntryViewFastAccess($output, $selectedEntries, $get): bool
    {
        return $output == 'html'
            && !empty($selectedEntries) && is_string($selectedEntries) && count(explode(',', $selectedEntries)) == 1
            && !empty($get['fields']) && $get['fields'] == 'html_output';
    }

    /**
     * helper to check if EntryView fast access for Bazar/Service/Guard.
     *
     * @param bool
     */
    public function isEntryViewFastAccessHelper(): bool
    {
        $route = array_keys($_GET)[0];
        if (substr($route, strlen('api/entries/html'), 1) == '/') {
            $output = substr($route, strlen('api/entries/'), strlen('html'));
            $selectedEntries = substr($route, strlen('api/entries/html/'));
        } else {
            $output = '';
            $selectedEntries = '';
        }

        return $this->isEntryViewFastAccess($output, $selectedEntries, $_GET);
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
        }

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
            'Location: ' . $this->wiki->Href('', $entry['id_fiche']),
        ]);
    }

    /**
     * @Route("/api/entries/bazarlist", methods={"GET"}, options={"acl":{"public"}},priority=2)
     */
    public function getBazarListData()
    {
        $bazarListService = $this->getService(BazarListService::class);
        $externalIds = $_GET['externalIds'] ?? null;
        $externalModeActivated = (is_array($externalIds) && isset($_GET['externalModeActivated'])) ? in_array($_GET['externalModeActivated'], [1, true, '1', 'true'], true) : false;
        $forms = $bazarListService->getForms([
            'externalModeActivated' => $externalModeActivated,
            'externalIds' => $externalIds,
            'refresh' => isset($_GET['refresh']) ? in_array($_GET['refresh'], [1, true, '1', 'true'], true) : false,
        ]);

        $formattedGet = array_map(function ($value) {
            return ($value === 'true') ? true : (($value === 'false') ? false : $value);
        }, $_GET);

        // format search field
        $searchfields = isset($GET['search']) && $_GET['search'] == 'dynamic' ? $_GET['searchfields'] ?? [] : [];
        $searchfields = (empty($searchfields) || !is_string($searchfields) || !is_array($searchfields))
            ? ['bf_titre']
            : (
                is_string($searchfields)
                ? explode(',', $searchfields)
                : $searchfields
            );
        $formattedGet['searchfields'] = $searchfields;
        $formattedGet['externalModeActivated'] = $externalModeActivated;

        $entries = $bazarListService->getEntries(
            $formattedGet + [
                'user' => null,
                'dateMin' => null,
                'random' => false,
                'ordre' => 'asc',
                'champ' => 'bf_titre',
                'nb' => null,
                'colorfield ' => null,
                'iconfield ' => null,
            ],
            $forms
        );
        $filters = $bazarListService->getFilters($formattedGet, $entries, $forms);

        // Basic fields
        $fieldList = ['id_fiche', 'bf_titre'];
        // If no id, we need idtypeannonce (== formId) to filter
        if (!isset($_GET['id'])) {
            $fieldList[] = 'id_typeannonce';
        }
        // fields for colo / icon
        $fieldList = array_merge($fieldList, [$_GET['colorfield'] ?? null, $_GET['iconfield'] ?? null]);
        // Fields for filters
        foreach ($filters as $filter) {
            $fieldList[] = $filter['propName'];
        }
        // Fields used to search
        foreach ($searchfields as $field) {
            $fieldList[] = $field;
        }
        // Fields used by template
        foreach ($_GET['displayfields'] ?? [] as $field) {
            $fieldList[] = $field;
        }
        // Fields for external urls
        if ($formattedGet['externalModeActivated']) {
            $fieldList[] = 'url';
        }
        // extra fields required by template
        $fieldList = array_merge($fieldList, $_GET['necessary_fields'] ?? []);
        $fieldList = array_values(array_unique(array_filter($fieldList))); // array_values to have incremental keys

        $formIds = array_map(function ($entry) {
            return $entry['id_typeannonce'];
        }, $entries);
        $formIds = array_unique($formIds);
        $usedForms = array_filter($forms, function ($form) use ($formIds) {
            return in_array($form['bn_id_nature'], $formIds);
        });
        $usedForms = array_map(function ($f) {
            return $f['prepared'];
        }, $usedForms);

        // Reduce the size of the data sent by transforming entries object into array
        // we use the $fieldMapping to transform back the data when receiving data in the front end
        $entries = array_map(function ($entry) use ($fieldList) {
            $result = [];
            foreach ($fieldList as $field) {
                if (!empty($entry[$field]) && !empty($_GET['displayfields']['subtitle']) && $_GET['displayfields']['subtitle'] == $field) {
                    $entry[$field] = $this->wiki->Format($entry[$field]);
                }
                $result[] = $entry[$field] ?? null;
            }
            return $result;
        }, $entries);


        return new ApiResponse(
            [
                'entries' => $entries,
                'fieldMapping' => $fieldList,
                'filters' => $filters,
                'forms' => $usedForms,
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Display Bazar api documentation.
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
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/html') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format json, avec la représentation html de la fiche dans le champ <code>html_output</code><br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/geojson') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format geojson<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries/ical') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> au format ical<br />
        Il est possible de filtrer sur les dates en ajoutant à l\'url <code>&datefilter=>-6M</code> (exemple pour les dates plus récentes que 6 mois)<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/forms/{formId}/entries&fields=bf_titre') . '</code></b><br />
        Obtenir la liste de toutes les fiches du formulaire <code>formId</code> en ne gardant que les titres (il est possible de spécifier d\autres champs en séparant leur nom par des \',\')<br />
        </p>';

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/entries/{formId}') . '</code></b><br />
        Créer une nouvelle fiche en utilisant le formulaire <code>formId</code><br />
        Si le header <code>Content-Type</code> est <code>application/ld+json</code>, un JSON sémantique est attendu.
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/html') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format json, avec la représentation html de la fiche dans le champ <code>html_output</code><br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/bazarlist') . '</code></b><br />
        Obtenir les données nécessaires à bazarliste dynamic au format json<br />
        </p>';

        $output .= '
        <p>
        <b><code>POST ' . $this->wiki->href('', 'api/entries/{formId}/json-ld') . '</code></b><br />
        Créer une nouvelle fiche de type <code>formId</code> au format sémantique<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/geojson') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format geojson<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/ical') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format ical<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entries/{output}&fields=bf_titre') . '</code></b><br />
        Obtenir la liste de toutes les fiches au format spécifié en ne gardant que les titres (il est possible de spécifier d\'autres champs en séparant leur nom par des \',\' ex: <code>&field=bf_titre,url</code>)<br />
        </p>';

        $output .= '
        <p>
        <b><code>GET ' . $this->wiki->href('', 'api/entry/url/{sourceUrl}') . '</code></b><br />
        Retourne l\'URL de la page Wiki synchronisée avec <code>sourceUrl</code><br />
        </p>';

        return $output;
    }
}

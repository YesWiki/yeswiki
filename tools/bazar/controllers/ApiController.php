<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\EntryExtraFieldsService;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\HttpSignatureService;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Bazar\Service\SemanticTransformer;
use YesWiki\Bazar\Service\WebfingerService;
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
        if (strpos($formId, 'b64_') === 0) {
            $vFormId = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $vForm = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $vFormID])[$vFormID];

        if (!$vForm || !isset($vForm['bn_id_nature'])) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse($vForm);
    }

    /**
     * @Route("/api/forms/{formId}/actor", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getFormActor($formId)
    {
        $activityPubService = $this->getService(ActivityPubService::class);

        $form = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $formId])[$formId];

        if ($activityPubService->isEnabled($form)) {
            $actor = $activityPubService->getActor($form);

            return new ApiResponse($actor, Response::HTTP_OK, ['Content-Type' => 'application/activity+json']);
        } else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @Route("/api/forms/{formId}/actor/followers", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getFormActorFollowers($formId, Request $request)
    {
        $activityPubService = $this->getService(ActivityPubService::class);

        $form = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $formId])[$formId];

        if ($activityPubService->isEnabled($form)) {
            $followers = $activityPubService->getFollowers($form);

            return new ApiResponse([
                '@context' => "https://www.w3.org/ns/activitystreams",
                'type' => 'Collection',
                'id' => $activityPubService->getFormCollectionUri($form, 'followers'),
                'items' => $followers,
            ], Response::HTTP_OK, ['Content-Type' => 'application/activity+json']);
        } else {
            throw new NotFoundHttpException();
        }
    }

        /**
     * @Route("/api/forms/{formId}/actor/following", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getFormActorFollowing($formId, Request $request)
    {
        $activityPubService = $this->getService(ActivityPubService::class);

        $form = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $formId])[$formId];

        if ($activityPubService->isEnabled($form)) {
            $following = $activityPubService->getFollowing($form);

            return new ApiResponse([
                '@context' => "https://www.w3.org/ns/activitystreams",
                'type' => 'Collection',
                'id' => $activityPubService->getFormCollectionUri($form, 'following'),
                'items' => $following,
            ], Response::HTTP_OK, ['Content-Type' => 'application/activity+json']);
        } else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @Route("/api/forms/{formId}/actor/inbox", methods={"POST"}, options={"acl":{"public"}})
     */
    public function postFormActorInbox($formId, Request $request)
    {
        $activityPubService = $this->getService(ActivityPubService::class);
        $httpSignatureService = $this->getService(HttpSignatureService::class);

        $form = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $formId])[$formId];

        if ($activityPubService->isEnabled($form)) {
            $activity = json_decode($request->getContent(), true);

            $httpSignatureService->verifySignature($request);
            $activityPubService->processActivity($activity, $form);

            return new ApiResponse(null, Response::HTTP_OK, ['Content-Type' => 'application/activity+json']);
        } else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @Route("/api/forms/{formId}/actor/outbox", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getFormActorOutbox($formId, Request $request)
    {
        $activityPubService = $this->getService(ActivityPubService::class);
        $bazarListService = $this->getService(BazarListService::class);

        $form = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $formId])[$formId];

        if ($activityPubService->isEnabled($form)) {
            $entries = $bazarListService->getEntries([
                'idtypeannonce' => $formId,
                'ordre' => 'asc',
                'queries' => '',
                // TODO Handle pagination
                // 'nb' => 100 
            ]);

            return new ApiResponse([
                '@context' => "https://www.w3.org/ns/activitystreams",
                'type' => 'OrderedCollection',
                'id' => $activityPubService->getFormCollectionUri($form, 'following'),
                'totalItems' => count($entries),
                'orderedItems' => array_map(function ($entry) use ($form, $activityPubService) {
                    $object = $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $entry);
                    unset($object['@context']);
                    $published = new \DateTime($entry['date_creation_fiche']);
                    return [
                        'type' => 'Create',
                        'actor' => $activityPubService->getFormActorUri($form),
                        'published' => $published->format(\DateTime::ISO8601),
                        'object' => $object,
                        'to' => [$activityPubService->getFormCollectionUri($form, 'followers'), 'https://www.w3.org/ns/activitystreams#Public'],
                    ];
                }, array_values($entries)),
            ], Response::HTTP_OK, ['Content-Type' => 'application/activity+json']);

            return new ApiResponse(null, Response::HTTP_OK, ['Content-Type' => 'application/activity+json']);
        } else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @Route("/api/webfinger", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getWebfinger(Request $request)
    {
        $webfingerService = $this->getService(WebfingerService::class);
        $activityPubService = $this->getService(ActivityPubService::class);

        $handle = substr($request->query->get('resource'), 5); // Remove 'acct:' prefix

        $matches = $webfingerService->splitHandle($handle);

        $form = $this->getService(FormManager::class)->findByActivityPubUsername($matches['user']);

        if ($form) {
            $actorUri = $activityPubService->getFormActorUri($form);
            $actor = $webfingerService->formatLocalActor($handle, $actorUri);

            return new ApiResponse($actor, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } else {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @Route("/api/forms/{formId}/entries/{output}/{selectedEntries}", methods={"GET"},options={"acl":{"public"}})
     */
    public function getAllFormEntries($formId, $output = null, $selectedEntries = null)
    {
        if (!is_array($formId) && strpos($formId, 'b64_') === 0) {
            $vFormID = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $vSearchManager = $this->getService(SearchManager::class);
        $get = $this->getRequest()->query;

        $vQuery = $get->get('query') ?? $get->get('queries') ?? null;
        $vQuery = $vSearchManager->aggregateQueries(
            !empty($selectedEntries) ? ['queries' => ['id_fiche' => $selectedEntries]] : [],
            isset($vQuery) ? urldecode($vQuery) : ''
        );

        $vKeywords = $vSearchManager->aggregateKeywords($get->get('keywords', ''), $get->get('q', ''));

        $vSearchFields = $get->has('searchfields') ? urldecode($get->get('searchfields')) : null;
        $vCorrespondance = $get->has('correspondance') ? urldecode($get->get('correspondance')) : null;
        $vDateFilter = $get->has('datefilter') ? urldecode($get->get('datefilter')) : null;
        $vOrdre = $get->get('ordre', 'asc');
        $vChamp = $get->get('champ', 'bf_titre');
        $vNb = intval($get->get('nbitem') ?? $get->get('nb') ?? null);
        $vMinDate = urldecode($get->get('dateMin') ?? $get->get('minDate') ?? $get->get('period') ?? '');

        if ($output == 'csv') { // Search is done in the CSV Manager
            $csvManager = $this->getService(CSVManager::class);
            $csvManager->sendCsvOrZip($vFormID, [
                'queries' => $vQuery,
                'keywords' => $vKeywords,
                'searchfields' => $vSearchFields,
                'datefilter' => $vDateFilter,
                'correspondance' => $vCorrespondance,
                'ordre' => $vOrdre,
                'champ' => $vChamp,
                'nb' => $vNb,
                'minDate' => $vMinDate,
            ]);
        } else {
            $vBazarListService = $this->getService(BazarListService::class);

            $entries = $vBazarListService->getEntries([
                'idtypeannonce' => $vFormID,
                'queries' => $vQuery,
                'keywords' => $vKeywords,
                'searchfields' => $vSearchFields,
                'datefilter' => $vDateFilter,
                'correspondance' => $vCorrespondance,
                'ordre' => $vOrdre,
                'champ' => $vChamp,
                'nb' => $vNb,
                'minDate' => $vMinDate,
            ]);

            $acceptHeader = $this->getRequest()->headers->get('accept', '');
            if ($output == 'json-ld' || strpos($acceptHeader, 'application/ld+json') !== false) {
                return $this->getAllSemanticEntries($formId, $entries);
            } // add entries in html format if asked
            elseif ($output == 'html') {
                foreach ($entries as $id => $entry) {
                    $entries[$id]['html_output'] = $this->getService(EntryController::class)->view($entry, '', 0);
                }
            } elseif ($output == 'geojson') {
                $entries = $this->getService(GeoJSONFormatter::class)->formatToGeoJSON($entries);
            } elseif ($output == 'ical') {
                return $this->getService(IcalFormatter::class)->apiResponse($entries, $formId, $get->all());
            } elseif ($get->has('fields')) {
                $fields = explode(',', $get->get('fields'));
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
        }

        return new ApiResponse(empty($entries) ? null : $entries);
    }

    /**
     * @Route("/api/entries/{output}/{selectedEntries}", methods={"GET"}, options={"acl":{"public"}})
     */
    public function getAllEntries($output = null, $selectedEntries = null)
    {
        // fast access for one entry
        $get = $this->getRequest()->query;
        if ($this->isEntryViewFastAccess($output, $selectedEntries, $get->all())) {
            $entryId = explode(',', $selectedEntries)[0];
            if ($this->getService(AclService::class)->hasAccess('read', $entryId)) {
                $html = $this->getService(EntryController::class)->view($entryId, '', 1);
                $isInIframe = $get->get('isInIframe');
                if ($isInIframe && $isInIframe == 'iframe') {
                    $html = replaceLinksWithIframe($html);
                }
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
        $queryAll = $this->getRequest()->query->all();
        $route = array_key_first($queryAll);
        if (substr($route, strlen('api/entries/html'), 1) == '/') {
            $output = substr($route, strlen('api/entries/'), strlen('html'));
            $selectedEntries = substr($route, strlen('api/entries/html/'));
        } else {
            $output = '';
            $selectedEntries = '';
        }

        return $this->isEntryViewFastAccess($output, $selectedEntries, $queryAll);
    }

    public function getAllSemanticEntries($formId, $entries)
    {
        // Put data inside LDP container
        $form = $this->getService(FormManager::class)->getOne($formId);

        $resources = array_map(function ($entry) use ($form) {
            return $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $entry, true);
        }, array_values($entries));

        $context = !empty($resources) ? ($resources[0]['@context'] ?? null) : null;
        foreach ($resources as &$resource) {
            unset($resource['@context']);
        }

        return new ApiResponse(
            [
                '@context' => $context,
                '@id' => $this->wiki->Href('fiche/' . $formId, 'api'),
                '@type' => ['ldp:Container', 'ldp:BasicContainer'],
                'dcterms:title' => $form['bn_label_nature'],
                'ldp:contains' => $resources,
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
     * Create or update an entry.
     *
     * @Route("/api/entries/{formId}", methods={"POST"}, options={"acl":{"+"}})
     */
    public function createEntry($formId)
    {
        $request = $this->getRequest();
        if (strpos($request->headers->get('content-type', ''), 'application/ld+json') !== false) {
            $this->createSemanticEntry($formId);
        }

        $postData = $request->request->all();
        $postData['antispam'] = 1;

        if (!isset($postData['id_fiche']) || !$this->getService(EntryManager::class)->isEntry($postData['id_fiche'])) {
            $entry = $this->getService(EntryManager::class)->create($formId, $postData, false, $request->headers->get('source-url'));
        } else {
            $entry = $this->getService(EntryManager::class)->update($postData['id_fiche'], $postData, false, true);
        }

        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new ApiResponse(
            ['success' => $this->wiki->Href('', $entry['id_fiche'])],
            Response::HTTP_CREATED
        );
    }

    /**
     * @Route("/api/entries/{formId}/json-ld", methods={"POST"}, options={"acl":{"+"}})
     */
    public function createSemanticEntry($formId)
    {
        $postData = $this->getRequest()->request->all();
        $postData['antispam'] = 1;
        $entry = $this->getService(EntryManager::class)->create($formId, $postData, true, $this->getRequest()->headers->get('source-url'));

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
        $vBazarListService = $this->getService(BazarListService::class);

        /* ------------------------------------ */
        /*             Format Params            */
        /* ------------------------------------ */

        $queryAll = $this->getRequest()->query->all();
        $formattedGet = array_map(function ($value) {
            return ($value === 'true') ? true : (($value === 'false') ? false : $value);
        }, $queryAll);

        $get = $this->getRequest()->query;
        $searchfields = $get->get('searchfields');
        $searchfields = is_string($searchfields) ? explode(',', urldecode($searchfields)) : $searchfields;
        $searchfields = $searchfields == null ? [] : $searchfields;

        $vKeywords = $get->has('keywords') ? urldecode($get->get('keywords')) : '';

        $formattedGet['keywords'] = $vKeywords;
        $formattedGet['searchfields'] = $searchfields;
        $formattedGet['idtypeannonce'] = $get->get('idtypeannonce') ?? $get->get('id') ?? null;

        /* ------------------------------------ */
        /*               Get Data               */
        /* ------------------------------------ */
        // All forms
        $refreshVal = $get->get('refresh');
        $forms = $vBazarListService->getForms($formattedGet + ['refresh' => isset($refreshVal) ? in_array($refreshVal, [1, true, '1', 'true'], true) : false]);

        // Entries
        $entries = $vBazarListService->getEntries($formattedGet, $forms);

        // Filters
        $filters = $vBazarListService->getFilters($formattedGet, $entries, $forms);

        /* ------------------------------------ */
        /*            Transform Data            */
        /* ------------------------------------ */

        // Associated Forms
        $formIds = array_unique(array_map(function ($entry) {
            return $entry['id_typeannonce'];
        }, $entries));
        $usedForms = array_filter($forms, function ($form) use ($formIds) {
            return in_array($form['bn_id_nature'], $formIds);
        });
        $usedForms = array_map(function ($f) {
            return $f['prepared'];
        }, $usedForms);

        // Basic fields
        $fieldList = ['id_fiche', 'bf_titre', 'url', '-is-external-', 'external-data'];
        // If no id, we need idtypeannonce (== formId) to filter
        if (!$get->has('id')) {
            $fieldList[] = 'id_typeannonce';
        }
        // fields for color / icon
        $colorfield = $get->get('colorfield');
        $fieldList = array_merge($fieldList, $colorfield ? [$colorfield] : []);
        $iconfield = $get->get('iconfield');
        $fieldList = array_merge($fieldList, $iconfield ? [$iconfield] : []);
        // Fields used to search
        $fieldList = array_merge($fieldList, $searchfields);
        // Fields used to sort
        $fieldList = array_merge($fieldList, $get->has('sortfields') ? $get->all('sortfields') : []);
        // Fields used by template
        $fieldList = array_merge($fieldList, $get->has('displayfields') ? $get->all('displayfields') : []);
        // extra fields required by template
        $fieldList = array_merge($fieldList, $get->has('necessary_fields') ? $get->all('necessary_fields') : []);
        $fieldList = array_merge($fieldList, $get->has('necessaryfields') ? $get->all('necessaryfields') : []);
        // Fields for filters
        foreach ($filters as $filter) {
            $fieldList[] = $filter['propName'];
        }

        // filter blank values, remove duplicates, array_values to have incremental keys
        $fieldList = array_values(array_unique(array_filter($fieldList)));

        // Reduce the size of the data sent by transforming entries object into array
        // we use the $fieldMapping to transform back the data when receiving data in the front end
        $entryFieldsService = $this->getService(EntryExtraFieldsService::class);

        $entries = array_map(function ($entry) use ($fieldList, $entryFieldsService) {
            $entryFieldsService->setEntryId($entry['id_fiche']);
            $result = [];
            foreach ($fieldList as $fieldName) {
                // when the field is a TextareaField with the SYNTAX_WIKI syntax, transform the field value into HTML
                $field = $this->getService(FormManager::class)->findFieldFromNameOrPropertyName($fieldName, $entry['id_typeannonce']);
                if ($field && $field->getType() == 'textelong' && $field->getSyntax() == TextareaField::SYNTAX_WIKI) {
                    $entry[$fieldName] = $this->wiki->Format($entry[$fieldName]);
                }
                // handle specific fields like comments, reactions
                if (!isset($entry[$fieldName]) || (is_string($entry[$fieldName]) && trim($entry[$fieldName]) == '')) {
                    $entry[$fieldName] = $entryFieldsService->get($fieldName);
                }
                $result[] = $entry[$fieldName] ?? null;
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

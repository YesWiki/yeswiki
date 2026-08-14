<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\CSVManager;
use YesWiki\Content\Service\EntryExtraFieldsService;
use YesWiki\Content\Service\EntryFastAccessService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\GeoJSONFormatter;
use YesWiki\Content\Service\IcalFormatter;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Search\Service\SearchManager;

class EntryApiController extends YesWikiController
{
    #[Route('/api/forms/{formId}/entries/{output}/{selectedEntries}', methods: ['GET'], options: ['acl' => ['public']])]
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
            !empty($selectedEntries) ? ['queries' => ['tag' => $selectedEntries]] : [],
            isset($vQuery) ? urldecode($vQuery) : ''
        );

        $vKeywords = $vSearchManager->aggregateKeywords($get->get('keywords', ''), $get->get('q', ''));

        $vSearchFields = $get->has('searchfields') ? urldecode($get->get('searchfields')) : null;
        $vFieldMapping = $get->has('fieldmapping') ? urldecode($get->get('fieldmapping')) : null;
        $vDateFilter = $get->has('datefilter') ? urldecode($get->get('datefilter')) : null;
        $vOrdre = $get->get('order', 'asc');
        $vField = $get->get('field', 'title');
        $vNb = intval($get->get('nbitem') ?? $get->get('nb') ?? null);
        $vMinDate = urldecode($get->get('dateMin') ?? $get->get('minDate') ?? $get->get('period') ?? '');

        if ($output == 'csv') { // Search is done in the CSV Manager
            $csvManager = $this->getService(CSVManager::class);
            $csvManager->sendCsvOrZip($vFormID, [
                'queries' => $vQuery,
                'keywords' => $vKeywords,
                'searchfields' => $vSearchFields,
                'datefilter' => $vDateFilter,
                'fieldmapping' => $vFieldMapping,
                'order' => $vOrdre,
                'field' => $vField,
                'nb' => $vNb,
                'minDate' => $vMinDate,
            ]);
        } else {
            $vBazarListService = $this->getService(BazarListService::class);

            $entries = $vBazarListService->getEntries([
                'id' => $vFormID,
                'queries' => $vQuery,
                'keywords' => $vKeywords,
                'searchfields' => $vSearchFields,
                'datefilter' => $vDateFilter,
                'fieldmapping' => $vFieldMapping,
                'order' => $vOrdre,
                'field' => $vField,
                'nb' => $vNb,
                'minDate' => $vMinDate,
            ]);

            $acceptHeader = $this->getRequest()->headers->get('accept', '');
            if ($output == 'json-ld' || strpos($acceptHeader, 'application/ld+json') !== false) {
                return $this->getAllSemanticEntries($formId, $entries);
            } // add entries in html format if asked
            elseif ($output == 'html') {
                foreach ($entries as $id => $entry) {
                    $entries[$id]['html_output'] = $this->getService(EntryController::class)->view($entry, '', false);
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

    #[Route('/api/entries/{output}/{selectedEntries}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllEntries($output = null, $selectedEntries = null)
    {
        // fast access for one entry
        $get = $this->getRequest()->query;
        if ($this->getService(EntryFastAccessService::class)->isFastAccess($output, $selectedEntries, $get->all())) {
            $entryId = explode(',', $selectedEntries)[0];
            if ($this->getService(AclService::class)->hasAccess('read', $entryId)) {
                $html = $this->getService(EntryController::class)->view($entryId, '', true);
                $isInIframe = $get->get('isInIframe');
                if ($isInIframe && $isInIframe == 'iframe') {
                    $html = replaceLinksWithIframe($html);
                }
            } else {
                $html = $this->render('@core/alert-message.twig', [
                    'type' => 'info',
                    'message' => _t('ERROR_NO_ACCESS'),
                ]);
            }

            return new ApiResponse(empty($html) ? null : [$entryId => ['html_output' => $html]]);
        }

        return $this->getAllFormEntries([], $output, $selectedEntries);
    }

    public function getAllSemanticEntries($formId, $entries)
    {
        // Put data inside LDP container
        $form = $this->getService(FormManager::class)->getOne($formId);

        $resources = array_map(function ($entry) use ($form) {
            return $this->getService(SemanticTransformer::class)->convertToSemanticData($form, $entry);
        }, array_values($entries));

        $context = !empty($resources) ? ($resources[0]['@context'] ?? null) : null;
        foreach ($resources as &$resource) {
            unset($resource['@context']);
        }

        return new ApiResponse(
            [
                '@context' => $context,
                '@id' => $this->getService(UrlFormatter::class)->href('fiche/' . $formId, 'api'),
                '@type' => ['ldp:Container', 'ldp:BasicContainer'],
                'dcterms:title' => $form['label'],
                'ldp:contains' => $resources,
            ],
            Response::HTTP_OK,
            ['Content-Type: application/ld+json; charset=UTF-8']
        );
    }

    #[Route('/api/entries/url/{sourceUrl}')]
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
            return $this->getService(UrlFormatter::class)->href('', $triple['resource']);
        }, $triples);

        return new ApiResponse($resources);
    }

    /**
     * Create or update an entry.
     */
    #[Route('/api/entries/{formId}', methods: ['POST'], options: ['acl' => ['+']])]
    public function createEntry($formId)
    {
        $request = $this->getRequest();
        if (strpos($request->headers->get('content-type', ''), 'application/ld+json') !== false) {
            // pre-split ApiController fell through here and created the entry twice,
            // discarding the semantic response
            return $this->createSemanticEntry($formId);
        }

        $postData = $request->request->all();
        if (empty($postData) && strpos($request->headers->get('content-type', ''), 'application/json') !== false) {
            $jsonData = json_decode($request->getContent(), true);
            if (is_array($jsonData)) {
                $postData = $jsonData;
            }
        }
        $postData['antispam'] = 1;

        if (!isset($postData['tag']) || !$this->getService(EntryManager::class)->isEntry($postData['tag'])) {
            $entry = $this->getService(EntryManager::class)->create($formId, $postData, false, $request->headers->get('source-url'));
        } else {
            $entry = $this->getService(EntryManager::class)->update($postData['tag'], $postData, false, true);
        }

        if (!$entry) {
            throw new BadRequestHttpException();
        }

        return new ApiResponse(
            ['success' => $this->getService(UrlFormatter::class)->href('', $entry['tag'])],
            Response::HTTP_CREATED
        );
    }

    #[Route('/api/entries/{formId}/json-ld', methods: ['POST'], options: ['acl' => ['+']])]
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
            'Location: ' . $this->getService(UrlFormatter::class)->href('', $entry['tag']),
        ]);
    }

    #[Route('/api/entries/bazarlist', methods: ['GET'], options: ['acl' => ['public']], priority: 2)]
    public function getBazarListData()
    {
        $vBazarListService = $this->getService(BazarListService::class);

        /* ------------------------------------ */
        /*             Format Params */
        /* ------------------------------------ */

        $queryAll = $this->getRequest()->query->all();
        $formattedGet = array_map(function ($value) {
            return ($value === 'true') ? true : (($value === 'false') ? false : $value);
        }, $queryAll);

        // Read from all() rather than query->get(): every one of these arrives as an
        // ARRAY from the dynamic templates (`idtypeannonce[]=1&searchfields[]=title`), and
        // InputBag::get() throws a 400 "contains a non-scalar value" on those -- which is
        // why every dynamic bazar view rendered a spinner and never resolved.
        $searchfields = $queryAll['searchfields'] ?? null;
        $searchfields = is_string($searchfields) ? explode(',', urldecode($searchfields)) : $searchfields;
        $searchfields = $searchfields === null ? [] : (array)$searchfields;

        $vKeywords = isset($queryAll['keywords']) && is_string($queryAll['keywords'])
            ? urldecode($queryAll['keywords'])
            : '';

        $formattedGet['keywords'] = $vKeywords;
        $formattedGet['searchfields'] = $searchfields;
        $formattedGet['id'] = $queryAll['id'] ?? null;

        /* ------------------------------------ */
        /*               Get Data */
        /* ------------------------------------ */
        // All forms
        $refreshVal = $queryAll['refresh'] ?? null;
        $forms = $vBazarListService->getForms($formattedGet + ['refresh' => isset($refreshVal) ? in_array($refreshVal, [1, true, '1', 'true'], true) : false]);

        // Entries
        $entries = $vBazarListService->getEntries($formattedGet, $forms);

        // Filters
        $filters = $vBazarListService->getFilters($formattedGet, $entries, $forms);

        /* ------------------------------------ */
        /*            Transform Data */
        /* ------------------------------------ */

        // Associated Forms
        $formIds = array_unique(array_map(function ($entry) {
            return $entry['form_id'];
        }, $entries));
        $usedForms = array_filter($forms, function ($form) use ($formIds) {
            return in_array($form['id'], $formIds);
        });
        $usedForms = array_map(function ($f) {
            return $f['prepared'];
        }, $usedForms);

        // Basic fields: the computed title (ADR-0010) plus bf_titre for forms that have it
        $fieldList = ['tag', PageBody::TITLE, 'bf_titre', 'url', '-is-external-', 'external-data'];
        // If no id, we need idtypeannonce (== formId) to filter
        if (!isset($queryAll['id'])) {
            $fieldList[] = 'form_id';
        }
        // fields for color / icon
        $colorfield = $queryAll['colorfield'] ?? null;
        $fieldList = array_merge($fieldList, is_string($colorfield) && $colorfield !== '' ? [$colorfield] : []);
        $iconfield = $queryAll['iconfield'] ?? null;
        $fieldList = array_merge($fieldList, is_string($iconfield) && $iconfield !== '' ? [$iconfield] : []);
        // Fields used to search
        $fieldList = array_merge($fieldList, $searchfields);
        // Fields used to sort / by the template / required by the template
        foreach (['sortfields', 'displayfields', 'necessary_fields', 'necessaryfields'] as $key) {
            $fieldList = array_merge($fieldList, (array)($queryAll[$key] ?? []));
        }
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
            $entryFieldsService->setEntryId($entry['tag']);
            $result = [];
            foreach ($fieldList as $fieldName) {
                // when the field is a TextareaField with the SYNTAX_WIKI syntax, transform the field value into HTML
                $field = $this->getService(FormManager::class)->findFieldFromNameOrPropertyName($fieldName, $entry['form_id']);
                // `instanceof` rather than `getType() == 'textelong'`: getSyntax() lives only on
                // TextareaField, and the string check proved that to a reader but not to the
                // analyser, which is why the call sat baselined as method.notFound (ticket 40)
                if ($field instanceof TextareaField && $field->getSyntax() == TextareaField::SYNTAX_WIKI) {
                    $entry[$fieldName] = $this->getService(MarkdownFormatterService::class)->format($entry[$fieldName]);
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
}

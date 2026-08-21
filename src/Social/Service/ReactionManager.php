<?php

namespace YesWiki\Social\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Social\Field\ReactionsField;

class ReactionManager
{
    protected ContainerInterface $container;
    protected DbService $dbService;
    protected EntryManager $entryManager;
    protected FormManager $formManager;
    protected TripleStore $tripleStore;

    public const TYPE_URI = 'https://yeswiki.net/vocabulary/reaction';
    public const DEFAULT_TITLE_T = 'REACTION_SHARE_YOUR_REACTION';
    public const DEFAULT_LABELS_T = ['REACTION_LIKE', 'REACTION_DISLIKE', 'REACTION_ANGRY', 'REACTION_SURPRISED', 'REACTION_THINKING'];

    public const DEFAULT_IDS = ['japprouve', 'je-napprouve-pas', 'fachee', 'surprise', 'dubitatifve'];
    public const DEFAULT_IMAGES = ['👍', '👎', '😡', '😮', '🤔'];
    public const DEFAULT_MAX_REACTIONS = 1;

    /** @var array<string, mixed>|null */
    protected $cachedReactions;

    public function __construct(
        ContainerInterface $container,
        TripleStore $tripleStore,
        DbService $dbService,
        EntryManager $entryManager,
        FormManager $formManager
    ) {
        $this->container = $container;
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->tripleStore = $tripleStore;
    }

    /**
     * @param list<string> $ids only these reaction ids, or [] for all of them
     *
     * @return array<string, mixed> keyed by "reactionId|pageTag", or by reactionId alone when $singleEntry
     */
    public function getReactions(string $pageTag = '', array $ids = [], string $user = '', bool $singleEntry = false): array
    {
        $res = [];

        $val = $this->tripleStore->getAll($pageTag, self::TYPE_URI, '', '');
        foreach ($val as $v) {
            $v['value'] = json_decode($v['value'], true);
            $v['value']['idTriple'] = $v['id'];
            if (!empty($user) && $user != $v['value']['user']) {
                continue;
            }
            if (!empty($ids) && isset($v['value']['idReaction']) && isset($v['value']['date']) && !in_array($v['value']['idReaction'], $ids)) {
                continue;
            }
            if (!empty($pageTag)) {
                $v['value']['pageTag'] = $pageTag;
            } else {
                $v['value']['pageTag'] = $v['resource'];
            }
            if (empty($v['value']['date'])) {
                $v['value']['date'] = _t('REACTION_DATE_UNKNOWN');
            }
            if (!isset($v['value']['idReaction']) || !isset($v['value']['date'])) {
                $idReaction = 'reactionField';
                $resKey = "$idReaction|{$v['value']['pageTag']}";
                if (!isset($res[$resKey])) {
                    $res[$resKey] = [];
                }
                if (!isset($res[$resKey]['parameters'])) {
                    $params = [];
                    $this->appendParametersFromField($params, $v['value']['pageTag']);
                    $res[$resKey]['parameters'] = $params[array_key_first($params)];
                    $res[$resKey]['parameters']['pageTag'] = $v['value']['pageTag'];
                }
                $res[$resKey]['reactions'][] = array_merge([
                    'idReaction' => $idReaction,
                ], $v['value']);
            } else {
                $key = $singleEntry ? $v['value']['idReaction'] : $v['value']['idReaction'] . '|' . $v['value']['pageTag'];

                if (!isset($res[$key]['parameters'])) {
                    $params = $this->getActionParameters($v['value']['pageTag'], $v['value']['idReaction']);
                    $res[$key]['parameters'] = $params[$v['value']['idReaction']] ?? [];
                    $res[$key]['parameters']['pageTag'] = $v['value']['pageTag'];
                }

                if (!isset($res[$key]['nb_reactions'])) {
                    $res[$key]['nb_reactions'] = [];
                }
                if (!isset($res[$key]['nb_reactions'][$v['value']['id']])) {
                    $res[$key]['nb_reactions'][$v['value']['id']] = 1;
                } else {
                    $res[$key]['nb_reactions'][$v['value']['id']]++;
                }

                $res[$key]['reactions'][] = $v['value'];
            }
        }
        ksort($res);

        return $res;
    }

    public function getReactionsCount(string $tag): int
    {
        $type = self::TYPE_URI;

        return $this->dbService->countRows("
            SELECT * FROM {$this->dbService->prefixTable('triples')}
            WHERE resource = ? AND property = ?
        ", [$tag, $type]);
    }

    /**
     * @return array<string, mixed> the {{reactions}} parameters, keyed by reaction id
     */
    public function getActionParameters(string $page, ?string $idReaction = null): array
    {
        // `$idReaction = null` used to stand where the argument goes, which assigned over the
        // caller's value and asked for every reaction each time (ticket 40)
        if ($this->entryManager->isEntry($page)) {
            return $this->getActionParametersFromEntry($page, $idReaction);
        }

        return $this->getActionParametersFromPage($page, $idReaction);
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionParametersFromPage(string $page, ?string $idReaction = null): array
    {
        $p = $this->container->get(PageManager::class)->getOne($page);
        if (!empty($p)) {
            $params = [];
            $this->appendParamsFromActionDefinition($params, PageBody::content($p['body'] ?? []));
            if (!empty($params)) {
                if ($idReaction != null && isset($params[$idReaction])) {
                    return [$idReaction => $params[$idReaction]];
                }
                ksort($params);

                return $params;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionParametersFromEntry(string $entryId, ?string $idReaction = null): array
    {
        $entry = $this->entryManager->getOne($entryId);
        $params = [];
        if (!empty($entry) && !empty($entry['form_id'])) {
            $formId = $entry['form_id'];
            $form = $this->formManager->getOne($formId);
            if (!empty($form['prepared'])) {
                foreach ($form['prepared'] as $field) {
                    if ($field instanceof TextareaField && $field->getSyntax() == TextareaField::SYNTAX_WIKI && !empty($entry[$field->getPropertyName()])) {
                        $this->appendParamsFromActionDefinition($params, $entry[$field->getPropertyName()]);
                    } elseif ($field instanceof ReactionsField) {
                        $this->appendParametersFromField($params, $entryId, $field);
                    }
                }
            }
            if (!empty($params)) {
                if (!is_null($idReaction) && isset($params[$idReaction])) {
                    return [$idReaction => $params[$idReaction]];
                }
                ksort($params);

                return $params;
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function appendParamsFromActionDefinition(array &$params, string $text): void
    {
        if (preg_match_all('/{{reactions(?:\s([^}]*))?\s*}}/Ui', $text, $matches)) {
            foreach ($matches[0] as $id => $m) {
                $paramText = $matches[1][$id];
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"|\s*/U', $paramText, $paramMatches)) {
                    // three arrays of strings, kept in step: what each parameter is called, what
                    // it was written as, and the whole `name="value"` it came from. The computed
                    // labels/images maps used to be written back over $names' string values,
                    // which is why explode() below could be handed an array (ticket 40).
                    $names = $paramMatches[1];
                    $written = $paramMatches[2];
                    $declarations = $paramMatches[0];

                    $k = array_search('title', $names);
                    if ($k === false) {
                        $names[] = 'title';
                        $k = count($names) - 1;
                        $written[$k] = _t(self::DEFAULT_TITLE_T);
                        $declarations[] = "title=\"{$written[$k]}\"";
                    }
                    $title = $written[$k];

                    $k = array_search('labels', $names);
                    if ($k === false) {
                        $names[] = 'labels';
                        $k = count($names) - 1;
                        $written[$k] = implode(',', array_map('_t', self::DEFAULT_LABELS_T));
                        $declarations[] = "labels=\"{$written[$k]}\"";
                    }
                    $labelsWithId = [];
                    foreach (array_map('trim', explode(',', $written[$k])) as $lab) {
                        $labelsWithId[\URLify::slug($lab)] = $lab;
                    }
                    $ids = array_keys($labelsWithId);

                    $k = array_search('images', $names);
                    if ($k === false) {
                        $names[] = 'images';
                        $k = count($names) - 1;
                        $written[$k] = implode(',', self::DEFAULT_IMAGES);
                        $declarations[] = "images=\"{$written[$k]}\"";
                    }
                    $htmlImages = [];
                    foreach (array_map('trim', explode(',', $written[$k])) as $i => $img) {
                        if (!isset($ids[$i])) {
                            // more images than labels: nothing to attach this one to
                            continue;
                        }
                        $htmlImages[$ids[$i]] = empty($img)
                            ? ''
                            : trim($this->container->get(\YesWiki\Render\Service\TemplateEngine::class)->renderSafely('@core/_reactions_images.twig', [
                                'image' => $img,
                                'id' => 'image',
                            ]));
                    }

                    $reactionId = \URLify::slug($title);
                    foreach (array_keys($declarations) as $idM) {
                        $params[$reactionId][$names[$idM]] = $written[$idM];
                    }
                    $params[$reactionId]['labels'] = $labelsWithId;
                    $params[$reactionId]['images'] = $htmlImages;
                }
            }
        }
    }

    /** to ensure backward compatibility with old reactions from lms extension. */
    /**
     * @param array<string, mixed> $params
     */
    protected function appendParametersFromField(array &$params, string $tag, ?ReactionsField $field = null): void
    {
        $labels = [];
        $images = [];
        if (is_null($field) && !empty($tag)) {
            $entry = $this->entryManager->getOne($tag);
            if (!empty($entry['form_id'])) {
                $form = $this->formManager->getOne($entry['form_id']);
                if (!empty($form['prepared'])) {
                    $reactionsFields = array_filter($form['prepared'], function ($intField) {
                        return $intField instanceof ReactionsField;
                    });
                    if (!empty($reactionsFields)) {
                        foreach ($reactionsFields as $intField) {
                            if ($intField->getName() === 'reactions') {
                                $field = $intField;
                                break;
                            }
                        }
                        if (empty($field)) {
                            foreach ($reactionsFields as $intField) {
                                if (empty($intField->getName()) || trim($intField->getName()) === '') {
                                    $field = $intField;
                                    break;
                                }
                            }
                        }
                        if (empty($field)) {
                            $field = $reactionsFields[array_key_first($reactionsFields)];
                        }
                    }
                }
            }
        }
        if (!empty($field)) {
            $reactionId = empty(trim($field->getName())) ? 'reactionField' : trim($field->getName());
            $ids = $field->getIds();
            $rawLabels = $field->getLabels();
            $rawImages = $field->getImagesPath();

            $labels = [];
            $images = [];
            foreach ($ids as $k => $id) {
                $labels[$id] = $rawLabels[$k];
                $images[$id] = empty($rawImages[$k])
                    ? ''
                    : trim($this->container->get(\YesWiki\Render\Service\TemplateEngine::class)->renderSafely('@core/_reactions_images.twig', [
                        'image' => $rawImages[$k],
                        'id' => $id,
                    ]));
            }
            $params[$reactionId] = [
                'labels' => $labels,
                'images' => $images,
                'pageTag' => $tag,
                'title' => _t('BAZ_SHARE_YOUR_REACTION'),
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAllReactionInfos(string $idReaction, string $page)
    {
        return $this->getActionParameters($page)[$idReaction] ?? null;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return int 0 (success), 1 (failure) or 3 (already exists)
     */
    public function addUserReaction(string $pageTag, array $values)
    {
        if (!$this->container->get(AuthenticationService::class)->getLoggedUser()) {
            throw new \Exception('Unauthorized');
        }

        $payload = json_encode([
            'user' => $values['userName'],
            'idReaction' => $values['reactionId'],
            'id' => $values['id'],
            'date' => $values['date'],
        ]);
        if ($payload === false) {
            throw new \Exception('Reaction could not be encoded');
        }

        return $this->tripleStore->create($pageTag, self::TYPE_URI, $payload, '', '');
    }

    public function deleteUserReaction(string $pageTag, string $reactionId, string $id, string $user): bool
    {
        if ($reactionId === '') {
            throw new \Exception('ReactionId not specified');
        }
        if ($id === '') {
            throw new \Exception('Reaction value not specified');
        }

        $connectedUser = $this->container->get(AuthenticationService::class)->getLoggedUser();
        if (!$this->container->get(AclService::class)->isAdmin() && (empty($connectedUser) || $connectedUser['name'] !== $user)) {
            throw new \Exception('Unauthorized');
        }

        $valueCol = $this->dbService->quoteIdentifier('value');
        $suffix = SqlParameters::LIKE_CLAUSE_SUFFIX;

        $holds = fn (string $json): SqlFragment => SqlFragment::of(
            "({$valueCol} LIKE ?{$suffix})",
            [SqlParameters::likeContains($json)]
        );
        $lacks = fn (string $json): SqlFragment => SqlFragment::of(
            "({$valueCol} NOT LIKE ?{$suffix})",
            [SqlParameters::likeContains($json)]
        );

        if ($this->entryManager->isEntry($pageTag) && $reactionId == 'reactionField') {
            return $this->tripleStore->delete(
                $pageTag,
                self::TYPE_URI,
                null,
                '',
                '',
                SqlFragment::all(
                    ' AND ',
                    $holds('"user":"' . $user . '"'),
                    $holds('"id":"' . $id . '"'),
                    $lacks('"idReaction":"'),
                    $lacks('"date":"')
                )
            );
        }

        return $this->tripleStore->delete(
            $pageTag,
            self::TYPE_URI,
            null,
            '',
            '',
            $holds('user":"' . $user . '","idReaction":"' . $reactionId . '","id":"' . $id . '"')
        );
    }
}

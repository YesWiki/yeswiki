<?php

namespace YesWiki\Core\Service;

use YesWiki\Bazar\Field\ReactionsField;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Wiki;

class ReactionManager
{
    protected $wiki;
    protected $dbService;
    protected $entryManager;
    protected $formManager;
    protected $tripleStore;

    public const TYPE_URI = 'https://yeswiki.net/vocabulary/reaction';
    public const DEFAULT_TITLE_T = 'REACTION_SHARE_YOUR_REACTION';
    public const DEFAULT_LABELS_T = ['REACTION_LIKE', 'REACTION_DISLIKE', 'REACTION_ANGRY', 'REACTION_SURPRISED', 'REACTION_THINKING'];
    // TODO make a migration script to move from old labels translation to english ones (like, dislike,angry,surprised,thinking)
    public const DEFAULT_IDS = ['japprouve', 'je-napprouve-pas', 'fachee', 'surprise', 'dubitatifve'];
    public const DEFAULT_IMAGES = ['👍', '👎', '😡', '😮', '🤔'];
    public const DEFAULT_MAX_REACTIONS = 1;

    protected $cachedReactions;

    public function __construct(
        Wiki $wiki,
        TripleStore $tripleStore,
        DbService $dbService,
        EntryManager $entryManager,
        formManager $formManager
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->tripleStore = $tripleStore;
    }

    public function getReactions($pageTag = '', $ids = [], $user = '')
    {
        $res = [];
        // get reactions in db
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
                // old format form lms extension
                // @todo remove this for ectoplasme
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
                // get title and reaction labels for choosen reaction id in choosen page page
                if (!isset($res[$v['value']['idReaction'] . '|' . $v['value']['pageTag']]['parameters'])) {
                    $params = $this->getActionParameters($v['value']['pageTag'], $v['value']['idReaction']);
                    $res[$v['value']['idReaction'] . '|' . $v['value']['pageTag']]['parameters'] = $params[$v['value']['idReaction']] ?? [];
                    $res[$v['value']['idReaction'] . '|' . $v['value']['pageTag']]['parameters']['pageTag'] = $v['value']['pageTag'];
                }
                $res[$v['value']['idReaction'] . '|' . $v['value']['pageTag']]['reactions'][] = $v['value'];
            }
        }
        ksort($res);

        return $res;
    }

    public function getActionParameters($page, $idReaction = null)
    {
        if ($this->entryManager->isEntry($page)) {
            return $this->getActionParametersFromEntry($page, $idReaction = null);
        } else {
            return $this->getActionParametersFromPage($page, $idReaction = null);
        }
    }

    public function getActionParametersFromPage($page, $idReaction = null)
    {
        $p = $this->wiki->LoadPage($page);
        if (!empty($p)) {
            $params = [];
            $this->appendParamsFromActionDefinition($params, $p['body']);
            if (!empty($params)) {
                if ($idReaction != null && isset($params[$idReaction])) {
                    return [$idReaction => $params[$idReaction]];
                } else {
                    ksort($params);

                    return $params;
                }
            }
        }

        return [];
    }

    public function getActionParametersFromEntry($entryId, $idReaction = null)
    {
        $entry = $this->entryManager->getOne($entryId);
        $params = [];
        if (!empty($entry) && !empty($entry['id_typeannonce'])) {
            $formId = $entry['id_typeannonce'];
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
                } else {
                    ksort($params);

                    return $params;
                }
            }
        }

        return $params;
    }

    protected function appendParamsFromActionDefinition(array &$params, string $text)
    {
        if (preg_match_all('/{{reactions(?:\s([^}]*))?\s*}}/Ui', $text, $matches)) {
            foreach ($matches[0] as $id => $m) {
                $paramText = $matches[1][$id];
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"|\s*/U', $paramText, $paramMatches)) {
                    $k = array_search('title', $paramMatches[1]);
                    if ($k === false) {
                        $paramMatches[1][] = 'title';
                        $k = array_search('title', $paramMatches[1]);
                        $paramMatches[2][$k] = _t(ReactionManager::DEFAULT_TITLE_T);
                        $paramMatches[0][] = "title=\"{$paramMatches[2][$k]}\"";
                    }
                    $title = $paramMatches[2][$k];
                    $k = array_search('labels', $paramMatches[1]);
                    if ($k === false) {
                        $paramMatches[1][] = 'labels';
                        $k = array_search('labels', $paramMatches[1]);
                        $paramMatches[2][$k] = implode(',', array_map('_t', ReactionManager::DEFAULT_LABELS_T));
                        $paramMatches[0][] = "labels=\"{$paramMatches[2][$k]}\"";
                    }
                    $labels = array_map('trim', explode(',', $paramMatches[2][$k]));
                    $labelsWithId = [];
                    foreach ($labels as $lab) {
                        $id = \URLify::slug($lab); //generate the id from the label
                        $labelsWithId[$id] = $lab;
                    }
                    $paramMatches[2][$k] = $labelsWithId;
                    $ids = array_keys($labelsWithId);
                    $k = array_search('images', $paramMatches[1]);
                    if ($k === false) {
                        $paramMatches[1][] = 'images';
                        $k = array_search('images', $paramMatches[1]);
                        $paramMatches[2][$k] = implode(',', ReactionManager::DEFAULT_IMAGES);
                        $paramMatches[0][] = "images=\"{$paramMatches[2][$k]}\"";
                    }
                    $images = array_map('trim', explode(',', $paramMatches[2][$k]));
                    $htmlImages = [];
                    foreach ($images as $i => $img) {
                        $image = empty($img)
                            ? ''
                            : trim($this->wiki->render('@core/_reactions_images.twig', [
                                'image' => $img,
                                'id' => 'image',
                            ]));
                        $htmlImages[$ids[$i]] = $image;
                    }
                    $paramMatches[2][$k] = $htmlImages;

                    $reactionId = \URLify::slug($title); //generate the id from the title
                    foreach ($paramMatches[0] as $idM => $paramMatch) {
                        $params[$reactionId][$paramMatches[1][$idM]] = $paramMatches[2][$idM];
                    }
                }
            }
        }
    }

    /**
     * to ensure backward compatibility with old reactions from lms extension.
     */
    protected function appendParametersFromField(array &$params, string $tag, ?ReactionsField $field = null)
    {
        $labels = [];
        $images = [];
        if (is_null($field) && !empty($tag)) {
            $entry = $this->entryManager->getOne($tag);
            if (!empty($entry['id_typeannonce'])) {
                $form = $this->formManager->getOne($entry['id_typeannonce']);
                if (!empty($form['prepared'])) {
                    $reactionsFields = array_filter($form['prepared'], function ($intField) {
                        return $intField instanceof ReactionsField;
                    });
                    if (!empty($reactionsFields)) {
                        // first with name equal to 'reactions'
                        foreach ($reactionsFields as $intField) {
                            if ($intField->getName() === 'reactions') {
                                $field = $intField;
                                break;
                            }
                        }
                        if (empty($field)) {
                            // or first with empty name
                            foreach ($reactionsFields as $intField) {
                                if (empty($intField->getName()) || trim($intField->getName()) === '') {
                                    $field = $intField;
                                    break;
                                }
                            }
                        }
                        if (empty($field)) {
                            // or first
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
                    : trim($this->wiki->render('@core/_reactions_images.twig', [
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

    public function getAllReactionInfos($idReaction, $page)
    {
        return $this->getActionParameters($page)[$idReaction] ?? null;
    }

    public function addUserReaction($pageTag, $values)
    {
        if (!$this->wiki->getUser()) {
            throw new \Exception('Unauthorized');
        }

        return $this->tripleStore->create(
            $pageTag,
            self::TYPE_URI,
            json_encode([
                'user' => $values['userName'],
                'idReaction' => $values['reactionId'],
                'id' => $values['id'],
                'date' => $values['date'],
            ]),
            '',
            ''
        );
    }

    public function deleteUserReaction($pageTag, $reactionId, $id, $user): bool
    {
        if (!isset($reactionId) || $reactionId === '') {
            throw new \Exception('ReactionId not specified');
        }
        if (!isset($id) || $id === '') {
            throw new \Exception('Reaction value not specified');
        }

        $connectedUser = $this->wiki->getUser();
        if (!$this->wiki->UserIsAdmin() && (empty($connectedUser) || $connectedUser['name'] !== $user)) {
            throw new \Exception('Unauthorized');
        }

        if ($this->entryManager->isEntry($pageTag) && $reactionId == 'reactionField') {
            return $this->tripleStore->delete(
                $pageTag,
                self::TYPE_URI,
                null,
                '',
                '',
                "(`value` LIKE '%\"user\":\"{$this->dbService->escape($user)}\"%')" .
                'AND' .
                "(`value` LIKE '%\"id\":\"{$this->dbService->escape($id)}\"%')" .
                'AND' .
                "(`value` NOT LIKE '%\"idReaction\":\"%')" .
                'AND' .
                "(`value` NOT LIKE '%\"date\":\"%')"
            );
        } else {
            return $this->tripleStore->delete($pageTag, self::TYPE_URI, null, '', '', 'value LIKE \'%user":"' . $user . '","idReaction":"' . $reactionId . '","id":"' . $id . '"%\'');
        }
    }
}

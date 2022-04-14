<?php

namespace YesWiki\Core\Service;

use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TripleStore;
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
                if (!isset($res[$resKey]) || !isset($res[$resKey]['parameters'])) {
                    $params = $this->getParametersFromField($v['value']['pageTag']);
                    if (!isset($res[$resKey])) {
                        $res[$resKey] = [];
                    }
                    $res[$resKey]['parameters'] = $params;
                    $res[$resKey]['parameters']['pageTag'] = $v['value']['pageTag'];
                }
                $res[$resKey]['reactions'][]=array_merge([
                    'idReaction' => $idReaction,
                ], $v['value']);
            } else {
                // get title and reaction labels for choosen reaction id in choosen page page
                if (!isset($res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['parameters'])) {
                    $params = $this->getActionParameters($v['value']['pageTag'], $v['value']['idReaction']);
                    $res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['parameters'] = $params[$v['value']['idReaction']] ?? [];
                    $res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['parameters']['pageTag'] = $v['value']['pageTag'];
                }
                $res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['reactions'][]=$v['value'];
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
            $params = $this->extractParamsFromActionDefinition($p['body']);
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
                        $params = array_merge($params, $this->extractParamsFromActionDefinition($entry[$field->getPropertyName()]));
                    }
                }
                $fieldParams = $this->getParametersFromField($entryId);
                if (!empty($fieldParams)) {
                    $params = array_merge($params, [ 'reactionField' => $fieldParams]);
                }
            }
            if (!empty($params)) {
                if ($idReaction != null && isset($params[$idReaction])) {
                    return [$idReaction => $params[$idReaction]];
                } else {
                    ksort($params);
                    return $params;
                }
            }
        }
        return $params;
    }

    protected function extractParamsFromActionDefinition(string $text): array
    {
        $params = [];
        if (preg_match_all('/{{reactions(?:\s([^}]*))?\s*}}/Ui', $text, $matches)) {
            foreach ($matches[0] as $id => $m) {
                $paramText = $matches[1][$id];
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"|\s*/U', $paramText, $paramMatches)) {
                    $k = array_search('title', $paramMatches[1]);
                    if ($k === false) {
                        $paramMatches[1][] = 'title';
                        $k = array_search('title', $paramMatches[1]);
                        $paramMatches[2][$k] = _t(ReactionManager::DEFAULT_TITLE_T);
                    }
                    $title = $paramMatches[2][$k];
                    $k = array_search('labels', $paramMatches[1]);
                    if ($k === false) {
                        $paramMatches[1][] = 'labels';
                        $k = array_search('labels', $paramMatches[1]);
                        $paramMatches[2][$k] = implode(',', array_map('_t', ReactionManager::DEFAULT_LABELS_T));
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
                    }
                    $images = array_map('trim', explode(',', $paramMatches[2][$k]));
                    $htmlImages = [];
                    foreach ($images as $i => $img) {
                        $image = '';
                        if (preg_match("/.(gif|jpeg|png|jpg|svg|webp)$/i", $img) == 1) { //image
                            $image = '<img class="img-responsive" src="'.$img.'" alt="reaction image" />';
                        } elseif (preg_match('/\p{S}/u', $img) == 1) { //emoji
                            $image = '<span class="reaction-emoji">'.$img.'</span>';
                        } elseif (preg_match("/^(fa[srb]? fa-*)/i", $img) == 1) { //class
                            $image = '<i class="reaction-fa-icons '.$img.'"></i>';
                        }
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
        return $params;
    }

    /**
     * to ensure backward compatibility with old reactions from lms extension
     * @param string $tag
     * @return array $params
     */
    protected function getParametersFromField(string $tag): array
    {
        $entry = $this->entryManager->getOne($tag);
        $formId = $entry['id_typeannonce'];
        $field = $this->formManager->findFieldFromNameOrPropertyName('reactions', $formId);
        $labels = [];
        $images = [];
        if (!empty($field)) {
            $data = $field->jsonSerialize();
            $ids = $data['ids'];
            $titles = $data['titles'];
            foreach ($ids as $key => $id) {
                $labels[$id] = $titles[$key] ?? $id;
            }
            $form = $this->formManager->getOne($formId);
            $fieldTemplate = array_filter($form['template'], function ($fTemplate) {
                return $fTemplate[0] == 'reactions';
            });
            $fieldTemplate = $fieldTemplate[array_key_first($fieldTemplate)];
            $fImages = isset($fieldTemplate[4]) ? explode(',', $fieldTemplate[4]) : [];
            foreach ($ids as $key => $id) {
                if (!empty($fImages[$key])) {
                    if (file_exists("files/{$fImages[$key]}")) {
                        $images[$id] = "<img class=\"reaction-img\" alt=\"icon $id\" src=\"files/{$fImages[$key]}\"/>";
                    }
                } else {
                    if (file_exists("tools/lms/presentation/images/mikone-$id.svg")) {
                        $images[$id] = "<img class=\"reaction-img\" alt=\"icon $id\" src=\"tools/lms/presentation/images/mikone-$id.svg\"/>";
                    }
                }
            }
        }

        return [
            'images' => $images,
            'labels' => $labels,
            'pageTag' => $tag,
            'title' => _t('REACTION_ON_ENTRY')
        ];
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

    public function deleteUserReaction($pageTag, $reactionId, $id, $user)
    {
        if (!isset($reactionId) || $reactionId === '') {
            throw new \Exception('ReactionId not specified');
        }
        if (!isset($id) || $id === '') {
            throw new \Exception('Reaction value not specified');
        }

        $connectedUser = $this->wiki->getUser();
        if (!$this->wiki->UserIsAdmin() || empty($connectedUser) || $connectedUser['name'] !== $user) {
            throw new \Exception('Unauthorized');
        }

        if ($this->entryManager->isEntry($pageTag) && $reactionId == 'reactionField') {
            $this->tripleStore->delete(
                $pageTag,
                self::TYPE_URI,
                null,
                '',
                '',
                "(`value` LIKE '%\"user\":\"{$this->dbService->escape($user)}\"%')".
                "AND".
                "(`value` LIKE '%\"id\":\"{$this->dbService->escape($id)}\"%')".
                "AND".
                "(`value` NOT LIKE '%\"idReaction\":\"%')".
                "AND".
                "(`value` NOT LIKE '%\"date\":\"%')"
            );
        } else {
            $this->tripleStore->delete($pageTag, self::TYPE_URI, null, '', '', 'value LIKE \'%user":"'.$user.'","idReaction":"'.$reactionId.'","id":"'.$id.'"%\'');
        }
    }
}

<?php

namespace YesWiki\Core\Service;

use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

class ReactionManager
{
    protected $wiki;
    protected $dbService;
    protected $tripleStore;

    public const TYPE_URI = 'https://yeswiki.net/vocabulary/reaction';

    protected $cachedReactions;

    public function __construct(Wiki $wiki, TripleStore $tripleStore)
    {
        $this->wiki = $wiki;
        $this->tripleStore = $tripleStore;
    }

    public function getReactions($pageTag = '', $ids = [], $user = '')
    {
        $res = [];
        // get reactions in db
        $val = $this->tripleStore->getAll($pageTag, self::TYPE_URI, '', '');
        foreach ($val as $v) {
            $v['value'] = json_decode($v['value'], true);
            if (!empty($user) && $user != $v['value']['user']) {
                continue;
            }
            if (!empty($ids) && !in_array($v['value']['idReaction'], $ids)) {
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
            // get title and reaction labels for choosen reaction id in choosen page page
            if (!isset($res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['parameters'])) {
                $params = $this->getActionParametersFromPage($v['value']['pageTag'], $v['value']['idReaction']);
                $res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['parameters'] = $params[$v['value']['idReaction']];
                $res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['parameters']['pageTag'] = $v['value']['pageTag'];
            }
            $res[$v['value']['idReaction'].'|'.$v['value']['pageTag']]['reactions'][]=$v['value'];
        }
        ksort($res);
        return $res;
    }

    public function getActionParametersFromPage($page, $idReaction = null)
    {
        $p = $this->wiki->LoadPage($page);
        if (preg_match_all('/{{reactions\s([^}]*)\s*}}/Ui', $p['body'], $matches)) {
            $params = [];
            foreach ($matches[0] as $id => $m) {
                $paramText = $matches[1][$id];
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"/U', $paramText, $paramMatches)) {
                    $k = array_search('title', $paramMatches[1]);
                    $title = $paramMatches[2][$k];
                    $k = array_search('labels', $paramMatches[1]);
                    $labels = array_map('trim', explode(',', $paramMatches[2][$k]));
                    $labelsWithId = [];
                    foreach ($labels as $lab) {
                        $id = \URLify::slug($lab); //generate the id from the label
                        $labelsWithId[$id] = $lab;
                    }
                    $paramMatches[2][$k] = $labelsWithId;
                    $ids = array_keys($labelsWithId);
                    $k = array_search('images', $paramMatches[1]);
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
            if ($idReaction != null && isset($params[$idReaction])) {
                return [$idReaction => $params[$idReaction]];
            } else {
                return ksort($params);
            }
        }
    }

    public function getAllReactionInfos($idReaction, $page)
    {
        return $this->getActionParametersFromPage($page)[$idReaction] ?? null;
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

        if (!$this->wiki->UserIsAdmin() && !$this->wiki->UserIsOwner($id)) {
            throw new \Exception('Unauthorized');
        }

        $this->tripleStore->delete($pageTag, self::TYPE_URI, null, '', '', 'value LIKE \'%user":"'.$user.'","idReaction":"'.$reactionId.'","id":"'.$id.'"%\'');
    }
}

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

    public function getAllReactions($pageTag = '', $ids = [])
    {
        $res = [];
        // get reactions in db
        $val = $this->tripleStore->getAll($pageTag, self::TYPE_URI, '', '');
        foreach ($val as $v) {
            $v['value'] = json_decode($v['value'], true);
            if (!empty($pageTag)) {
                $v['value']['pageTag'] = $pageTag;
            } else {
                $v['value']['pageTag'] = $v['resource'];
            }
            $res[$v['value']['idReaction']][]=$v['value'];
        }
        return $res;
    }

    public function getActionParametersFromPage($page)
    {
        $p = $this->wiki->LoadPage($page);
        if (preg_match_all('/{{reactions\s([^}]*)}}/Ui', $p['body'], $matches)) {
            $params = [];
            foreach ($matches[0] as $id => $m) {
                $paramText = $matches[1][$id];
                if (preg_match_all('/([a-zA-Z0-9_]*)=\"(.*)\"/U', $paramText, $paramMatches)) {
                    $reactionId = \URLify::slug($paramMatches[2][0]); //generate the id from the title
                    foreach ($paramMatches[0] as $idM => $paramMatch) {
                        $params[$reactionId][$paramMatches[1][$idM]] = $paramMatches[2][$idM];
                    }
                }
            }
            return $params;
        }
    }

    public function getUserReactions($user, $pageTag = '', $ids = [])
    {
        $res =[];
        // TODO : make more efficient db query
        $userReactions = $this->getAllReactions($pageTag, $ids);
        foreach ($userReactions as $reactions) {
            foreach ($reactions as $v) {
                if (isset($v['user']) && $v['user'] == $user && !empty($v['id'])) {
                    $res[] = $v;
                }
            }
        }
        return $res;
    }

    public function addUserReaction($pageTag, $values)
    {
        if (!$this->wiki->getUser()) {
            throw new \Exception('Unauthorized');
        }
        return $this->tripleStore->create($pageTag, self::TYPE_URI, json_encode(['user' => $values['userName'], 'idReaction' => $values['reactionId'], 'id' => $values['id']]), '', '');
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

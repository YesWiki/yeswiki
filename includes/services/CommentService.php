<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;
use YesWiki\Security\Service\HashCashService;

class CommentService
{
    protected $wiki;
    protected $dbService;
    protected $pageManager;
    protected $params;


    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        PageManager $pageManager,
        ParameterBagInterface $params
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->pageManager = $pageManager;
        $this->params = $params;
    }
    public function addCommentIfAutorized($content, $idComment = '')
    {
        if (!$this->wiki->getUser()) {
            return [
                'code' => 401,
                'error' => _t('USER_MUST_BE_LOGGED_TO_COMMENT')
            ];
        } else {
            if ($this->wiki->HasAccess("comment", $content['pagetag']) && $this->wiki->Loadpage($content['pagetag'])) {
                if ($this->wiki->config['use_hashcash']) {
                    require_once('tools/security/secret/wp-hashcash.lib');
                    if (!isset($content["hashcash_value"]) || ($content["hashcash_value"] != hashcash_field_value())) {
                        return [
                            'code' => 400,
                            'error' => _t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT')
                        ];
                    }
                }
                if (empty($idComment)) {
                    // find number
                    $sql = 'SELECT MAX(SUBSTRING(tag, 8) + 0) AS comment_id'
                        . ' FROM ' . $this->wiki->GetConfigValue('table_prefix') . 'pages'
                        . ' WHERE comment_on != ""';
                    if ($lastComment = $this->wiki->LoadSingle($sql)) {
                        $num = $lastComment['comment_id'] + 1;
                    } else {
                        $num = "1";
                    }
                    $idComment = "Comment".$num;
                }

                $body = trim($content["body"]);
                if (!$body) {
                    return [
                        'code' => 400,
                        'error' => _t('COMMENT_EMPTY_NOT_SAVED')
                    ];
                } else {
                    // store new comment
                    $this->wiki->SavePage($idComment, $body, $content['pagetag']);
                    $comment = $this->wiki->LoadPage($idComment);
                    $com['tag'] = $comment['tag'];
                    $com['rawbody'] = $comment['body'];
                    $com['body'] = $this->wiki->Format($comment['body']);
                    $com['user'] = $comment['user'];
                    $com['linkuser'] = $this->wiki->href('', $comment['user']);
                    $com['userpicture'] = 'https://colibris-universite.org/mooc-democratie-v2/files/avatar-colibris.png';
                    $com['date'] = 'le '.date("d.m.Y à H:i:s", strtotime($comment['time']));
                    if ($this->wiki->HasAccess('comment', $comment['tag'])) {
                        $com['linkcomment'] = $this->wiki->href('pages/'.$comment['tag'].'/comments', 'api');
                    }
                    if ($this->wiki->HasAccess('write', $comment['tag']) || $this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin($comment['tag'])) {
                        $com['linkeditcomment'] = $this->wiki->href('edit', $comment['tag']);
                    }
                    if ($this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin()) {
                        $com['linkdeletecomment'] = $this->wiki->href('comments/'.$comment['tag'], 'api');
                        //$this->wiki->href('deletepage', $comment['tag']);
                    }
                    $com['reponses'] = $this->getCommentList($comment['tag'], false);
                    return [
                        'code' => 200,
                        'success' => _t('COMMENT_PUBLISHED'),
                        'html' => $this->wiki->render("@core/comment.twig", ['comment' => $com])
                    ];
                }
            } else {
                return [
                    'code' => 403,
                    'error' => _t('USER_NOT_ALLOWED_TO_COMMENT')
                ];
            }
        }
    }

    public function getCommentList($tag, $first = true)
    {
        $com = array();
        $com['first'] = $first;
        $com['tag'] = $tag;
        $com['comments'] = array();
        $comments = $this->wiki->LoadComments($tag);
        if ($comments) {
            foreach ($comments as $i => $comment) {
                $com['comments'][$i]['tag'] = $comment['tag'];
                $com['comments'][$i]['rawbody'] = $comment['body'];
                $com['comments'][$i]['body'] = $this->wiki->Format($comment['body']);
                $com['comments'][$i]['user'] = $comment['user'];
                $com['comments'][$i]['linkuser'] = $this->wiki->href('', $comment['user']);
                $com['comments'][$i]['userpicture'] = 'https://www.dailymoss.com/wp-content/uploads/2019/08/funny-profile-pic26.jpg';
                $com['comments'][$i]['date'] = 'le '.date("d.m.Y à H:i:s", strtotime($comment['time']));
                if ($this->wiki->HasAccess('comment', $comment['tag'])) {
                    $com['comments'][$i]['linkcomment'] = $this->wiki->href('pages/'.$comment['tag'].'/comments', 'api');
                }
                if ($this->wiki->HasAccess('write', $comment['tag']) || $this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin($comment['tag'])) {
                    $com['comments'][$i]['linkeditcomment'] = $this->wiki->href('edit', $comment['tag']);
                }
                if ($this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin()) {
                    $com['comments'][$i]['linkdeletecomment'] = $this->wiki->href('comments/'.$comment['tag'].'/delete', 'api');
                    //$this->wiki->href('deletepage', $comment['tag']);
                }
                $com['comments'][$i]['reponses'] = $this->getCommentList($comment['tag'], false);
            }
        }

        return $this->wiki->render("@core/comment-list.twig", $com);
    }

    public function getCommentForm($tag)
    {
        $options = [];
        if (!$this->wiki->getUser()) {
            $options['alerts'][] = [
                'class' => 'info',
                'text' => _t('USER_MUST_BE_LOGGED_TO_COMMENT')
            ];
        } else {
            if ($this->wiki->HasAccess('comment', $tag)) {
                $hashCashCode = '';
                if ($this->wiki->config['use_hashcash']) {
                    $hashCash = $this->wiki->services->get(HashCashService::class);
                    $hashCashCode = $hashCash->getJavascriptCode('post-comment');
                }
                $options = [
                    'pagetag' => $tag,
                    'formlink' => $this->wiki->href('comments', 'api'),
                    'hashcash' => $hashCashCode
                ];
            } else {
                $options['alerts'][] = [
                    'class' => 'warning',
                    'text' => _t('USER_NOT_ALLOWED_TO_COMMENT')
                ];
            }
        }
        return $this->wiki->render("@core/comment-form.twig", $options);
    }
}

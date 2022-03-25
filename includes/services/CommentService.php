<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Wiki;
use YesWiki\Security\Service\HashCashService;

class CommentService
{
    protected $wiki;
    protected $dbService;
    protected $aclService;
    protected $pageManager;
    protected $params;
    protected $pagesWhereCommentWereRendered;


    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        AclService $aclService,
        PageManager $pageManager,
        ParameterBagInterface $params
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->aclService = $aclService;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->pagesWhereCommentWereRendered = [];
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
                if ($this->params->get('use_hashcash')) {
                    require_once('tools/security/secret/wp-hashcash.lib');
                    if (!isset($content["hashcash_value"]) || ($content["hashcash_value"] != hashcash_field_value())) {
                        return [
                            'code' => 400,
                            'error' => _t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT')
                        ];
                    }
                }
                if (empty($idComment)) {
                    $newComment = true;
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
                } else {
                    $newComment = false;
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
                    if ($newComment) {
                        // default ACLs for comments : visible for all, writable by owner, commentable like parent.
                        // TODO: config param for default comments rights?
                        $this->aclService->save($idComment, 'write', '%');
                        $this->aclService->save($idComment, 'read', '*');
                        $this->aclService->save($idComment, 'comment', '+');
                    }

                    $comment = $this->wiki->LoadPage($idComment);
                    $com['tag'] = $comment['tag'];
                    $com['rawbody'] = $comment['body'];
                    $com['body'] = $this->wiki->Format($comment['body']);
                    $com['user'] = $comment['user'];
                    $com['usercolor'] = $this->genColorCodeFromText($comment['user']);
                    $com['linkuser'] = $this->wiki->href('', $comment['user']);
                    $com['userpicture'] = !empty($this->wiki->config['default_comment_avatar']) ? $this->wiki->config['default_comment_avatar'] : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='".str_replace('#', '%23', $com['usercolor'])."' class='bi bi-person-circle' viewBox='0 0 16 16'%3E%3Cpath d='M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'/%3E%3Cpath fill-rule='evenodd' d='M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z'/%3E%3C/svg%3E";
                    $com['date'] = 'le '.date("d.m.Y à H:i:s", strtotime($comment['time']));
                    if ($this->wiki->HasAccess('comment', $comment['tag'])) {
                        $com['linkcomment'] = $this->wiki->href('pages/'.$comment['tag'].'/comments', 'api');
                    }
                    if ($this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin()) {
                        $com['linkeditcomment'] = $this->wiki->href('edit', $comment['tag']);
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

    /**
    * Load comments for given page.
    *
    * @param string $tag Page name (Ex : "PagePrincipale") if empty, all comments
    * @return array All comments and their corresponding properties.
    */
    public function loadComments($tag)
    {
        $query = 'SELECT * FROM ' . $this->wiki->config['table_prefix'] . 'pages ' . 'WHERE ';
        if (empty($tag)) {
            $query .= 'comment_on != "" ';
        } else {
            $query .= 'comment_on = "' . mysqli_real_escape_string($this->wiki->dblink, $tag) . '" ';
        }
        $query .= 'AND latest = "Y" ' . 'ORDER BY substring(tag, 8) + 0';
        return $this->wiki->LoadAll($query);
    }

    public function getCommentList($tag, $first = true, $comments = null)
    {
        $com = array();
        $com['first'] = $first;
        $com['tag'] = $tag;
        $com['comments'] = array();
        $comments = is_array($comments) ? $comments : $this->loadComments($tag);
        if ($comments) {
            foreach ($comments as $i => $comment) {
                $com['comments'][$i]['tag'] = $comment['tag'];
                $com['comments'][$i]['rawbody'] = $comment['body'];
                $com['comments'][$i]['body'] = $this->wiki->Format($comment['body']);
                $com['comments'][$i]['user'] = $comment['user'];
                $com['comments'][$i]['linkuser'] = $this->wiki->href('', $comment['user']);
                $com['comments'][$i]['usercolor'] = $this->genColorCodeFromText($comment['user']);
                $com['comments'][$i]['userpicture'] = !empty($this->wiki->config['default_comment_avatar']) ? $this->wiki->config['default_comment_avatar'] : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='".str_replace('#', '%23', $com['comments'][$i]['usercolor'])."' class='bi bi-person-circle' viewBox='0 0 16 16'%3E%3Cpath d='M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'/%3E%3Cpath fill-rule='evenodd' d='M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z'/%3E%3C/svg%3E";
                $com['comments'][$i]['date'] = 'le '.date("d.m.Y à H:i:s", strtotime($comment['time']));
                if ($this->wiki->HasAccess('comment', $comment['tag'])) {
                    $com['comments'][$i]['linkcomment'] = $this->wiki->href('pages/'.$comment['tag'].'/comments', 'api');
                }
                if ($this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin()) {
                    $com['comments'][$i]['linkeditcomment'] = $this->wiki->href('edit', $comment['tag']);
                    $com['comments'][$i]['linkdeletecomment'] = $this->wiki->href('comments/'.$comment['tag'].'/delete', 'api');
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

    public function renderCommentsForPage($tag, $showOnlyOnce = true)
    {
        $output = '';
        // if the comments were allready render in page, we don't show them again
        if ($showOnlyOnce && in_array($tag, $this->pagesWhereCommentWereRendered)) {
            return '';
        }
        $aclsService = $this->wiki->services->get(AclService::class);
        $hasAccessComment = $aclsService->hasAccess('comment', $tag);
        $HasAccessRead = $aclsService->HasAccess("read", $tag);

        if ($HasAccessRead) {
            $comments = $this->loadComments($tag);
            $coms = $this->getCommentList($tag, true, $comments);
            if ($hasAccessComment === 'comments-closed') {
                if (!empty($comments)) {
                    $output .= $coms;
                    $output .= '<div class="alert alert-info comments-closed-info">'._t('COMMENTS_CURRENTLY_CLOSED').'.</div>';
                }
            } else {
                $output .= $coms;
                if ($hasAccessComment) {
                    $output .= $this->getCommentForm($tag);
                } else {
                    if (! $this->wiki->GetUser()) {
                        $output .= '<div class="comments-connect-info"><a href="#LoginModal" role="button" data-toggle="modal"><i class="fa fa-user"></i> '._t('COMMENT_LOGIN').'.</a></div>';
                    } else {
                        $output .= '<div class="alert alert-info comments-acls-info">'._t('COMMENT_NOT_ENOUGH_RIGHTS').'</div>';
                    }
                }
            }
        }

        // indicate that those comments on page were already rendered once
        $this->pagesWhereCommentWereRendered[] = $tag;

        return $output;
    }

    /*
    * Outputs a color (#000000) based Text input thanks https://gist.github.com/mrkmg/1607621
    *
    * @param $text String of text
    * @param $min_brightness Integer between 0 and 100
    * @param $spec Integer between 2-10, determines how unique each color will be
    */

    public function genColorCodeFromText($text, $min_brightness=100, $spec=10)
    {
        // Check inputs
        if (!is_int($min_brightness)) {
            throw new Exception("$min_brightness is not an integer");
        }
        if (!is_int($spec)) {
            throw new Exception("$spec is not an integer");
        }
        if ($spec < 2 or $spec > 10) {
            throw new Exception("$spec is out of range");
        }
        if ($min_brightness < 0 or $min_brightness > 255) {
            throw new Exception("$min_brightness is out of range");
        }


        $hash = md5($text);  //Gen hash of text
        $colors = array();
        for ($i=0;$i<3;$i++) {
            $colors[$i] = max(array(round(((hexdec(substr($hash, $spec*$i, $spec)))/hexdec(str_pad('', $spec, 'F')))*255),$min_brightness));
        } //convert hash into 3 decimal values between 0 and 255

        if ($min_brightness > 0) {  //only check brightness requirements if min_brightness is about 100
            while (array_sum($colors)/3 < $min_brightness) {  //loop until brightness is above or equal to min_brightness
                for ($i=0;$i<3;$i++) {
                    $colors[$i] += 10;
                }
            }
        }	//increase each color by 10

        $output = '';

        for ($i=0;$i<3;$i++) {
            $output .= str_pad(dechex($colors[$i]), 2, 0, STR_PAD_LEFT);
        }  //convert each color to hex and append to output

        return '#'.$output;
    }
}

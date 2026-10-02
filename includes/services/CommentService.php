<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Core\Entity\Event;
use YesWiki\Wiki;

class CommentService implements EventSubscriberInterface
{
    protected $wiki;
    protected $aclService;
    protected $dbService;
    protected $eventDispatcher;
    protected $mailer;
    protected $pageManager;
    protected $params;
    protected $pagesWhereCommentWereRendered;
    protected $userManager;
    protected $templateEngine;
    protected $commentsActivated;

    public function __construct(
        Wiki $wiki,
        DbService $dbService,
        AclService $aclService,
        EventDispatcher $eventDispatcher,
        Mailer $mailer,
        PageManager $pageManager,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        UserManager $userManager
    ) {
        $this->wiki = $wiki;
        $this->dbService = $dbService;
        $this->aclService = $aclService;
        $this->eventDispatcher = $eventDispatcher;
        $this->mailer = $mailer;
        $this->pageManager = $pageManager;
        $this->templateEngine = $templateEngine;
        $this->userManager = $userManager;
        $this->params = $params;
        $this->pagesWhereCommentWereRendered = [];
        $this->commentsActivated = $this->params->get('comments_activated');
    }

    public static function getSubscribedEvents()
    {
        return [
            'comment.created' => 'sendEmailAfterCreate',
            'comment.updated' => 'sendEmailAfterModify',
            'comment.deleted' => 'sendEmailAfterDelete',
        ];
    }

    /**
     * Post a comment, or rewrite one that is already there.
     *
     * An edit is authorized against the comment being rewritten, and its parent is read back from
     * it, so no other page can be reached through the parent named in the request.
     */
    public function addCommentIfAuthorized($content, $idComment = '')
    {
        if (!$this->wiki->getUser()) {
            return [
                'code' => 401,
                'error' => _t('USER_MUST_BE_LOGGED_TO_COMMENT'),
            ];
        }
        $content['pagetag'] = $content['pagetag'] ?? '';
        if (!empty($idComment)) {
            $edited = $this->pageManager->getOne($idComment, null, true, true);
            if (empty($edited) || empty($edited['comment_on'])) {
                return [
                    'code' => 404,
                    'error' => _t('COMMENT_NOT_FOUND'),
                ];
            }
            if (!$this->aclService->hasAccess('write', $idComment)) {
                return [
                    'code' => 403,
                    'error' => _t('NOT_AUTORIZED_TO_EDIT_COMMENT'),
                ];
            }
            $content['pagetag'] = $edited['comment_on'];
        }
        if ($this->wiki->HasAccess('comment', $content['pagetag']) && $this->wiki->Loadpage($content['pagetag'])) {
            if (empty($idComment)) {
                $newComment = true;
                $sql = 'SELECT MAX(SUBSTRING(tag, 8) + 0) AS comment_id'
                    . ' FROM ' . $this->wiki->GetConfigValue('table_prefix') . 'pages'
                    . ' WHERE comment_on != ""';
                if ($lastComment = $this->wiki->LoadSingle($sql)) {
                    $num = $lastComment['comment_id'] + 1;
                } else {
                    $num = '1';
                }
                $idComment = 'Comment' . $num;
            } else {
                $newComment = false;
            }

            $body = trim($content['body']);
            if (!$body) {
                return [
                    'code' => 400,
                    'error' => _t('COMMENT_EMPTY_NOT_SAVED'),
                ];
            }
            $body = $this->wiki->services->get(HtmlPurifierService::class)->cleanHTML($body);
            $this->wiki->SavePage($idComment, $body, $content['pagetag']);
            if ($newComment) {
                $parentCommentAcl = $this->aclService->load($content['pagetag'], 'comment', false);
                $parentCommentAcl = empty($parentCommentAcl) || empty($parentCommentAcl['list']) ? $this->aclService->load($content['pagetag'], 'comment', true) : $parentCommentAcl;
                $parentCommentAcl = $parentCommentAcl['list'];
                $this->aclService->save($idComment, 'write', '%');
                $this->aclService->save($idComment, 'read', '*');
                $this->aclService->save($idComment, 'comment', $parentCommentAcl);
            }

            $comment = $this->wiki->LoadPage($idComment);
            $com['tag'] = $comment['tag'];
            $com['commentOn'] = $comment['comment_on'];
            $com['rawbody'] = $comment['body'];
            $oldPage = $GLOBALS['wiki']->GetPageTag();
            $oldPageArray = $GLOBALS['wiki']->page;
            $GLOBALS['wiki']->tag = $comment['tag'];
            $GLOBALS['wiki']->page = $comment;
            $com['body'] = $GLOBALS['wiki']->Format($comment['body']);
            $GLOBALS['wiki']->tag = $oldPage;
            $GLOBALS['wiki']->page = $oldPageArray;
            $this->setUserData($comment, 'user', $com);
            $this->setUserData($comment, 'owner', $com);
            $com['date'] = 'le ' . date('d.m.Y à H:i:s', strtotime($comment['time']));
            if ($this->wiki->HasAccess('comment', $comment['tag'])) {
                $com['linkcomment'] = $this->wiki->href('pages/' . $comment['tag'] . '/comments', 'api');
            }
            if ($this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin()) {
                $com['linkeditcomment'] = $this->wiki->href('edit', $comment['tag']);
                $com['linkdeletecomment'] = $this->wiki->href("comments/{$comment['tag']}/delete", 'api');
            }
            $com['reponses'] = $this->getCommentList($comment['tag'], false);
            $com['parentPage'] = $this->getParentPage($comment['tag']);
            $errors = $this->eventDispatcher->yesWikiDispatch($newComment ? 'comment.created' : 'comment.updated', [
                'id' => $com['tag'],
                'data' => $com,
            ]);

            return [
                'code' => 200,
                'success' => _t('COMMENT_PUBLISHED'),
                'html' => $this->wiki->render('@core/comment.twig', ['comment' => $com]),
            ] + $errors;
        }

        return [
            'code' => 403,
            'error' => _t('USER_NOT_ALLOWED_TO_COMMENT'),
        ];
    }

    /**
     * delete a comment.
     */
    public function delete(string $commentTag): array
    {
        $comments = $this->loadComments($commentTag, true);
        foreach ($comments as $com) {
            $this->pageManager->deleteOrphaned($com['tag']);
        }
        $comment = $this->pageManager->getOne($commentTag);
        $parentPage = $this->getParentPage($commentTag);
        $this->pageManager->deleteOrphaned($commentTag);
        $errors = $this->eventDispatcher->yesWikiDispatch('comment.deleted', [
            'id' => $comment['tag'],
            'data' => array_merge($comment, [
                'associatedComments' => $comments,
                'parentPage' => $parentPage,
            ]),
        ]);

        return $errors;
    }

    /**
     * Load comments recursivelly.
     *
     * @param string $tag Page name (Ex : "PagePrincipale") if empty, all comments
     *
     * @return array all comments and their corresponding properties
     */
    public function loadCommentsRecursive($tag, bool $bypassAcls = false)
    {
        $comments = $this->loadComments($tag);
        foreach ($comments as $k => $c) {
            $comments[$k]['comments'] = $this->loadCommentsRecursive($c['tag']);
        }

        return $comments;
    }

    /**
     * Load comments for given page.
     *
     * @param string $tag Page name (Ex : "PagePrincipale") if empty, all comments
     *
     * @return array all comments and their corresponding properties
     */
    public function loadComments($tag, bool $bypassAcls = false, $username = null)
    {
        $query = 'SELECT * FROM ' . $this->wiki->config['table_prefix'] . 'pages WHERE ';
        if (empty($tag)) {
            $query .= 'comment_on != "" ';
        } else {
            $query .= "comment_on = \"{$this->dbService->escape($tag)}\" ";
        }
        if (!empty($username)) {
            $query .=
                <<<SQL
            AND (`user` = '{$this->dbService->escape($username)}' OR `owner` = '{$this->dbService->escape($username)}')
            SQL;
        }
        $query .= " AND `tag` != '{$this->dbService->escape($tag)}' ";
        $query .= 'AND latest = "Y" ORDER BY substring(tag, 8) + 0';
        $comments = array_filter($this->wiki->LoadAll($query), function ($comment) {
            return !empty($comment['tag']);
        });

        foreach ($comments as $id => $comment) {
            $parentPage = $this->getParentPage($comment['tag']);
            $comments[$id]['parentTag'] = !empty($parentPage['tag']) ? $parentPage['tag'] : '';
        }

        if (!$bypassAcls) {
            $comments = array_filter($comments, function ($com) {
                return !empty($com['comment_on']) && $this->aclService->hasAccess('read', $com['comment_on']);
            });
        }

        return $comments;
    }

    public function getCommentList($tag, $first = true, $comments = null)
    {
        $com = [];
        $com['first'] = $first;
        $com['tag'] = $tag;
        $com['comments'] = [];
        $comments = is_array($comments) ? $comments : $this->loadComments($tag);
        if ($comments) {
            foreach ($comments as $i => $comment) {
                $com['comments'][$i]['tag'] = $comment['tag'];
                $com['comments'][$i]['commentOn'] = $comment['comment_on'];
                $com['comments'][$i]['rawbody'] = $comment['body'];
                $com['comments'][$i]['body'] = $this->wiki->Format($comment['body']);
                $this->setUserData($comment, 'user', $com['comments'][$i]);
                $this->setUserData($comment, 'owner', $com['comments'][$i]);
                $com['comments'][$i]['date'] = 'le ' . date('d.m.Y à H:i:s', strtotime($comment['time']));
                if ($this->wiki->HasAccess('comment', $comment['tag'])) {
                    $com['comments'][$i]['linkcomment'] = $this->wiki->href('pages/' . $comment['tag'] . '/comments', 'api');
                }
                if ($this->wiki->UserIsOwner($comment['tag']) || $this->wiki->UserIsAdmin()) {
                    $com['comments'][$i]['linkeditcomment'] = $this->wiki->href('edit', $comment['tag']);
                    $com['comments'][$i]['linkdeletecomment'] = $this->wiki->href('comments/' . $comment['tag'] . '/delete', 'api');
                }
                $com['comments'][$i]['reponses'] = $this->getCommentList($comment['tag'], false);
            }
        }

        return $this->wiki->render('@core/comment-list.twig', $com);
    }

    public function getCommentsCount($tag)
    {
        return $this->dbService->count("
            SELECT * FROM {$this->dbService->prefixTable('pages')} 
            WHERE comment_on = '{$this->dbService->escape($tag)}' AND latest = 'Y'
        ");
    }

    private function setUserData(array $comment, string $key, array &$data)
    {
        if (in_array($key, ['user', 'owner'], true) && !empty($comment[$key])) {
            $data[$key] = $comment[$key];
            $data["link$key"] = $this->wiki->href('', $comment[$key]);
            $data["{$key}color"] = $this->genColorCodeFromText($comment[$key]);
            $data["{$key}picture"] =
                !empty($this->wiki->config['default_comment_avatar'])
                ? $this->wiki->config['default_comment_avatar']
                : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='" . str_replace('#', '%23', $data["{$key}color"]) . "' class='bi bi-person-circle' viewBox='0 0 16 16'%3E%3Cpath d='M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'/%3E%3Cpath fill-rule='evenodd' d='M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z'/%3E%3C/svg%3E";
        }
    }

    public function getCommentForm($tag)
    {
        $options = [];
        if (!$this->wiki->getUser()) {
            $options['alerts'][] = [
                'class' => 'info',
                'text' => _t('USER_MUST_BE_LOGGED_TO_COMMENT'),
            ];
        } else {
            if ($this->wiki->HasAccess('comment', $tag)) {
                $page = $this->pageManager->getOne($tag);
                $commentOn = !empty($page['comment_on']) ? $page['comment_on'] : $page['tag'];
                $tempTag = ($this->wiki->config['temp_tag_for_entry_creation'] ?? null) . '_' . bin2hex(random_bytes(10));
                $options = [
                    'pagetag' => $commentOn,
                    'formlink' => $this->wiki->href('comments', 'api'),
                    'tempTag' => $tempTag,
                ];
            } else {
                $options['alerts'][] = [
                    'class' => 'warning',
                    'text' => _t('USER_NOT_ALLOWED_TO_COMMENT'),
                ];
            }
        }

        return $this->wiki->services->get(BotGuard::class)->insertInto($this->wiki->render('@core/comment-form.twig', $options), 'post-comment');
    }

    public function renderCommentsForPage($tag, $showOnlyOnce = true)
    {
        if (!$this->commentsActivated) {
            return '';
        }
        $output = '';
        if ($showOnlyOnce && in_array($tag, $this->pagesWhereCommentWereRendered)) {
            return '';
        }
        $aclsService = $this->wiki->services->get(AclService::class);
        $hasAccessComment = $aclsService->hasAccess('comment', $tag);
        $HasAccessRead = $aclsService->HasAccess('read', $tag);

        if ($HasAccessRead) {
            $comments = $this->loadComments($tag);
            $coms = $this->getCommentList($tag, true, $comments);
            $acl = $aclsService->load($tag, 'comment');
            $options = (!empty($acl['list']) && $acl['list'] == 'comments-closed')
                ? [
                    'commentsClosed' => true,
                    'coms' => !empty($comments) ? $coms : '',
                    'user' => null,
                    'form' => null,
                ]
                : [
                    'commentsClosed' => false,
                    'coms' => $coms,
                    'user' => ($hasAccessComment) ? null : $this->wiki->GetUser(),
                    'form' => ($hasAccessComment) ? $this->getCommentForm($tag) : '',
                ];
            $output = $this->wiki->render('@core/comment-for-page.twig', $options);
        }

        $this->pagesWhereCommentWereRendered[] = $tag;

        return $output;
    }

    public function genColorCodeFromText($text, $min_brightness = 100, $spec = 10)
    {
        if (!is_int($min_brightness)) {
            throw new \Exception("$min_brightness is not an integer");
        }
        if (!is_int($spec)) {
            throw new \Exception("$spec is not an integer");
        }
        if ($spec < 2 or $spec > 10) {
            throw new \Exception("$spec is out of range");
        }
        if ($min_brightness < 0 or $min_brightness > 255) {
            throw new \Exception("$min_brightness is out of range");
        }

        $hash = md5($text);
        $colors = [];
        for ($i = 0; $i < 3; $i++) {
            $colors[$i] = max([round((hexdec(substr($hash, $spec * $i, $spec)) / hexdec(str_pad('', $spec, 'F'))) * 255), $min_brightness]);
        }

        if ($min_brightness > 0) {
            while (array_sum($colors) / 3 < $min_brightness) {
                for ($i = 0; $i < 3; $i++) {
                    $colors[$i] += 10;
                }
            }
        }

        $output = '';

        for ($i = 0; $i < 3; $i++) {
            $output .= str_pad(dechex($colors[$i]), 2, 0, STR_PAD_LEFT);
        }

        return '#' . $output;
    }

    public function sendEmailAfterCreate(Event $event)
    {
        $data = $event->getData();
        if (!empty($data['data']['commentOn'])) {
            $parentTag = $data['data']['commentOn'];
            $loggedUser = $this->userManager->getLoggedUser();
            $parentPage = $this->getParentPage($data['data']['tag']);
            if (!empty($loggedUser)) {
                $parentComment = $this->pageManager->getOne($parentTag);

                if (!empty($parentComment['owner'])) {
                    $owner = $this->userManager->getOneByName($parentComment['owner']);
                    $this->sendEmailToOwnerAtCreation($parentComment, $loggedUser, $parentPage, ['comment' => $data['data']], $owner);
                }
                $this->sendEmailToTaggedUserAtCreation($parentComment, $loggedUser, $parentPage, ['comment' => $data['data']], $owner ?? null);
            }
        }
    }

    protected function sendEmailToOwnerAtCreation(?array $parentComment, $loggedUser, array $parentPage, array $data, $owner)
    {
        if (!empty($owner) && !empty($loggedUser) && $owner['email'] != $loggedUser['email']) {
            $baseUrl = $this->mailer->getBaseUrl();
            $formattedData = [
                'baseUrl' => $baseUrl,
                'parentPage' => $parentPage,
                'comment' => $data['comment'],
                'parentComment' => $parentComment,
            ];
            $this->mailer->sendEmailFromAdmin(
                $owner['email'],
                $this->templateEngine->render('@core/comments/notify-email-subject.twig', $formattedData),
                $this->templateEngine->render('@core/comments/notify-email-text.twig', $formattedData),
                $this->templateEngine->render('@core/comments/notify-email-html.twig', $formattedData)
            );
        }
    }

    protected function sendEmailToTaggedUserAtCreation(?array $parentComment, $loggedUser, array $parentPage, array $data, $owner)
    {
        $taggedUsers = $this->extractTaggedUsernamesFromContent($data['comment'], $loggedUser, $owner);
        if (!empty($taggedUsers)) {
            $baseUrl = $this->mailer->getBaseUrl();
            $formattedData = [
                'baseUrl' => $baseUrl,
                'parentPage' => $parentPage,
                'comment' => $data['comment'],
                'parentComment' => $parentComment,
            ];
            foreach ($taggedUsers as $user) {
                $this->mailer->sendEmailFromAdmin(
                    $user['email'],
                    $this->templateEngine->render('@core/comments/notify-tag-email-subject.twig', $formattedData),
                    $this->templateEngine->render('@core/comments/notify-tag-email-text.twig', $formattedData),
                    $this->templateEngine->render('@core/comments/notify-tag-email-html.twig', $formattedData)
                );
            }
        }
    }

    protected function extractTaggedUsernamesFromContent(array $comment, $loggedUser, $owner): array
    {
        $users = [];
        try {
            if (preg_match_all("/\B@([^\s!#@<>\\\\\/][^\s<>\\\\\/]{2,})(?=\s|$)/i", $comment['rawbody'], $matches)) {
                foreach ($matches[0] as $idx => $value) {
                    $userName = $matches[1][$idx];
                    if (!empty($userName) && !in_array($userName, array_keys($users))) {
                        $user = $this->userManager->getOneByName($userName);
                        if (!empty($user)) {
                            $users[$userName] = $user;
                        }
                    }
                }
            }
        } catch (Throwable $th) {
        }
        $filteredUsers = [];
        foreach ($users as $user) {
            if (
                $user['email'] != $loggedUser['email']
                && (empty($owner) || ($user['email'] != $owner['email']))
                && !in_array($user['name'], array_keys($filteredUsers))
            ) {
                $filteredUsers[$user['name']] = $user;
            }
        }

        return $filteredUsers;
    }

    public function sendEmailAfterModify(Event $event)
    {
        $data = $event->getData();
    }

    public function sendEmailAfterDelete(Event $event)
    {
        $data = $event->getData();
    }

    /**
     * retrieve parent page of the current tag
     * RECURSIVE.
     *
     * @return array|null $page, null is not parent found
     */
    protected function getParentPage(string $commentTag, array $alreadyFoundTags = []): ?array
    {
        $page = $this->pageManager->getOne($commentTag);
        if (empty($page)) {
            return null;
        } elseif (empty($page['comment_on'])) {
            return $page;
        } elseif (in_array($page['comment_on'], $alreadyFoundTags)) {
            return null;
        }
        $foundTags = $alreadyFoundTags;
        $foundTags[] = $commentTag;

        return $this->getParentPage($page['comment_on'], $foundTags);
    }
}

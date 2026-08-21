<?php

namespace YesWiki\Social\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\HashCashService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\Mailer;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

class CommentService implements EventSubscriberInterface
{
    protected ContainerInterface $container;
    protected AclService $aclService;
    protected DbService $dbService;
    protected EventDispatcher $eventDispatcher;
    protected Mailer $mailer;
    protected PageManager $pageManager;
    protected ParameterBagInterface $params;
    /**
     * @var list<string>
     */
    protected array $pagesWhereCommentWereRendered;
    protected PageContext $pageContext;
    protected UserManager $userManager;
    protected TemplateEngine $templateEngine;
    protected mixed $commentsActivated;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        ContainerInterface $container,
        DbService $dbService,
        AclService $aclService,
        EventDispatcher $eventDispatcher,
        Mailer $mailer,
        PageManager $pageManager,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        UserManager $userManager,
        UrlFormatter $urlFormatter,
        PageContext $pageContext
    ) {
        $this->pageContext = $pageContext;
        $this->urlFormatter = $urlFormatter;
        $this->container = $container;
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

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'comment.created' => 'sendEmailAfterCreate',
            'comment.updated' => 'sendEmailAfterModify',
            'comment.deleted' => 'sendEmailAfterDelete',
        ];
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public function addCommentIfAuthorized(array $content, string $idComment = ''): array
    {
        if (!$this->container->get(AuthenticationService::class)->getLoggedUser()) {
            return [
                'code' => 401,
                'error' => _t('USER_MUST_BE_LOGGED_TO_COMMENT'),
            ];
        }
        if ($this->aclService->hasAccess('comment', $content['pagetag']) && $this->pageManager->getOne($content['pagetag'])) {
            if (!$this->container->get(HashCashService::class)->checkHashcash()) {
                return [
                    'code' => 400,
                    'error' => _t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'),
                ];
            }
            if (empty($idComment)) {
                $newComment = true;

                $numericTag = $this->dbService->dialect()->castToInteger('SUBSTRING(tag, 8)');
                $sql = "SELECT MAX({$numericTag}) AS comment_id"
                    . ' FROM ' . $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)->getValue('table_prefix') . 'pages'
                    . " WHERE parent != ''";
                if ($lastComment = $this->dbService->loadSingle($sql)) {
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

            $this->pageManager->save($idComment, [PageBody::CONTENT => $body], $content['pagetag']);
            if ($newComment) {
                $parentCommentAcl = $this->aclService->load($content['pagetag'], 'comment', false);
                $parentCommentAcl = empty($parentCommentAcl) || empty($parentCommentAcl['list']) ? $this->aclService->load($content['pagetag'], 'comment', true) : $parentCommentAcl;
                $parentCommentAcl = $parentCommentAcl['list'] ?? '';
                $this->aclService->save($idComment, 'write', '%');
                $this->aclService->save($idComment, 'read', '*');
                $this->aclService->save($idComment, 'comment', $parentCommentAcl);
            }

            $comment = $this->pageManager->getOne($idComment);
            if (empty($comment)) {
                return [
                    'code' => 500,
                    'error' => _t('COMMENT_EMPTY_NOT_SAVED'),
                ];
            }
            $com['tag'] = $comment['tag'];
            $com['commentOn'] = $comment['parent'];
            $com['rawbody'] = PageBody::content($comment['body']);

            $oldPage = $this->pageContext->getTag();
            $oldPageArray = $this->pageContext->getPage();
            $this->pageContext->setTag($comment['tag']);
            $this->pageContext->setPage($comment);
            $com['body'] = $this->container->get(MarkdownFormatterService::class)->format($com['rawbody']);
            $this->pageContext->setTag($oldPage);
            $this->pageContext->setPage($oldPageArray);
            $this->setUserData($comment, 'user', $com);
            $this->setUserData($comment, 'owner', $com);
            $com['date'] = 'le ' . date('d.m.Y à H:i:s', strtotime($comment['time']));
            if ($this->aclService->hasAccess('comment', $comment['tag'])) {
                $com['linkcomment'] = $this->urlFormatter->href('pages/' . $comment['tag'] . '/comments', 'api');
            }
            if ($this->aclService->isOwner($comment['tag']) || $this->aclService->isAdmin()) {
                $com['linkeditcomment'] = $this->urlFormatter->href('edit', $comment['tag']);
                $com['linkdeletecomment'] = $this->urlFormatter->href("comments/{$comment['tag']}/delete", 'api');
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
                'html' => $this->container->get(TemplateEngine::class)->renderSafely('@core/comment.twig', ['comment' => $com]),
            ] + $errors;
        }

        return [
            'code' => 403,
            'error' => _t('USER_NOT_ALLOWED_TO_COMMENT'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $commentTag): array
    {
        $comments = $this->loadComments($commentTag, true);
        foreach ($comments as $com) {
            $this->pageManager->deleteOrphaned($com['tag']);
        }
        $comment = $this->pageManager->getOne($commentTag);
        if (empty($comment)) {
            return [];
        }
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
     * @return array<array<string, mixed>> all comments and their corresponding properties
     */
    public function loadCommentsRecursive(string $tag, bool $bypassAcls = false): array
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
     * @return array<array<string, mixed>> all comments and their corresponding properties
     */
    public function loadComments(string $tag, bool $bypassAcls = false, ?string $username = null): array
    {
        $query = 'SELECT * FROM ' . $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['table_prefix'] . 'pages WHERE ';
        $params = [];
        if (empty($tag)) {
            $query .= "parent != '' ";
        } else {
            $query .= 'parent = ? ';
            $params[] = $tag;
        }
        if (!empty($username)) {
            $userCol = $this->dbService->quoteIdentifier('user');
            $query .= "AND ($userCol = ? OR owner = ?) ";
            $params[] = $username;
            $params[] = $username;
        }

        $query .= 'AND tag != ? ';
        $params[] = $tag;

        $numericTag = $this->dbService->dialect()->castToInteger('substring(tag, 8)');
        $query .= "AND latest = 'Y' ORDER BY {$numericTag}";
        $comments = array_filter($this->dbService->loadAll($query, $params), function ($comment) {
            return !empty($comment['tag']);
        });

        foreach ($comments as $id => $comment) {
            $parentPage = $this->getParentPage($comment['tag']);
            $comments[$id]['parentTag'] = !empty($parentPage['tag']) ? $parentPage['tag'] : '';
        }

        if (!$bypassAcls) {
            $comments = array_filter($comments, function ($com) {
                return !empty($com['parent']) && $this->aclService->hasAccess('read', $com['parent']);
            });
        }

        return $comments;
    }

    /**
     * @param array<mixed>|null $comments
     */
    public function getCommentList(string $tag, bool $first = true, ?array $comments = null): string
    {
        $com = [];
        $com['first'] = $first;
        $com['tag'] = $tag;
        $com['comments'] = [];
        $comments = is_array($comments) ? $comments : $this->loadComments($tag);
        if ($comments) {
            foreach ($comments as $i => $comment) {
                $com['comments'][$i]['tag'] = $comment['tag'];
                $com['comments'][$i]['commentOn'] = $comment['parent'];

                $com['comments'][$i]['rawbody'] = PageBody::content(PageBody::decode($comment['body']));
                $com['comments'][$i]['body'] = $this->container->get(MarkdownFormatterService::class)->format($com['comments'][$i]['rawbody']);
                $this->setUserData($comment, 'user', $com['comments'][$i]);
                $this->setUserData($comment, 'owner', $com['comments'][$i]);
                $com['comments'][$i]['date'] = 'le ' . date('d.m.Y à H:i:s', strtotime($comment['time']));
                if ($this->aclService->hasAccess('comment', $comment['tag'])) {
                    $com['comments'][$i]['linkcomment'] = $this->urlFormatter->href('pages/' . $comment['tag'] . '/comments', 'api');
                }
                if ($this->aclService->isOwner($comment['tag']) || $this->aclService->isAdmin()) {
                    $com['comments'][$i]['linkeditcomment'] = $this->urlFormatter->href('edit', $comment['tag']);
                    $com['comments'][$i]['linkdeletecomment'] = $this->urlFormatter->href('comments/' . $comment['tag'] . '/delete', 'api');
                }
                $com['comments'][$i]['reponses'] = $this->getCommentList($comment['tag'], false);
            }
        }

        return $this->container->get(TemplateEngine::class)->renderSafely('@core/comment-list.twig', $com);
    }

    public function getCommentsCount(string $tag): int
    {
        return $this->dbService->countRows("
            SELECT * FROM {$this->dbService->prefixTable('pages')}
            WHERE parent = ? AND latest = 'Y'
        ", [$tag]);
    }

    /**
     * The latest comments of every page, newest first (historic Wiki::LoadRecentComments()).
     *
     * @param int $limit 0 means all of them
     *
     * @return array<mixed>
     */
    public function getRecentComments(int $limit = 0): array
    {
        $lim = $limit > 0 ? ' limit ' . $limit : '';

        return $this->dbService->loadAll(
            'select * from ' . $this->dbService->prefixTable('pages')
            . " where parent != '' and latest = 'Y' " . 'order by time desc ' . $lim
        );
    }

    /**
     * The most recently commented pages, each carrying comment_user/comment_time/comment_tag of its latest first-revision comment (historic Wiki::LoadRecentlyCommented()).
     *
     * @return array<mixed>
     */
    public function getRecentlyCommented(int $limit = 50): array
    {
        $pages = [];

        if ($ids = $this->dbService->loadAll('select min(id) as id from ' . $this->dbService->prefixTable('pages') . " where parent != '' group by tag order by id desc")) {
            $num = 0;
            $comments = [];
            foreach ($ids as $id) {
                $comment = $this->dbService->loadSingle('select * from ' . $this->dbService->prefixTable('pages') . " where id = '" . $id['id'] . "' limit 1");
                if (empty($comment)) {
                    continue;
                }
                if (!isset($comments[$comment['parent']]) && $num < $limit) {
                    $comments[$comment['parent']] = $comment;
                    $num++;
                }
            }

            foreach ($comments as $comment) {
                $page = $this->pageManager->getOne($comment['parent']);
                if (empty($page)) {
                    continue;
                }
                $page['comment_user'] = $comment['user'];
                $page['comment_time'] = $comment['time'];
                $page['comment_tag'] = $comment['tag'];
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * @param array<string, mixed> $comment
     * @param array<string, mixed> $data
     */
    private function setUserData(array $comment, string $key, array &$data): void
    {
        if (in_array($key, ['user', 'owner'], true) && !empty($comment[$key])) {
            $data[$key] = $comment[$key];
            $data["link$key"] = $this->urlFormatter->href('', $comment[$key]);
            $data["{$key}color"] = $this->genColorCodeFromText($comment[$key]);
            $data["{$key}picture"] =
                !empty($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_comment_avatar'])
                ? $this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['default_comment_avatar']
                : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='" . str_replace('#', '%23', $data["{$key}color"]) . "' class='bi bi-person-circle' viewBox='0 0 16 16'%3E%3Cpath d='M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z'/%3E%3Cpath fill-rule='evenodd' d='M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z'/%3E%3C/svg%3E";
        }
    }

    public function getCommentForm(string $tag): string
    {
        $options = [];
        if (!$this->container->get(AuthenticationService::class)->getLoggedUser()) {
            $options['alerts'][] = [
                'class' => 'info',
                'text' => _t('USER_MUST_BE_LOGGED_TO_COMMENT'),
            ];
        } else {
            if ($this->aclService->hasAccess('comment', $tag)) {
                $hashCashCode = '';
                if ($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['use_hashcash']) {
                    $hashCash = $this->container->get(HashCashService::class);
                    $hashCashCode = $hashCash->getJavascriptCode('post-comment');
                }
                $page = $this->pageManager->getOne($tag);
                $commentOn = !empty($page['parent']) ? $page['parent'] : ($page['tag'] ?? $tag);
                $tempTag = ($this->container->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['temp_tag_for_entry_creation'] ?? null) . '_' . bin2hex(random_bytes(10));
                $options = [
                    'pagetag' => $commentOn,
                    'formlink' => $this->urlFormatter->href('comments', 'api'),
                    'hashcash' => $hashCashCode,
                    'tempTag' => $tempTag,
                ];
            } else {
                $options['alerts'][] = [
                    'class' => 'warning',
                    'text' => _t('USER_NOT_ALLOWED_TO_COMMENT'),
                ];
            }
        }

        return $this->container->get(TemplateEngine::class)->renderSafely('@core/comment-form.twig', $options);
    }

    public function renderCommentsForPage(string $tag, bool $showOnlyOnce = true): string
    {
        if (!$this->commentsActivated) {
            return '';
        }
        $output = '';

        if ($showOnlyOnce && in_array($tag, $this->pagesWhereCommentWereRendered)) {
            return '';
        }
        $aclsService = $this->container->get(AclService::class);
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
                    'user' => ($hasAccessComment) ? null : $this->container->get(AuthenticationService::class)->getLoggedUser(),
                    'form' => ($hasAccessComment) ? $this->getCommentForm($tag) : '',
                ];
            $output = $this->container->get(TemplateEngine::class)->renderSafely('@core/comment-for-page.twig', $options);
        }

        $this->pagesWhereCommentWereRendered[] = $tag;

        return $output;
    }

    public function genColorCodeFromText(string $text, int $min_brightness = 100, int $spec = 10): string
    {
        if ($spec < 2 or $spec > 10) {
            throw new \Exception("$spec is out of range");
        }
        if ($min_brightness < 0 or $min_brightness > 255) {
            throw new \Exception("$min_brightness is out of range");
        }

        $hash = md5($text);
        $colors = [];
        for ($i = 0; $i < 3; $i++) {
            $colors[$i] = (int)max([round((hexdec(substr($hash, $spec * $i, $spec)) / hexdec(str_pad('', $spec, 'F'))) * 255), $min_brightness]);
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
            $output .= str_pad(dechex($colors[$i]), 2, '0', STR_PAD_LEFT);
        }

        return '#' . $output;
    }

    public function sendEmailAfterCreate(Event $event): void
    {
        $data = $event->getData();
        if (!empty($data['data']['commentOn'])) {
            $parentTag = $data['data']['commentOn'];
            $loggedUser = $this->userManager->getOneByName($this->userManager->getLoggedUserName());
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

    /**
     * @param array<string, mixed>|null $parentComment
     * @param array<string, mixed>|null $parentPage
     * @param array<string, mixed>      $data
     */
    protected function sendEmailToOwnerAtCreation(?array $parentComment, User $loggedUser, ?array $parentPage, array $data, ?User $owner): void
    {
        if (!empty($owner) && $owner['email'] != $loggedUser['email']) {
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

    /**
     * @param array<string, mixed>|null $parentComment
     * @param array<string, mixed>|null $parentPage
     * @param array<string, mixed>      $data
     */
    protected function sendEmailToTaggedUserAtCreation(?array $parentComment, User $loggedUser, ?array $parentPage, array $data, ?User $owner): void
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

    /**
     * @param array<string, mixed> $comment
     *
     * @return array<string, User>
     */
    protected function extractTaggedUsernamesFromContent(array $comment, User $loggedUser, ?User $owner): array
    {
        $users = [];
        try {
            if (preg_match_all("/\B@([^\s!#@<>\\\\\/][^\s<>\\\\\/]{2,})(?=\s|$)/i", $comment['rawbody'], $matches)) {
                foreach ($matches[0] as $idx => $value) {
                    $userName = $matches[1][$idx];
                    if (!in_array($userName, array_keys($users))) {
                        $user = $this->userManager->getOneByName($userName);
                        if (!empty($user)) {
                            $users[$userName] = $user;
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
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

    public function sendEmailAfterModify(Event $event): void
    {
        $data = $event->getData();
    }

    public function sendEmailAfterDelete(Event $event): void
    {
        $data = $event->getData();
    }

    /**
     * retrieve parent page of the current tag RECURSIVE.
     *
     * @param list<string> $alreadyFoundTags
     *
     * @return array<string, mixed>|null $page, null is not parent found
     */
    protected function getParentPage(string $commentTag, array $alreadyFoundTags = []): ?array
    {
        $page = $this->pageManager->getOne($commentTag);
        if (empty($page)) {
            return null;
        } elseif (empty($page['parent'])) {
            return $page;
        } elseif (in_array($page['parent'], $alreadyFoundTags)) {
            return null;
        }
        $foundTags = $alreadyFoundTags;
        $foundTags[] = $commentTag;

        return $this->getParentPage($page['parent'], $foundTags);
    }
}

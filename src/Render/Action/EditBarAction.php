<?php

namespace YesWiki\Render\Action;

use Carbon\Carbon;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\ContentTypeResolver;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Routing\ReservedTags;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;
use YesWiki\Render\Service\TemplateEngine;

/** `{{editbar}}` -- converted from the procedural actions/barreredaction.php by ticket 06. */
class EditBarAction extends YesWikiAction implements RegisteredAction
{
    /** What the edit button says, by what the row is (ticket 63). */
    private const EDIT_LABELS = [
        PageType::PAGE => 'TEMPLATE_EDIT_THIS_PAGE',
        PageType::COMMENT => 'TEMPLATE_EDIT_THIS_PAGE',
        PageType::ENTRY => 'TEMPLATE_EDIT_THIS_ENTRY',
        PageType::USER => 'TEMPLATE_EDIT_THIS_ACCOUNT',
        PageType::FILE => 'TEMPLATE_EDIT_THIS_FILE',
        PageType::FORM => 'TEMPLATE_EDIT_THIS_FORM',
        PageType::LIST => 'TEMPLATE_EDIT_THIS_LIST',
    ];

    public static function performableName(): string
    {
        return 'editbar';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        if (empty($this->getService(PerformableArguments::class)->get('page'))
            && ReservedTags::isReserved($this->getService(PageContext::class)->getTag())) {
            return;
        }

        $user = $this->getService(AuthenticationService::class)->getLoggedUser();
        if ((!empty($user) || $this->getService(AclService::class)->hasAccess('write')) && $this->getService(PageContext::class)->getRawMethod() != 'revisions') {
            $page = $this->getService(PerformableArguments::class)->get('page');
            if (empty($page)) {
                $page = $this->getService(PageContext::class)->getTag();
                $time = $this->getService(PageContext::class)->getPageTime();
                $content = $this->getService(PageContext::class)->getPage();
            } else {
                $content = $this->getService(PageManager::class)->getOne($page);
                $time = $content['time'] ?? '';
            }
            $options['page'] = $page;
            $options['linkpage'] = $this->getService(UrlFormatter::class)->href('', $page);

            $template = $this->getService(PerformableArguments::class)->get('template');
            if (empty($template)) {
                $template = 'barreredaction_basic.twig';
            }

            $options['class'] = ($this->getService(PerformableArguments::class)->get('class') ? 'footer ' . $this->getService(PerformableArguments::class)->get('class') : 'footer');

            if ($this->getService(AclService::class)->hasAccess('write')) {
                if ($this->getService(AclService::class)->hasAccess('write', $page)
                    && !$this->getService(HibernationService::class)->isWikiHibernated()
                    && !$this->isEditingThisPage($page)) {
                    $options['linkedit'] = $this->getService(UrlFormatter::class)->href(
                        'edit',
                        $page,
                        ['incomingurl' => WikiUrls::absoluteUrl()],
                        false
                    );
                }

                if ($time) {
                    date_default_timezone_set(@date_default_timezone_get());
                    $options['linkrevisions'] = $this->getService(UrlFormatter::class)->href('revisions', $page);
                    $options['time'] = $this->day($time);
                }

                if ($content) {
                    $owner = $this->getService(PageManager::class)->getOwner($page);

                    if ($this->getService(AclService::class)->isOwner($page)) {
                        $options['owner'] = _t('TEMPLATE_OWNER') . ' : ' . _t('TEMPLATE_YOU');
                    } elseif ($owner) {
                        $options['owner'] = _t('TEMPLATE_OWNER') . ' : ' . $owner;
                    } else {
                        $options['owner'] = _t('TEMPLATE_NO_OWNER');
                    }

                    if ($this->getService(AclService::class)->isOwner($page) || $this->getService(AclService::class)->isAdmin()) {
                        $options['owner'] .= ' - ' . _t('TEMPLATE_PERMISSIONS');
                        if (!$this->getService(HibernationService::class)->isWikiHibernated()) {
                            $options['linkacls'] = $this->getService(UrlFormatter::class)->href('acls', $page);
                            $options['linkdeletepage'] = $this->getService(UrlFormatter::class)->href('deletepage', $page);
                        }
                        $aclsService = $this->getService(AclService::class);
                        $hasAccessComment = $aclsService->hasAccess('comment');
                        $options['wikigroups'] = $this->getService(GroupOperationsService::class)->getAll();
                        if ($this->getService(ParameterBagInterface::class)->get('comments_activated')) {
                            if ($hasAccessComment) {
                                $options['linkclosecomments'] = $this->getService(UrlFormatter::class)->href('claim', $page, ['action' => 'closecomments'], false);
                            } else {
                                $options['linkopencomments'] = $this->getService(UrlFormatter::class)->href('claim', $page, ['action' => 'opencomments'], false);
                            }
                        }
                    } elseif (!$owner && $this->getService(AuthenticationService::class)->getLoggedUser()) {
                        $options['owner'] .= ' - ' . _t('TEMPLATE_CLAIM');
                        if (!$this->getService(HibernationService::class)->isWikiHibernated()) {
                            $options['linkacls'] = $this->getService(UrlFormatter::class)->href('claim', $page);
                        }
                    }
                }
            }
            $options['editLabel'] = _t(self::EDIT_LABELS[$this->getService(PageManager::class)->typeOf($page) ?? PageType::PAGE] ?? 'TEMPLATE_EDIT_THIS_PAGE');
            $options['linkduplicate'] = $this->getService(UrlFormatter::class)->href('duplicate', $page);
            $options['linkshare'] = $this->getService(UrlFormatter::class)->href('share', $page);
            $options += $this->contentFacts($page, $content);
            $options['userIsOwner'] = $this->getService(AclService::class)->isOwner($page);
            $options['userIsAdmin'] = $this->getService(AclService::class)->isAdmin();
            $options['userIsAdminOrOwner'] = $this->getService(AclService::class)->isAdmin() || $this->getService(AclService::class)->isOwner($page);
            $favoritesManager = $this->getService(FavoritesManager::class);
            if (!empty($user) && $favoritesManager->areFavoritesActivated()) {
                $options['currentuser'] = $user['name'];
                $options['isUserFavorite'] = $favoritesManager->isUserFavorite($user['name'], $page);
            }

            echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $options);
            echo ' <!-- /.footer -->' . "\n";
        }
    }

    /**
     * What the Content itself says about its own making: who wrote it, what kind of Content it is, when it was created and last changed.
     *
     * @param array<string, mixed>|null $content the page row, body already decoded
     *
     * @return array<string, mixed>
     */
    private function contentFacts(string $page, ?array $content): array
    {
        if (empty($content)) {
            return [];
        }

        $body = is_array($content['body'] ?? null) ? $content['body'] : [];
        $form = $this->getService(ContentTypeResolver::class)->formFor($page);

        return [
            'author' => $this->authorName((string)$this->getService(PageManager::class)->getOwner($page)),
            'contentLabel' => $form['label'] ?? '',
            'contentUrl' => empty($form['tag']) ? '' : $this->getService(UrlFormatter::class)->href('', $form['tag'], [], false),

            'createdAt' => $this->moment(
                $body['created_at'] ?? $this->getService(PageManager::class)->getCreateTime($page)
            ),

            'updatedAt' => $this->moment($body['updated_at'] ?? ($content['time'] ?? null)),
        ];
    }

    /** Whether the request being served is the edit screen *of this page*. */
    private function isEditingThisPage(string $page): bool
    {
        $pageContext = $this->getService(PageContext::class);

        return $pageContext->getMethod() === 'edit' && $page === $pageContext->getTag();
    }

    private function authorName(string $owner): string
    {
        $isIpAddress = $owner !== '' && preg_replace('/([0-9]|\.)/', '', $owner) === '';

        return ($owner === '' || $isIpAddress) ? '' : $owner;
    }

    /** A stored timestamp as a date and a time of day: "4 juillet 2026 19:54". */
    private function moment(mixed $stamp): ?string
    {
        return $this->inTheReadersLanguage($stamp, 'LLL');
    }

    /** The same, to the day: "4 juillet 2026". */
    private function day(mixed $stamp): ?string
    {
        return $this->inTheReadersLanguage($stamp, 'LL');
    }

    /** A date written the way the reader's language writes dates. */
    private function inTheReadersLanguage(mixed $stamp, string $format): ?string
    {
        if (!is_string($stamp) || trim($stamp) === '') {
            return null;
        }

        try {
            $moment = Carbon::parse($stamp);
        } catch (\Throwable) {
            return null;
        }

        $moment->locale($this->getService(LanguageService::class)->preferredLanguage());

        return $moment->isoFormat($format);
    }
}

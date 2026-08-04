<?php

namespace YesWiki\Render\Action;

use Carbon\Carbon;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `{{editbar}}` -- converted from the procedural actions/barreredaction.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class EditBarAction extends YesWikiAction implements RegisteredAction
{
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
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // A routed name is not a page, so none of the page actions below mean anything on
        // one: `/search` and `/doc` were offering "edit this page", a file manager, duplicate
        // and share for a tag no Content can ever occupy -- clicking edit opened an editor
        // for a page that ReservedTags refuses to let anyone save (ticket 20).
        //
        // An explicit {{editbar page="..."}} still renders: naming a page is asking for that
        // page's bar, and a route is free to show one for something else.
        // empty(), not === null: get() defaults to '' when the parameter is absent
        if (empty($this->getService(PerformableArguments::class)->get('page'))
            && ReservedTags::isReserved($this->getService(PageContext::class)->getTag())) {
            return;
        }

        $user = $this->getService(AuthenticationService::class)->getLoggedUser();
        if ((!empty($user) || $this->getService(AclService::class)->hasAccess('write')) && $this->getService(PageContext::class)->getRawMethod() != 'revisions') {
            // on récupére la page et ses valeurs associées
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

            // on choisit le template utilisé
            $template = $this->getService(PerformableArguments::class)->get('template');
            if (empty($template)) {
                $template = 'barreredaction_basic.twig';
            }

            // on peut ajouter des classes, la classe par défaut est .footer
            $options['class'] = ($this->getService(PerformableArguments::class)->get('class') ? 'footer ' . $this->getService(PerformableArguments::class)->get('class') : 'footer');

            if ($this->getService(AclService::class)->hasAccess('write')) {
                // on ajoute le lien d'édition si l'action est autorisée
                //
                // ...unless this *is* the edit screen for this page: the editor renders its
                // own cluster in the same corner (save, preview, delete), and "edit this
                // page" offered to someone already editing it is a link to where they are.
                // Without `linkedit` there is no cluster here at all -- see the template.
                if ($this->getService(AclService::class)->hasAccess('write', $page)
                    && !$this->getService(HibernationService::class)->isWikiHibernated()
                    && !$this->isEditingThisPage($page)) {
                    $options['linkedit'] = $this->getService(UrlFormatter::class)->href('edit', $page);
                }

                if ($time) {
                    // hack to hide E_STRICT error if no timezone set
                    date_default_timezone_set(@date_default_timezone_get());
                    $options['linkrevisions'] = $this->getService(UrlFormatter::class)->href('revisions', $page);
                    $options['time'] = $this->day($time);
                }

                // if this page exists
                if ($content) {
                    $owner = $this->getService(PageManager::class)->getOwner($page);
                    // message
                    if ($this->getService(AclService::class)->isOwner($page)) {
                        $options['owner'] = _t('TEMPLATE_OWNER') . ' : ' . _t('TEMPLATE_YOU');
                    } elseif ($owner) {
                        $options['owner'] = _t('TEMPLATE_OWNER') . ' : ' . $owner;
                    } else {
                        $options['owner'] = _t('TEMPLATE_NO_OWNER');
                    }

                    // if current user is owner or admin
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
     * What the Content itself says about its own making: who wrote it, what kind of
     * Content it is, when it was created and last changed.
     *
     * This used to be the bazar entry footer's job, which is why every *page* grew one
     * when ticket 10 made pages render through a form -- two bars saying overlapping
     * things, one above the other. The facts belong with the links about them (the owner
     * with permissions, the dates with the revisions), so they are gathered here and the
     * entry footer keeps them only where there is no edit bar: an entry embedded in some
     * other page.
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
            // the *owner*, which is who a wiki records as having written a page. An
            // anonymous edit records an IP address; that is not a name to print
            'author' => $this->authorName((string)$this->getService(PageManager::class)->getOwner($page)),
            'contentLabel' => $form['label'] ?? '',
            // A bazar entry keeps `created_at`/`updated_at` in its body; a page keeps
            // neither, and its creation date is the date of its *first revision* -- a fact
            // the `pages` table has always held. What a page must never do is what the
            // entry footer did with it: `entry.created_at|date(…)` on a body without the
            // key is Twig's date filter on null, which is **now**, so a page five years
            // old claimed to have been created the second you looked at it.
            'createdAt' => $this->moment(
                $body['created_at'] ?? $this->getService(PageManager::class)->getCreateTime($page)
            ),
            // ...and when it keeps no `updated_at` either, the revision being served is
            // when it was last changed. `time` says the same thing to the day; this says
            // it to the minute, so the two dates on the line are the same shape.
            'updatedAt' => $this->moment($body['updated_at'] ?? ($content['time'] ?? null)),
        ];
    }

    /**
     * Whether the request being served is the edit screen *of this page*.
     *
     * `{{editbar page="SomethingElse"}}` on an edit screen is a bar for another page, and
     * that one still offers to edit it.
     */
    private function isEditingThisPage(string $page): bool
    {
        $pageContext = $this->getService(PageContext::class);

        // getMethod(), not getRawMethod(): `editiframe` is the edit screen too
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

    /**
     * A date written the way the reader's language writes dates.
     *
     * Through Carbon (already a dependency) rather than `date(_t('TEMPLATE_DATE_FORMAT'))`,
     * which is what this used to do. That asked translators to write PHP format letters,
     * and three of the nine catalogs answered by translating the *letters*: `'z L A'`
     * renders "215 0 AM", and Tamil's renders three Tamil numerals. Every other one said
     * `d M Y`, which prints English month names in every language -- "04 Jul 2026" in a
     * French wiki, which is what sent me here. A date formatter that knows the locale gets
     * all nine right and asks nobody to translate anything.
     *
     * The old key is left in the catalogs: it costs nothing there, and an extension may
     * still be reading it.
     */
    private function inTheReadersLanguage(mixed $stamp, string $format): ?string
    {
        if (!is_string($stamp) || trim($stamp) === '') {
            return null;
        }

        try {
            $moment = Carbon::parse($stamp);
        } catch (\Throwable) {
            // a stored value that is not a date at all: say nothing rather than something
            return null;
        }

        // as a statement, not chained: `locale()` with no argument is the getter, so its
        // declared return type is `static|string` and a chained call is not statically a
        // Carbon. It sets the locale on this instance either way.
        $moment->locale((string)($GLOBALS['prefered_language'] ?? 'en'));

        return $moment->isoFormat($format);
    }
}

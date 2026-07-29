<?php

namespace YesWiki\Render\Action;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{barreredaction}}` -- converted from the procedural actions/barreredaction.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class BarreredactionAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'barreredaction';
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
        $user = $this->wiki->services->get(AuthenticationService::class)->getLoggedUser();
        if ((!empty($user) || $this->getService(AclService::class)->hasAccess('write')) && $this->wiki->method != 'revisions') {
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
                if ($this->getService(AclService::class)->hasAccess('write', $page) && !$this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                    $options['linkedit'] = $this->getService(UrlFormatter::class)->href('edit', $page);
                }

                if ($time) {
                    // hack to hide E_STRICT error if no timezone set
                    date_default_timezone_set(@date_default_timezone_get());
                    $options['linkrevisions'] = $this->getService(UrlFormatter::class)->href('revisions', $page);
                    $options['time'] = date(_t('TEMPLATE_DATE_FORMAT'), strtotime($time));
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
                        if (!$this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                            $options['linkacls'] = $this->getService(UrlFormatter::class)->href('acls', $page);
                            $options['linkdeletepage'] = $this->getService(UrlFormatter::class)->href('deletepage', $page);
                        }
                        $aclsService = $this->wiki->services->get(AclService::class);
                        $hasAccessComment = $aclsService->hasAccess('comment');
                        $options['wikigroups'] = $this->getService(GroupOperationsService::class)->getAll();
                        if ($this->wiki->services->get(ParameterBagInterface::class)->get('comments_activated')) {
                            if ($hasAccessComment && $hasAccessComment !== 'comments-closed') {
                                $options['linkclosecomments'] = $this->getService(UrlFormatter::class)->href('claim', $page, ['action' => 'closecomments'], false);
                            } else {
                                $options['linkopencomments'] = $this->getService(UrlFormatter::class)->href('claim', $page, ['action' => 'opencomments'], false);
                            }
                        }
                    } elseif (!$owner && $this->getService(AuthenticationService::class)->getLoggedUser()) {
                        $options['owner'] .= ' - ' . _t('TEMPLATE_CLAIM');
                        if (!$this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                            $options['linkacls'] = $this->getService(UrlFormatter::class)->href('claim', $page);
                        }
                    }
                }
            }
            $options['linkduplicate'] = $this->getService(UrlFormatter::class)->href('duplicate', $page);
            $options['linkshare'] = $this->getService(UrlFormatter::class)->href('share', $page);
            $options['userIsOwner'] = $this->getService(AclService::class)->isOwner($page);
            $options['userIsAdmin'] = $this->getService(AclService::class)->isAdmin();
            $options['userIsAdminOrOwner'] = $this->getService(AclService::class)->isAdmin() || $this->getService(AclService::class)->isOwner($page);
            $favoritesManager = $this->wiki->services->get(FavoritesManager::class);
            if (!empty($user) && $favoritesManager->areFavoritesActivated()) {
                $options['currentuser'] = $user['name'];
                $options['isUserFavorite'] = $favoritesManager->isUserFavorite($user['name'], $page);
            }

            echo $this->wiki->render("@core/$template", $options);
            echo ' <!-- /.footer -->' . "\n";
        }
    }
}

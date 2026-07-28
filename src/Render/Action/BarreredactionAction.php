<?php

namespace YesWiki\Render\Action;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\AclService;
use YesWiki\Content\Service\FavoritesManager;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

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
        if ((!empty($user) || $this->wiki->HasAccess('write')) && $this->wiki->method != 'revisions') {
            // on récupére la page et ses valeurs associées
            $page = $this->wiki->GetParameter('page');
            if (empty($page)) {
                $page = $this->wiki->GetPageTag();
                $time = $this->wiki->GetPageTime();
                $content = $this->wiki->page;
            } else {
                $content = $this->wiki->LoadPage($page);
                $time = $content['time'];
            }
            $options['page'] = $page;
            $options['linkpage'] = $this->wiki->href('', $page);

            // on choisit le template utilisé
            $template = $this->wiki->GetParameter('template');
            if (empty($template)) {
                $template = 'barreredaction_basic.twig';
            }

            // on peut ajouter des classes, la classe par défaut est .footer
            $options['class'] = ($this->wiki->GetParameter('class') ? 'footer ' . $this->wiki->GetParameter('class') : 'footer');

            if ($this->wiki->HasAccess('write')) {
                // on ajoute le lien d'édition si l'action est autorisée
                if ($this->wiki->HasAccess('write', $page) && !$this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                    $options['linkedit'] = $this->wiki->href('edit', $page);
                }

                if ($time) {
                    // hack to hide E_STRICT error if no timezone set
                    date_default_timezone_set(@date_default_timezone_get());
                    $options['linkrevisions'] = $this->wiki->href('revisions', $page);
                    $options['time'] = date(_t('TEMPLATE_DATE_FORMAT'), strtotime($time));
                }

                // if this page exists
                if ($content) {
                    $owner = $this->wiki->GetPageOwner($page);
                    // message
                    if ($this->wiki->UserIsOwner($page)) {
                        $options['owner'] = _t('TEMPLATE_OWNER') . ' : ' . _t('TEMPLATE_YOU');
                    } elseif ($owner) {
                        $options['owner'] = _t('TEMPLATE_OWNER') . ' : ' . $owner;
                    } else {
                        $options['owner'] = _t('TEMPLATE_NO_OWNER');
                    }

                    // if current user is owner or admin
                    if ($this->wiki->UserIsOwner($page) || $this->wiki->UserIsAdmin()) {
                        $options['owner'] .= ' - ' . _t('TEMPLATE_PERMISSIONS');
                        if (!$this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                            $options['linkacls'] = $this->wiki->href('acls', $page);
                            $options['linkdeletepage'] = $this->wiki->href('deletepage', $page);
                        }
                        $aclsService = $this->wiki->services->get(AclService::class);
                        $hasAccessComment = $aclsService->hasAccess('comment');
                        $options['wikigroups'] = $this->wiki->GetGroupsList();
                        if ($this->wiki->services->get(ParameterBagInterface::class)->get('comments_activated')) {
                            if ($hasAccessComment && $hasAccessComment !== 'comments-closed') {
                                $options['linkclosecomments'] = $this->wiki->href('claim', $page, ['action' => 'closecomments'], false);
                            } else {
                                $options['linkopencomments'] = $this->wiki->href('claim', $page, ['action' => 'opencomments'], false);
                            }
                        }
                    } elseif (!$owner && $this->wiki->GetUser()) {
                        $options['owner'] .= ' - ' . _t('TEMPLATE_CLAIM');
                        if (!$this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                            $options['linkacls'] = $this->wiki->href('claim', $page);
                        }
                    }
                }
            }
            $options['linkduplicate'] = $this->wiki->href('duplicate', $page);
            $options['linkshare'] = $this->wiki->href('share', $page);
            $options['userIsOwner'] = $this->wiki->UserIsOwner($page);
            $options['userIsAdmin'] = $this->wiki->UserIsAdmin();
            $options['userIsAdminOrOwner'] = $this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner($page);
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

<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\FavoritesManager;
use YesWiki\Security\Controller\SecurityController;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
$user = $this->services->get(AuthController::class)->getLoggedUser();
if ((!empty($user) || $this->HasAccess('write')) && $this->method != 'revisions') {
    // on récupére la page et ses valeurs associées
    $page = $this->GetParameter('page');
    if (empty($page)) {
        $page = $this->GetPageTag();
        $time = $this->GetPageTime();
        $content = $this->page;
    } else {
        $content = $this->LoadPage($page);
        $time = $content['time'];
    }
    $options['page'] = $page;
    $options['linkpage'] = $this->href('', $page);

    // on choisit le template utilisé
    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'barreredaction_basic.twig';
    }

    // on peut ajouter des classes, la classe par défaut est .footer
    $options['class'] = ($this->GetParameter('class') ? 'footer ' . $this->GetParameter('class') : 'footer');

    if ($this->HasAccess('write')) {
        // on ajoute le lien d'édition si l'action est autorisée
        if ($this->HasAccess("write", $page) && !$this->services->get(SecurityController::class)->isWikiHibernated()) {
            $options['linkedit'] = $this->href("edit", $page);
        }

        if ($time) {
            // hack to hide E_STRICT error if no timezone set
            date_default_timezone_set(@date_default_timezone_get());
            $options['linkrevisions'] = $this->href("revisions", $page);
            $options['time'] = date(_t('TEMPLATE_DATE_FORMAT'), strtotime($time));
        }

        // if this page exists
        if ($content) {
            $owner = $this->GetPageOwner($page);
            // message
            if ($this->UserIsOwner($page)) {
                $options['owner'] = _t('TEMPLATE_OWNER') . " : " . _t('TEMPLATE_YOU');
            } elseif ($owner) {
                $options['owner'] = _t('TEMPLATE_OWNER') . " : " . $owner;
            } else {
                $options['owner'] = _t('TEMPLATE_NO_OWNER');
            }

            // if current user is owner or admin
            if ($this->UserIsOwner($page) || $this->UserIsAdmin()) {
                $options['owner'] .= ' - ' . _t('TEMPLATE_PERMISSIONS');
                if (!$this->services->get(SecurityController::class)->isWikiHibernated()) {
                    $options['linkacls'] = $this->href("acls", $page);
                    $options['linkdeletepage'] = $this->href("deletepage", $page);
                }
                $aclsService = $this->services->get(AclService::class);
                $hasAccessComment = $aclsService->hasAccess('comment');
                $options['wikigroups'] = $this->GetGroupsList();
                if ($this->services->get(ParameterBagInterface::class)->get('comments_activated')) {
                    if ($hasAccessComment && $hasAccessComment !== 'comments-closed') {
                        $options['linkclosecomments'] = $this->href("claim", $page, ['action' => 'closecomments'], false);
                    } else {
                        $options['linkopencomments'] = $this->href("claim", $page, ['action' => 'opencomments'], false);
                    }
                }
            } elseif (!$owner && $this->GetUser()) {
                $options['owner'] .= " - " . _t('TEMPLATE_CLAIM');
                if (!$this->services->get(SecurityController::class)->isWikiHibernated()) {
                    $options['linkacls'] = $this->href("claim", $page);
                }
            }
        }
    }
    $options['linkduplicate'] = $this->href('duplicate', $page);
    $options['linkshare'] = $this->href("share", $page);
    $options['userIsOwner'] = $this->UserIsOwner($page);
    $options['userIsAdmin'] = $this->UserIsAdmin();
    $options['userIsAdminOrOwner'] = $this->UserIsAdmin() || $this->UserIsOwner($page);
    $favoritesManager = $this->services->get(FavoritesManager::class);
    if (!empty($user) && $favoritesManager->areFavoritesActivated()) {
        $options['currentuser'] = $user['name'];
        $options['isUserFavorite'] = $favoritesManager->isUserFavorite($user['name'], $page);
    }

    echo $this->render("@templates/$template", $options);
    echo ' <!-- /.footer -->' . "\n";
}

<?php

use YesWiki\Security\Controller\SecurityController;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
if ($this->HasAccess("write") && $this->method != "revisions") {
    // on récupére la page et ses valeurs associées
    $page = $this->GetParameter('page');
    if (empty($page)) {
        $page = $this->GetPageTag();
        $time = $this->GetPageTime();
        $content = $this->page;
    } else {
        $content = $this->LoadPage($page);
        $time = $content["time"];
    }
    $barreredactionelements['page'] = $page;
    $barreredactionelements['linkpage'] = $this->href('', $page);

    // on choisit le template utilisé
    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'barreredaction_basic.tpl.html';
    }

    // on peut ajouter des classes, la classe par défaut est .footer
    $barreredactionelements['class'] = ($this->GetParameter('class') ? 'footer '.$this->GetParameter('class') : 'footer');

    // on ajoute le lien d'édition si l'action est autorisée
    if ($this->HasAccess("write", $page) && !$this->services->get(SecurityController::class)->isWikiHibernated()) {
        $barreredactionelements['linkedit'] = $this->href("edit", $page);
    }

    if ($time) {
        // hack to hide E_STRICT error if no timezone set
        date_default_timezone_set(@date_default_timezone_get());
        $barreredactionelements['linkrevisions'] = $this->href("revisions", $page);
        $barreredactionelements['time'] = date(_t('TEMPLATE_DATE_FORMAT'), strtotime($time));
    }

    // if this page exists
    if ($content) {
        $owner = $this->GetPageOwner($page);
        // message
        if ($this->UserIsOwner($page)) {
            $barreredactionelements['owner'] = _t('TEMPLATE_OWNER')." : "._t('TEMPLATE_YOU');
        } elseif ($owner) {
            $barreredactionelements['owner'] = _t('TEMPLATE_OWNER')." : ".$owner;
        } else {
            $barreredactionelements['owner'] = _t('TEMPLATE_NO_OWNER');
        }

        // if current user is owner or admin
        if ($this->UserIsOwner($page) || $this->UserIsAdmin()) {
            $barreredactionelements['owner'] .= ' - '._t('TEMPLATE_PERMISSIONS');
            if (!$this->services->get(SecurityController::class)->isWikiHibernated()) {
                $barreredactionelements['linkacls'] = $this->href("acls", $page);
                $barreredactionelements['linkdeletepage'] = $this->href("deletepage", $page);
            }
        } elseif (!$owner && $this->GetUser()) {
            $barreredactionelements['owner'] .= " - "._t('TEMPLATE_CLAIM');
            if (!$this->services->get(SecurityController::class)->isWikiHibernated()) {
                $barreredactionelements['linkacls'] = $this->href("claim", $page);
            }
        }
    }

    //$barreredactionelements['linkreferrers'] = $this->href("referrers", $page);
    //$barreredactionelements['linkdiaporama'] = $this->href("diaporama", $page);
    $barreredactionelements['linkshare'] = $this->href("share", $page);

    echo $this->render("@templates/$template", $barreredactionelements);
    echo ' <!-- /.footer -->'."\n";
}

<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class SecureEditionOfSpecialPages extends YesWikiMigration
{
    protected const SPECIAL_PAGES = [
        'BazaR', 'GererSite', 'GererDroits', 'GererThemes', 'GererMisesAJour', 'GererUtilisateurs',
        'GererDroitsActions', 'GererDroitsHandlers', 'TableauDeBord',
        'PageTitre', 'PageMenuHaut', 'PageRapideHaut', 'PageHeader', 'PageFooter', 'PageCSS', 'PageMenu',
        'PageColonneDroite', 'MotDePassePerdu', 'ParametresUtilisateur', 'GererConfig', 'ActuYeswiki', 'LookWiki',
    ];

    public function run()
    {
        // Ensure that every special page is only editable by admins. AclService::save() now
        // requires the target page to already exist (ACLs live in that page's own metadata
        // column since ticket 03) -- check for that specific, expected condition rather than
        // swallowing every possible failure, so a genuine bug in save() itself still surfaces.
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);
        foreach ($this::SPECIAL_PAGES as $page) {
            if ($pageManager->getOne($page, null, false, true)) {
                $aclService->save($page, 'write', '@admins');
            }
        }
    }
}

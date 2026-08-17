<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\AclService;

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
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);
        foreach ($this::SPECIAL_PAGES as $page) {
            if ($pageManager->getOne($page, null, false, true)) {
                $aclService->save($page, 'write', '@admins');
            }
        }
    }
}

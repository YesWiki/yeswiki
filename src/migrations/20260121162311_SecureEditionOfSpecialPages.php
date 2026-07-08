<?php

use YesWiki\Core\Service\AclService;
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
        // Ensure that every special page is only editable by admins
        foreach ($this::SPECIAL_PAGES as $page) {
            $this->getService(AclService::class)->save($page, 'write', '@admins');
        }
    }
}

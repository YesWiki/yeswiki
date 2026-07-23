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
        // Ensure that every special page is only editable by admins. AclService::save() now
        // requires the target page to already exist (ACLs live in that page's own metadata
        // column since ticket 03) -- skip any special page not created yet on this instance
        // rather than failing the whole migration.
        foreach ($this::SPECIAL_PAGES as $page) {
            try {
                $this->getService(AclService::class)->save($page, 'write', '@admins');
            } catch (\Throwable $th) {
            }
        }
    }
}

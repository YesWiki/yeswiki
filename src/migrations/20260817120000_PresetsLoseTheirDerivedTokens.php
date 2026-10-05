<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Render\Service\DoryphoreLookRestorer;
use YesWiki\Render\Service\PresetUpgrader;

/** ADR-0021: a Preset stops declaring what core can derive, and stops holding typed lengths. */
class PresetsLoseTheirDerivedTokens extends YesWikiMigration
{
    public function run()
    {
        $style = $this->params->has('favorite_style') ? $this->params->get('favorite_style') : '';
        $upgraded = $this->getService(DoryphoreLookRestorer::class)
            ->upgradeAll(PresetUpgrader::hadColouredNavbar($style));

        $navbarWasColoured = $this->forgetColouredNavbarStyle($style);

        if ($upgraded !== [] || $navbarWasColoured) {
            $this->say(
                'presets simplified (ADR-0021): what core can derive -- hover colours, muted'
                . ' text, border shades, the panel and ink of each status colour, the focus'
                . ' ring, the shadow colours, the corner radii -- is no longer declared, and'
                . ' the eleven spacing steps are three. Rewritten: '
                . ($upgraded === [] ? 'none' : DoryphoreLookRestorer::summary($upgraded)) . '.'
                . ($navbarWasColoured
                    ? ' The colored-navbar style is gone; the top bar\'s colours are tokens now'
                    . ' and this wiki\'s presets were given its coloured bar.'
                    : '')
            );
        }
    }

    /** Stop naming a stylesheet that no longer exists. */
    private function forgetColouredNavbarStyle(mixed $style): bool
    {
        if (!is_string($style) || !str_contains($style, 'colored-navbar')) {
            return false;
        }

        $config = $this->getService(ConfigurationService::class)
            ->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $config['favorite_style'] = CSS_PAR_DEFAUT;
        $config->write();

        return true;
    }
}

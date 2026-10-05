<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Render\Service\DoryphoreLookRestorer;
use YesWiki\Render\Service\PresetUpgrader;

/** ADR-0020: a Preset's nine variables become `--yw-*` Design tokens, written out as a complete Preset. */
class PresetsBecomeTokenSets extends YesWikiMigration
{
    public function run()
    {
        $style = $this->params->has('favorite_style') ? $this->params->get('favorite_style') : '';
        $upgraded = $this->getService(DoryphoreLookRestorer::class)
            ->upgradeAll(PresetUpgrader::hadColouredNavbar($style));

        if ($upgraded !== []) {
            $this->say(
                'presets carried over to the --yw-* design tokens (ADR-0020, ADR-0021): '
                . DoryphoreLookRestorer::summary($upgraded)
                . '. What they did not say was filled in from core; adjust it on /admin/preset.'
            );
        }
    }
}

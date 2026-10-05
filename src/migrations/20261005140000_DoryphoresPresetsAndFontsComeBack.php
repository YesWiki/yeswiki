<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DoryphoreLeftovers;
use YesWiki\Render\Service\DoryphoreLookRestorer;
use YesWiki\Render\Service\PresetUpgrader;

/** A wiki upgraded while its presets and fonts sat in private/doryphore gets them back, as complete Presets. */
class DoryphoresPresetsAndFontsComeBack extends YesWikiMigration
{
    public function run()
    {
        $restorer = $this->getService(DoryphoreLookRestorer::class);
        $style = $this->params->has('favorite_style') ? $this->params->get('favorite_style') : '';
        $result = $restorer->restore(YESWIKI_INSTANCE_DIR, PresetUpgrader::hadColouredNavbar($style));

        if ($result['restored'] !== []) {
            $this->say(
                count($result['restored']) . ' presets, fonts and images were copied back from '
                . DoryphoreLeftovers::ASIDE . '/custom/ into custom/: ' . $this->folders($result['restored']) . '.'
            );
        }
        if ($result['upgraded'] !== []) {
            $this->say(
                'presets rewritten as complete --yw-* Presets: ' . DoryphoreLookRestorer::summary($result['upgraded'])
                . '. Check them on /admin/preset.'
            );
        }

        $favourite = $this->params->has('favorite_preset') ? $this->params->get('favorite_preset') : '';
        if (is_string($favourite) && $favourite !== '' && ($result['restored'] !== [] || $result['upgraded'] !== [])) {
            $this->say(
                $restorer->resolves($favourite)
                    ? "the wiki's preset, {$favourite}, is found again and pages wear it."
                    : "the wiki's preset, {$favourite}, is still missing, so pages wear core's own look; choose one on /admin/preset."
            );
        }
    }

    /** @param list<string> $paths */
    private function folders(array $paths): string
    {
        $folders = array_unique(array_map(
            fn (string $path): string => str_starts_with($path, 'custom/fonts/') ? 'custom/fonts/' . explode('/', $path)[2] . '/' : $path,
            $paths
        ));

        return implode(', ', $folders);
    }
}

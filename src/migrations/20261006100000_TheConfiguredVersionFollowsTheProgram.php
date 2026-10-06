<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ProgramVersion;

/** A wiki upgraded from Doryphore still named `doryphore` in `yeswiki_version`, so /admin/updates offered Doryphore's packages; it now names the Program's Release line. */
class TheConfiguredVersionFollowsTheProgram extends YesWikiMigration
{
    public function run()
    {
        $configurationService = $this->getService(ConfigurationService::class);
        $configuration = $configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();

        $line = self::lineToWrite((string)($configuration['yeswiki_version'] ?? ''), $this->getService(ProgramVersion::class)->releaseLine());
        if ($line === null) {
            return;
        }
        $previous = (string)($configuration['yeswiki_version'] ?? '');
        $configuration['yeswiki_version'] = $line;
        $configurationService->write($configuration);

        $this->say("yeswiki_version goes from '{$previous}' to '{$line}': updates now come from the {$line} repository");
    }

    /** The Release line to write in place of $configured, or null when it already names it or the Program names none. */
    public static function lineToWrite(string $configured, string $programLine): ?string
    {
        if ($programLine === '' || strtolower(trim($configured)) === strtolower($programLine)) {
            return null;
        }

        return $programLine;
    }
}

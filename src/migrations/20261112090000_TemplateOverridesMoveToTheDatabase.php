<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Render\Service\TemplateImporter;

/** Ticket 18: the overrides an admin managed in `custom/templates/` move to the database; a folder the wiki cannot write, as in an image on a replica, stays the operator's. */
class TemplateOverridesMoveToTheDatabase extends YesWikiMigration
{
    public function run()
    {
        $this->moveFrom(YESWIKI_INSTANCE_DIR . '/custom/templates');
    }

    public function moveFrom(string $folder): void
    {
        $files = $this->getService(LocalFiles::class);
        if (!$files->isDirectory($folder)) {
            return;
        }
        if (Replication::isOn() || !$files->isWritable($folder)) {
            $this->say('custom/templates/ is the operator\'s here, so its files stay where they are, under what admins save from the screens.');

            return;
        }
        $report = $this->getService(TemplateImporter::class)->importFrom($folder);
        if ($report[TemplateImporter::IMPORTED] !== []) {
            $this->say(count($report[TemplateImporter::IMPORTED]) . ' template override(s) moved from custom/templates/ to the database, with their history from now on.');
        }
        foreach ($report[TemplateImporter::INVALID] as $path => $error) {
            $this->say("custom/templates/$path stays a file, since it does not compile: $error");
        }
        foreach ($report[TemplateImporter::KEPT] as $path) {
            $this->say("custom/templates/$path was imported, but the file could not be removed: the database's copy now hides it.");
        }
    }
}

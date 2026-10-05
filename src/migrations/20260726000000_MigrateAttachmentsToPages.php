<?php

use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\LegacyAttachments;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 17: uploaded files become their own Content type (a `pages` row per file, own ACL, see FileManager).
 */
class MigrateAttachmentsToPages extends YesWikiMigration
{
    private const LISTED = 20;

    public function run()
    {
        $legacy = $this->getService(LegacyAttachments::class);
        $report = $legacy->migrateUploads($this->uploadPath());
        $rewrite = $report['created'] === [] ? ['rewritten' => [], 'unresolved' => []] : $legacy->rewriteReferences();
        $this->sayWhatHappened($report, $rewrite);
    }

    private function uploadPath(): string
    {
        $attachConfig = $this->params->get('attach_config');
        $uploadPath = is_array($attachConfig) ? ($attachConfig['upload_path'] ?? '') : '';

        return is_string($uploadPath) && $uploadPath !== '' ? rtrim($uploadPath, '/') : 'files';
    }

    /**
     * @param array{created: array<string, string>, superseded: list<string>, orphans: list<string>, namedVerbatim: list<string>, alreadyMigrated: list<string>} $report
     * @param array{rewritten: list<string>, unresolved: list<string>}                                                                                           $rewrite
     */
    private function sayWhatHappened(array $report, array $rewrite): void
    {
        if ($report['created'] !== []) {
            $this->say(count($report['created']) . ' attached file(s) became File Content in ' . FileManager::STORAGE_DIR
                . '/, and the file="…" references of ' . count($rewrite['rewritten']) . ' page(s) now name them by tag.');
        }
        $lists = [
            'superseded' => 'older upload(s) of a name a newer upload replaced were left in place in files/, unmigrated',
            'namedVerbatim' => 'file(s) stayed in files/ because some Content names them in full (a Bazar field, a direct link)',
            'orphans' => 'file(s) stayed in files/ because the page they were attached to no longer exists',
        ];
        foreach ($lists as $key => $sentence) {
            if ($report[$key] !== []) {
                $this->say(count($report[$key]) . " {$sentence}: " . self::listed($report[$key]));
            }
        }
        if ($rewrite['unresolved'] !== []) {
            $this->say(count($rewrite['unresolved']) . ' file="…" reference(s) match no File Content of their page and were left as written: ' . self::listed($rewrite['unresolved']));
        }
    }

    /** @param list<string> $items */
    private static function listed(array $items): string
    {
        return implode(', ', array_slice($items, 0, self::LISTED)) . (count($items) > self::LISTED ? ', …' : '');
    }
}

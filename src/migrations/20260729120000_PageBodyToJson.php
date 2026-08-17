<?php

use YesWiki\Content\Service\PageBodyMigrator;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 09: `pages.body` becomes a JSON object for every Content type, for every revision -- not just `latest`.
 */
class PageBodyToJson extends YesWikiMigration
{
    public function run(): void
    {
        $migrator = $this->getService(PageBodyMigrator::class);
        $counts = $migrator->apply();

        $failures = $migrator->verify();
        if (!empty($failures)) {
            $first = array_slice($failures, 0, 5);
            $detail = implode(', ', array_map(
                fn (array $f) => "{$f['tag']}#{$f['id']} ({$f['reason']})",
                $first
            ));

            throw new Exception('Body migration left ' . count($failures) . ' row(s) unconverted: ' . $detail . '. Nothing is recorded as migrated, so re-running is safe once the cause is fixed.');
        }

        unset($counts);
    }
}
